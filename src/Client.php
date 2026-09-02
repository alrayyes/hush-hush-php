<?php

declare(strict_types=1);

namespace HushHush;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use HushHush\Exception\ApiException;
use HushHush\Generated\Api\AuditLogApi;
use HushHush\Generated\Api\HealthApi;
use HushHush\Generated\Api\ObjectsApi;
use HushHush\Generated\ApiException as GeneratedApiException;
use HushHush\Generated\Configuration;
use HushHush\Generated\Model\AuditLogEntry;
use HushHush\Generated\Model\CreateObjectRequest;
use HushHush\Generated\Model\Health;
use HushHush\Generated\Model\ObjectMetadata;
use HushHush\Generated\Model\UpdateObjectRequest;
use HushHush\Generated\Model\UsedBy;

/**
 * A typed, synchronous client for hush-hush, a standalone secrets object store.
 *
 * @see https://github.com/alrayyes/hush-hush
 */
final readonly class Client
{
    private const API_KEY_ENV_VAR = 'HUSH_HUSH_API_KEY';
    private const DEFAULT_TIMEOUT = 30.0;
    private const DEFAULT_MAX_RETRIES = 3;

    private ObjectsApi $objectsApi;
    private HealthApi $healthApi;
    private AuditLogApi $auditLogApi;

    /**
     * @param string               $baseUrl    hush-hush's base URL, e.g. `https://hush-hush.example.com`.
     * @param null|string          $apiKey     Bearer credential for write paths (create/update/delete).
     *                                         Falls back to the `HUSH_HUSH_API_KEY` environment variable when
     *                                         not supplied. Read paths (get, used-by, audit-log query) need
     *                                         no credential at all.
     * @param float                $timeout    per-request timeout, in seconds
     * @param int                  $maxRetries maximum retry attempts for network failures and 5xx/429 responses
     * @param null|ClientInterface $httpClient Override for the underlying HTTP client, mainly for tests.
     *                                         When given, `$timeout` and `$maxRetries` are ignored — the
     *                                         caller owns retry/timeout configuration on the client it passes in.
     */
    public function __construct(
        string $baseUrl,
        ?string $apiKey = null,
        float $timeout = self::DEFAULT_TIMEOUT,
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?ClientInterface $httpClient = null,
    ) {
        $configuration = new Configuration();
        $configuration->setHost(rtrim($baseUrl, '/'));

        $resolvedKey = $apiKey ?? (getenv(self::API_KEY_ENV_VAR) ?: null);
        if (null !== $resolvedKey && '' !== $resolvedKey) {
            $configuration->setAccessToken($resolvedKey);
        }

        $client = $httpClient ?? self::buildHttpClient($timeout, $maxRetries);

        $this->objectsApi = new ObjectsApi($client, $configuration);
        $this->healthApi = new HealthApi($client, $configuration);
        $this->auditLogApi = new AuditLogApi($client, $configuration);
    }

    /** Answers whether the server process is up. Needs no credential. */
    public function health(): Health
    {
        return $this->call(fn () => $this->healthApi->health());
    }

    /**
     * Stores an already-sealed value under a new object id. Requires a credential.
     *
     * @param string        $id     The new object's id. Must match hush-hush's id pattern
     *                              (lowercase alphanumeric, `-`/`_`).
     * @param string        $value  The already-sealed (encrypted) value, as raw bytes. This SDK
     *                              never encrypts or decrypts anything.
     * @param null|string[] $usedBy Consumers (repos or hosts) recorded as depending on
     *                              this object. Set once, at creation; unaffected by later value updates.
     * @param null|string   $caller Recorded in the audit log as the calling program's
     *                              self-reported identity. Not verified by the server.
     *
     * @throws ApiException If the server responds with anything other than 201
     *                      (e.g. 409 if the id already exists).
     */
    public function createObject(
        string $id,
        string $value,
        ?array $usedBy = null,
        ?string $caller = null,
    ): ObjectMetadata {
        $request = new CreateObjectRequest([
            'id' => $id,
            'value' => base64_encode($value),
            'used_by' => $usedBy,
        ]);

        return $this->call(fn () => self::expectType(
            $this->objectsApi->createObject($request, $caller),
            ObjectMetadata::class,
        ));
    }

    /**
     * Fetches an object's sealed ciphertext exactly as stored — this SDK never
     * decrypts it, the same as the server. Needs no credential.
     *
     * @param string      $id     the object's id
     * @param null|string $caller recorded in the audit log as the calling program's
     *                            self-reported identity
     *
     * @throws ApiException If the server responds with anything other than 200
     *                      (e.g. 404 if no object exists under that id).
     */
    public function getObject(string $id, ?string $caller = null): string
    {
        return $this->call(function () use ($id, $caller): string {
            $file = self::expectType($this->objectsApi->getObject($id, $caller), \SplFileObject::class);
            $contents = file_get_contents($file->getPathname());
            unlink($file->getPathname());

            return false === $contents ? '' : $contents;
        });
    }

    /**
     * Replaces the stored ciphertext for an existing object. The object's id and
     * used-by metadata are unchanged. Requires a credential.
     *
     * @param string      $id     the existing object's id
     * @param string      $value  the new already-sealed (encrypted) value, as raw bytes
     * @param null|string $caller recorded in the audit log as the calling program's
     *                            self-reported identity
     *
     * @throws ApiException If the server responds with anything other than 200
     *                      (e.g. 401 or 404).
     */
    public function updateObject(string $id, string $value, ?string $caller = null): ObjectMetadata
    {
        $request = new UpdateObjectRequest(['value' => base64_encode($value)]);

        return $this->call(fn () => self::expectType(
            $this->objectsApi->updateObject($id, $request, $caller),
            ObjectMetadata::class,
        ));
    }

    /**
     * Permanently removes an object. A subsequent fetch by this id returns 404.
     * Requires a credential.
     *
     * @param string      $id     the object's id
     * @param null|string $caller recorded in the audit log as the calling program's
     *                            self-reported identity
     *
     * @throws ApiException If the server responds with anything other than 204
     *                      (e.g. 401 or 404).
     */
    public function deleteObject(string $id, ?string $caller = null): void
    {
        $this->call(function () use ($id, $caller): null {
            $this->objectsApi->deleteObject($id, $caller);

            return null;
        });
    }

    /**
     * Returns the recorded list of consumers for an object — the "what depends on
     * this" mapping set at creation. Needs no credential.
     *
     * @param string $id the object's id
     *
     * @throws ApiException If the server responds with anything other than 200
     *                      (e.g. 404).
     */
    public function getObjectUsedBy(string $id): UsedBy
    {
        return $this->call(fn () => self::expectType($this->objectsApi->getObjectUsedBy($id), UsedBy::class));
    }

    /**
     * Queries the audit log — every create, read, update, and delete call is
     * recorded here. Needs no credential. Filters combine with AND when more than
     * one is given.
     *
     * hush-hush's `/audit-log` endpoint has no pagination parameters, so this
     * always returns the full matching result set as a single array, never a page
     * plus a cursor.
     *
     * @param array{objectId?: string, caller?: string, from?: string, to?: string} $filter
     *     Optional filters. `objectId` restricts to entries for that object id;
     *     `caller` to entries recorded with that caller identity; `from`/`to`
     *     (ISO-8601 timestamps) bound the timestamp range.
     *
     * @return AuditLogEntry[]
     */
    public function queryAuditLog(array $filter = []): array
    {
        return $this->call(fn () => $this->auditLogApi->queryAuditLog(
            $filter['objectId'] ?? null,
            $filter['caller'] ?? null,
            isset($filter['from']) ? new \DateTime($filter['from']) : null,
            isset($filter['to']) ? new \DateTime($filter['to']) : null,
        ));
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    private function call(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (GeneratedApiException $e) {
            throw ApiException::fromGenerated($e);
        }
    }

    /**
     * Generated methods declare a return type spanning every documented status
     * code's model, since the generator has no way to know a 2xx-only shape at
     * codegen time — a spec-conforming server only ever produces the success
     * type here, since every non-2xx status routes through {@see GeneratedApiException}
     * instead (Guzzle's default `http_errors` behavior). This just makes that
     * guarantee explicit for the type checker and callers alike.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function expectType(mixed $value, string $class): object
    {
        if (!$value instanceof $class) {
            $actual = get_debug_type($value);

            throw new \UnexpectedValueException("hush-hush: expected {$class}, got {$actual}");
        }

        return $value;
    }

    private static function buildHttpClient(float $timeout, int $maxRetries): GuzzleClient
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            RetryPolicy::decider($maxRetries),
            RetryPolicy::delay(),
        ));

        return new GuzzleClient([
            'handler' => $handlerStack,
            'timeout' => $timeout,
        ]);
    }
}

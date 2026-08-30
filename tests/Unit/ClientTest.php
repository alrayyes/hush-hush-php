<?php

declare(strict_types=1);

namespace HushHush\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use HushHush\Client;
use HushHush\Exception\ApiException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
final class ClientTest extends TestCase
{
    private const ENV_VAR = 'HUSH_HUSH_API_KEY';

    protected function tearDown(): void
    {
        putenv(self::ENV_VAR);
    }

    #[Test]
    public function usesApiKeyFromEnvironmentWhenNoCredentialGiven(): void
    {
        putenv(self::ENV_VAR . '=env-token');
        $history = [];
        $client = self::mockedClient([new Response(201, [], '{"id":"x"}')], $history);

        $client->createObject('x', 'sealed');

        self::assertSame('Bearer env-token', $history[0]['request']->getHeaderLine('Authorization'));
    }

    #[Test]
    public function explicitCredentialOverridesEnvironment(): void
    {
        putenv(self::ENV_VAR . '=env-token');
        $history = [];
        $client = self::mockedClient([new Response(201, [], '{"id":"x"}')], $history, 'explicit-token');

        $client->createObject('x', 'sealed');

        self::assertSame('Bearer explicit-token', $history[0]['request']->getHeaderLine('Authorization'));
    }

    #[Test]
    public function sendsTypedCreateRequestAndReturnsTypedResponse(): void
    {
        $history = [];
        $client = self::mockedClient(
            [new Response(201, [], '{"id":"my-object","used_by":["repo/a"]}')],
            $history,
            'token',
        );

        $result = $client->createObject('my-object', 'sealed-bytes', ['repo/a']);

        self::assertSame('my-object', $result->getId());
        self::assertSame(['repo/a'], $result->getUsedBy());
        self::assertSame('POST', $history[0]['request']->getMethod());
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        self::assertSame(base64_encode('sealed-bytes'), $body['value']);
    }

    #[Test]
    public function getReturnsRawSealedBytesNotJsonDecoded(): void
    {
        $client = self::mockedClient([
            new Response(200, ['Content-Type' => 'application/octet-stream'], "\xDE\xAD\xBE\xEF"),
        ]);

        $result = $client->getObject('my-object');

        self::assertSame("\xDE\xAD\xBE\xEF", $result);
    }

    #[Test]
    public function readOnlyCallSucceedsWithoutAnyCredential(): void
    {
        $history = [];
        $client = self::mockedClient(
            [new Response(200, ['Content-Type' => 'application/octet-stream'], 'bytes')],
            $history,
        );

        $client->getObject('my-object');

        self::assertFalse($history[0]['request']->hasHeader('Authorization'));
    }

    #[Test]
    public function attachesXCallerPerCallNotClientWide(): void
    {
        $history = [];
        $client = self::mockedClient([
            new Response(200, ['Content-Type' => 'application/octet-stream'], 'a'),
            new Response(200, ['Content-Type' => 'application/octet-stream'], 'b'),
        ], $history);

        $client->getObject('my-object', 'repo/a');
        $client->getObject('my-object');

        self::assertSame('repo/a', $history[0]['request']->getHeaderLine('X-Caller'));
        self::assertFalse($history[1]['request']->hasHeader('X-Caller'));
    }

    #[Test]
    public function repliesHealthAsUp(): void
    {
        $client = self::mockedClient([new Response(200, [], '{"status":"ok"}')]);

        self::assertSame('ok', $client->health()->getStatus());
    }

    #[Test]
    public function replacesAnObjectsValue(): void
    {
        $history = [];
        $client = self::mockedClient([new Response(200, [], '{"id":"my-object"}')], $history, 'token');

        $result = $client->updateObject('my-object', 'new-sealed');

        self::assertSame('my-object', $result->getId());
        self::assertSame('PUT', $history[0]['request']->getMethod());
    }

    #[Test]
    public function deletesAnObject(): void
    {
        $history = [];
        $client = self::mockedClient([new Response(204)], $history, 'token');

        $client->deleteObject('my-object');

        self::assertSame('DELETE', $history[0]['request']->getMethod());
    }

    #[Test]
    public function queriesTheUsedByEndpoint(): void
    {
        $history = [];
        $client = self::mockedClient([new Response(200, [], '{"used_by":["repo/a"]}')], $history);

        $result = $client->getObjectUsedBy('my-object');

        self::assertSame(['repo/a'], $result->getUsedBy());
        self::assertStringEndsWith('/objects/my-object/used-by', (string) $history[0]['request']->getUri());
    }

    #[Test]
    public function sendsAuditLogFiltersAsQueryParametersAndReturnsMatchingEntries(): void
    {
        $history = [];
        $client = self::mockedClient([
            new Response(200, [], '[{"object_id":"my-object","action":"read","timestamp":"2026-01-01T00:00:00Z"}]'),
        ], $history);

        $result = $client->queryAuditLog(['objectId' => 'my-object', 'caller' => 'repo/a']);

        self::assertCount(1, $result);
        self::assertSame('my-object', $result[0]->getObjectId());
        $query = $history[0]['request']->getUri()->getQuery();
        self::assertStringContainsString('object_id=my-object', $query);
        self::assertStringContainsString('caller=repo%2Fa', $query);
    }

    #[Test]
    public function raisesTypedErrorWithStatusAndParsedBodyForNonRetryable4xx(): void
    {
        $client = self::mockedClient([
            new Response(404, [], '{"error":"unknown object"}'),
        ]);

        try {
            $client->getObject('missing');
            self::fail('expected an ApiException');
        } catch (ApiException $e) {
            self::assertSame(404, $e->status());
            self::assertSame('unknown object', $e->apiMessage());
        }
    }

    /**
     * @param Response[] $responses
     * @param RequestInterface[] $history
     */
    private static function mockedClient(array $responses, array &$history = [], ?string $apiKey = null): Client
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $guzzle = new GuzzleClient(['handler' => $stack]);

        return new Client('https://hush-hush.test', $apiKey, httpClient: $guzzle);
    }
}

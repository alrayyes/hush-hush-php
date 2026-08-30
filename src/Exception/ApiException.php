<?php

declare(strict_types=1);

namespace HushHush\Exception;

use HushHush\Generated\ApiException as GeneratedApiException;

/**
 * Thrown for any non-2xx response from hush-hush.
 *
 * `requestId` is populated only when the response carries a documented
 * request-ID header; hush-hush's spec doesn't currently document one, so
 * this is usually `null`. Kept as a property rather than omitted so a
 * future spec addition doesn't change this type's shape.
 */
final class ApiException extends HushHushException
{
    private readonly int $status;
    private readonly ?string $requestId;

    /** The parsed `error` field from hush-hush's error body, if present. */
    private readonly ?string $apiMessage;

    /** The raw, unparsed response body, for a caller that needs more than `apiMessage`. */
    private readonly string $body;

    public function __construct(int $status, string $body, ?string $requestId)
    {
        $apiMessage = self::parseMessage($body);
        parent::__construct(
            null !== $apiMessage ? "hush-hush: {$status}: {$apiMessage}" : "hush-hush: unexpected status {$status}",
            $status,
        );
        $this->status = $status;
        $this->requestId = $requestId;
        $this->apiMessage = $apiMessage;
        $this->body = $body;
    }

    public static function fromGenerated(GeneratedApiException $exception): self
    {
        $status = $exception->getCode();
        $body = $exception->getResponseBody();
        $bodyString = \is_string($body) ? $body : (string) json_encode($body);

        $requestId = null;
        $headers = $exception->getResponseHeaders() ?? [];
        foreach ($headers as $name => $values) {
            if (0 === strcasecmp((string) $name, 'X-Request-Id')) {
                $requestId = $values[0] ?? null;

                break;
            }
        }

        return new self($status, $bodyString, $requestId);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function apiMessage(): ?string
    {
        return $this->apiMessage;
    }

    public function body(): string
    {
        return $this->body;
    }

    private static function parseMessage(string $body): ?string
    {
        $decoded = json_decode($body, true);
        if (\is_array($decoded) && isset($decoded['error']) && \is_string($decoded['error'])) {
            return $decoded['error'];
        }

        return null;
    }
}

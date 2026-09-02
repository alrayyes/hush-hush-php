<?php

declare(strict_types=1);

namespace HushHush;

use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Retries a request only on network failure or an HTTP 5xx/429 response,
 * using exponential backoff with jitter, and honors a `Retry-After` response
 * header ahead of the computed backoff delay when present. Any other 4xx is
 * never retried — it won't succeed on a second attempt, and retrying only
 * delays the real error reaching the caller.
 */
final class RetryPolicy
{
    public static function decider(int $maxRetries): callable
    {
        return static function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Throwable $exception = null,
        ) use ($maxRetries): bool {
            if ($retries >= $maxRetries) {
                return false;
            }
            if ($exception instanceof ConnectException) {
                return true;
            }
            if (null !== $response) {
                $status = $response->getStatusCode();

                return $status >= 500 || 429 === $status;
            }

            return false;
        };
    }

    public static function delay(): callable
    {
        return self::delayMs(...);
    }

    public static function delayMs(int $retries, ?ResponseInterface $response = null): int
    {
        if (null !== $response) {
            $retryAfterMs = self::retryAfterMs($response);
            if (null !== $retryAfterMs) {
                return $retryAfterMs;
            }
        }

        $base = 100 * 2 ** ($retries - 1);

        return (int) ($base + random_int(0, (int) $base));
    }

    private static function retryAfterMs(ResponseInterface $response): ?int
    {
        $retryAfter = $response->getHeaderLine('Retry-After');
        if ('' === $retryAfter) {
            return null;
        }

        if (is_numeric($retryAfter)) {
            return max((int) ((float) $retryAfter * 1000), 0);
        }

        $when = strtotime($retryAfter);
        if (false === $when) {
            return null;
        }

        return max(($when - time()) * 1000, 0);
    }
}

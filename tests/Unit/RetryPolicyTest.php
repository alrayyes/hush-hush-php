<?php

declare(strict_types=1);

namespace HushHush\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HushHush\RetryPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class RetryPolicyTest extends TestCase
{
    #[Test]
    public function retriesATransientServerError(): void
    {
        $decider = RetryPolicy::decider(3);

        self::assertTrue($decider(0, self::request(), new Response(503)));
    }

    #[Test]
    public function retriesA429(): void
    {
        $decider = RetryPolicy::decider(3);

        self::assertTrue($decider(0, self::request(), new Response(429)));
    }

    #[Test]
    public function retriesANetworkFailure(): void
    {
        $decider = RetryPolicy::decider(3);
        $exception = new ConnectException('network down', self::request());

        self::assertTrue($decider(0, self::request(), null, $exception));
    }

    #[Test]
    public function doesNotRetryANonRetryable4xx(): void
    {
        $decider = RetryPolicy::decider(3);

        self::assertFalse($decider(0, self::request(), new Response(400)));
    }

    #[Test]
    public function stopsOnceMaxRetriesIsReached(): void
    {
        $decider = RetryPolicy::decider(3);

        self::assertFalse($decider(3, self::request(), new Response(503)));
    }

    #[Test]
    public function honorsRetryAfterInSecondsAheadOfComputedBackoff(): void
    {
        $delayMs = RetryPolicy::delayMs(1, new Response(429, ['Retry-After' => '5']));

        self::assertSame(5000, $delayMs);
    }

    #[Test]
    public function honorsRetryAfterAsAnHttpDate(): void
    {
        $future = gmdate('D, d M Y H:i:s \G\M\T', time() + 10);
        $delayMs = RetryPolicy::delayMs(1, new Response(503, ['Retry-After' => $future]));

        self::assertGreaterThan(8000, $delayMs);
        self::assertLessThanOrEqual(10000, $delayMs);
    }

    #[Test]
    public function computesExponentialBackoffWithJitterWhenNoRetryAfterIsPresent(): void
    {
        $delayMs = RetryPolicy::delayMs(1, new Response(503));

        self::assertGreaterThanOrEqual(100, $delayMs);
        self::assertLessThanOrEqual(200, $delayMs);
    }

    #[Test]
    public function backoffGrowsExponentiallyAcrossAttempts(): void
    {
        $first = RetryPolicy::delayMs(1);
        $third = RetryPolicy::delayMs(3);

        self::assertGreaterThan($first, $third);
    }

    private static function request(): Request
    {
        return new Request('GET', 'https://hush-hush.test/objects/my-object');
    }
}

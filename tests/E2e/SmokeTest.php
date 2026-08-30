<?php

declare(strict_types=1);

namespace HushHush\Tests\E2e;

use HushHush\Client;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A handful of real-network smoke tests against a real hush-hush instance.
 * Deliberately excluded from the default PR pipeline — see design.md's
 * testing layers: this is the thin, deliberately sparse layer, not a
 * substitute for the unit/contract tests that run on every PR. Skips itself
 * when the staging secrets aren't set, rather than failing — see
 * hush-hush-go's CLAUDE.md for why a workflow-level `if:` on secrets is the
 * wrong place to gate this instead.
 *
 * @internal
 */
final class SmokeTest extends TestCase
{
    #[Test]
    public function reachesARealHushHushInstanceAndRoundTripsAnObject(): void
    {
        $baseUrl = getenv('HUSH_HUSH_BASE_URL');
        $apiKey = getenv('HUSH_HUSH_API_KEY');
        if (false === $baseUrl || false === $apiKey) {
            self::markTestSkipped('HUSH_HUSH_BASE_URL/HUSH_HUSH_API_KEY are not set');
        }

        $client = new Client($baseUrl, $apiKey);
        self::assertSame('ok', $client->health()->getStatus());

        $id = 'hush-hush-php-e2e-' . bin2hex(random_bytes(4));
        $client->createObject($id, 'sealed-bytes');
        self::assertSame('sealed-bytes', $client->getObject($id));
        $client->deleteObject($id);
    }
}

<?php

declare(strict_types=1);

namespace HushHush\Tests\Contract;

use HushHush\Client;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the client against a Prism mock server generated from hush-hush's own
 * pinned spec (see ci.yml's `contract` job) — never a hand-rolled stub. This
 * proves the client's requests/responses conform to the spec; it says
 * nothing about whether the real server still matches that spec, which is
 * what tests/Pact is for. Prism's responses are the spec's own examples, not
 * an echo of what was sent, so these tests only assert on shape, not values.
 *
 * Run locally with:
 *   docker run -d -p 4010:4010 -v "$(pwd)/hush-hush/api:/spec:ro" \
 *     stoplight/prism:5 mock -h 0.0.0.0 -m false /spec/openapi.yaml
 *
 * @internal
 */
final class ClientTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $baseUrl = getenv('HUSH_HUSH_BASE_URL');
        if (false === $baseUrl) {
            self::markTestSkipped('HUSH_HUSH_BASE_URL must point at a running Prism mock');
        }

        $this->client = new Client($baseUrl, 'prism-does-not-check-this');
    }

    #[Test]
    public function reportsTheMockServerAsHealthy(): void
    {
        self::assertSame('ok', $this->client->health()->getStatus());
    }

    #[Test]
    public function createsFetchesUpdatesAndDeletesAnObject(): void
    {
        $created = $this->client->createObject(
            'contract-test-object',
            'sealed',
            ['contract-test'],
            'hush-hush-php-contract-test',
        );
        self::assertIsString($created->getId());

        $fetched = $this->client->getObject('contract-test-object');
        self::assertIsString($fetched);

        $updated = $this->client->updateObject('contract-test-object', 'new-sealed');
        self::assertIsString($updated->getId());

        $this->client->deleteObject('contract-test-object');
    }

    #[Test]
    public function queriesWhatDependsOnAnObject(): void
    {
        $usedBy = $this->client->getObjectUsedBy('contract-test-object');
        self::assertIsArray($usedBy->getUsedBy());
    }

    #[Test]
    public function queriesTheAuditLog(): void
    {
        $entries = $this->client->queryAuditLog(['objectId' => 'contract-test-object']);
        self::assertIsArray($entries);
    }
}

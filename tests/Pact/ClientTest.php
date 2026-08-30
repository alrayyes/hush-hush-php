<?php

declare(strict_types=1);

namespace HushHush\Tests\Pact;

use HushHush\Client;
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Matcher\Matcher;
use PhpPact\Consumer\Model\Body\Binary;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Records this SDK's real interactions as a Pact consumer contract. Provider
 * verification against hush-hush's actual server has to run in hush-hush's
 * own CI (see design.md's Risks — an external dependency this repo can't
 * wire up unilaterally); this module's job is only to keep producing an
 * up-to-date pact file for that to consume.
 *
 * @internal
 */
final class ClientTest extends TestCase
{
    #[Test]
    public function getsAnObject(): void
    {
        $sealedFile = tempnam(sys_get_temp_dir(), 'pact-sealed-');
        file_put_contents($sealedFile, 'sealed-bytes');

        $request = new ConsumerRequest();
        $request->setMethod('GET')->setPath('/objects/my-object');

        $response = new ProviderResponse();
        $response->setStatus(200)->setBody(new Binary($sealedFile, 'application/octet-stream'));

        $config = $this->config();
        $builder = new InteractionBuilder($config);
        $builder
            ->given('an object exists with id my-object')
            ->uponReceiving('a request to get an object')
            ->with($request)
            ->willRespondWith($response)
        ;

        $client = new Client((string) $config->getBaseUri());
        $got = $client->getObject('my-object');

        self::assertSame('sealed-bytes', $got);
        self::assertTrue($builder->verify());

        unlink($sealedFile);
    }

    #[Test]
    public function queriesTheAuditLog(): void
    {
        $matcher = new Matcher();

        $request = new ConsumerRequest();
        $request->setMethod('GET')->setPath('/audit-log');

        $response = new ProviderResponse();
        $response
            ->setStatus(200)
            ->addHeader('Content-Type', 'application/json')
            ->setBody($matcher->eachLike([
                'object_id' => $matcher->like('my-object'),
                'action' => $matcher->regex('read', 'create|read|update|delete'),
                'timestamp' => $matcher->dateTimeISO8601(),
            ]))
        ;

        $config = $this->config();
        $builder = new InteractionBuilder($config);
        $builder
            ->given('the audit log has at least one entry')
            ->uponReceiving('a request to query the audit log')
            ->with($request)
            ->willRespondWith($response)
        ;

        $client = new Client((string) $config->getBaseUri());
        $entries = $client->queryAuditLog();

        self::assertGreaterThanOrEqual(1, \count($entries));
        self::assertTrue($builder->verify());
    }

    private function config(): MockServerConfig
    {
        $config = new MockServerConfig();
        $config
            ->setConsumer('hush-hush-php')
            ->setProvider('hush-hush')
            ->setPactDir(__DIR__ . '/../../pact/pacts')
        ;

        return $config;
    }
}

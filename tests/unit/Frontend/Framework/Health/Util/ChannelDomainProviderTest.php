<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Health\Util;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomain;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainProvider;

/**
 * @internal
 */
#[CoversClass(ChannelDomainProvider::class)]
class ChannelDomainProviderTest extends TestCase
{
    private Connection&Stub $connection;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
    }

    public function testFetchChannelDomainsReturnsCollectionWithData(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['id' => 'domain-1', 'channel_id' => 'test-sales-channel-id-1', 'url' => 'http://localhost:8000', 'language_id' => 'language-1', 'currency_id' => 'currency-1'],
            ['id' => 'domain-2', 'channel_id' => 'test-sales-channel-id-2', 'url' => 'http://localhost:8001', 'language_id' => 'language-2', 'currency_id' => 'currency-2'],
        ]);

        $provider = $this->createProvider();

        $collection = $provider->fetchChannelDomains();
        static::assertCount(2, $collection);
        static::assertContainsOnlyInstancesOf(ChannelDomain::class, $collection);

        // the readiness checks build their lookup context from these, so the query has to select them
        $domain = $collection->get('test-sales-channel-id-1');
        static::assertInstanceOf(ChannelDomain::class, $domain);
        static::assertSame('domain-1', $domain->id);
        static::assertSame('language-1', $domain->languageId);
        static::assertSame('currency-1', $domain->currencyId);
    }

    public function testFetchChannelDomainsHandlesEmptyResults(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $provider = $this->createProvider();

        $collection = $provider->fetchChannelDomains();
        static::assertCount(0, $collection);
    }

    private function createProvider(): AbstractChannelDomainProvider
    {
        return new ChannelDomainProvider($this->connection);
    }
}

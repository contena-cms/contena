<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\SystemCheck\Util;

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
            ['channel_id' => 'test-channel-id-1', 'url' => 'http://localhost:8000'],
            ['channel_id' => 'test-channel-id-2', 'url' => 'http://localhost:8001'],
        ]);

        $collection = $this->createProvider()->fetchChannelDomains();

        static::assertCount(2, $collection);
        static::assertContainsOnlyInstancesOf(ChannelDomain::class, $collection);
    }

    public function testFetchChannelDomainsHandlesEmptyResults(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        static::assertCount(0, $this->createProvider()->fetchChannelDomains());
    }

    private function createProvider(): AbstractChannelDomainProvider
    {
        return new ChannelDomainProvider($this->connection);
    }
}

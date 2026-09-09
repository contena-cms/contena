<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\InstallationId\Fingerprint;

use Contena\Core\Framework\App\InstallationId\Fingerprint\ChannelDomainUrls;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ChannelDomainUrls::class)]
class ChannelDomainUrlsTest extends TestCase
{
    public function testIdentifier(): void
    {
        $fingerprint = new ChannelDomainUrls(static::createStub(Connection::class));

        static::assertSame('channel_domain_urls', $fingerprint->getIdentifier());
    }

    public function testScore(): void
    {
        $fingerprint = new ChannelDomainUrls(static::createStub(Connection::class));

        static::assertSame(25, $fingerprint->getScore());
    }

    public function testTakesChannelDomainUrlsHash(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($urls = ['foo', 'bar', 'baz']);

        $fingerprint = new ChannelDomainUrls($connection);

        static::assertSame(\hash('md5', implode('', $urls)), $fingerprint->getStamp());
    }
}

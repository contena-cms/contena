<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\McpAllowedHostsProvider;

/**
 * @internal
 */
#[CoversClass(McpAllowedHostsProvider::class)]
class McpAllowedHostsProviderTest extends TestCase
{
    public function testAlwaysAllowsLocalhostVariants(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([]);

        $provider = new McpAllowedHostsProvider($connection, 'http://localhost');

        static::assertSame(['localhost', '127.0.0.1', '[::1]'], $provider->getAllowedHosts());
    }

    public function testIncludesAppUrlAndChannelDomainHostsWithoutPort(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([
            'http://trunk.localhost:8088',
            'https://www.example.com',
            'https://zh.example.com/zh',
        ]);

        $provider = new McpAllowedHostsProvider($connection, 'http://trunk.localhost:8088');

        static::assertSame(
            ['localhost', '127.0.0.1', '[::1]', 'trunk.localhost', 'www.example.com', 'zh.example.com'],
            $provider->getAllowedHosts(),
        );
    }

    public function testLowercasesAndDeduplicatesHosts(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([
            'https://Www.Example.com',
            'https://www.example.com:8443',
        ]);

        $provider = new McpAllowedHostsProvider($connection, 'https://WWW.example.com');

        static::assertSame(['localhost', '127.0.0.1', '[::1]', 'www.example.com'], $provider->getAllowedHosts());
    }

    public function testSkipsDomainsWithoutParseableHost(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([
            'default.headless0',
            '',
            'https://valid.example.com',
        ]);

        $provider = new McpAllowedHostsProvider($connection, 'http://localhost');

        static::assertSame(['localhost', '127.0.0.1', '[::1]', 'valid.example.com'], $provider->getAllowedHosts());
    }
}

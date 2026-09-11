<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Loader;

use Contena\Core\Framework\App\Feature\AppFeature;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\App\Feature\TranslatedString;
use Contena\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Contena\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @internal
 */
#[CoversClass(AppMcpPrivilegeProvider::class)]
class AppMcpPrivilegeProviderTest extends TestCase
{
    public function testReturnsEmptyMapWhenNoFeatures(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([]);

        $provider = new AppMcpPrivilegeProvider($storage, new NullLogger());

        static::assertSame([], $provider->getAppToolPrivileges());
    }

    public function testMapsRequiredPrivilegesByPrefixedToolName(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([
            $this->feature('publish-content', ['blog:read', 'blog:update'], 'my-cms'),
            $this->feature('cms-status', ['system:read'], 'my-cms'),
        ]);

        $provider = new AppMcpPrivilegeProvider($storage, new NullLogger());

        static::assertSame(
            [
                'my-cms-publish-content' => ['blog:read', 'blog:update'],
                'my-cms-cms-status' => ['system:read'],
            ],
            $provider->getAppToolPrivileges(),
        );
    }

    public function testIncludesToolsWithoutRequiredPrivilegesAsAnEmptyList(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([
            $this->feature('no-priv', [], 'my-erp'),
            $this->feature('with-priv', ['entity:read'], 'my-erp'),
        ]);

        $provider = new AppMcpPrivilegeProvider($storage, new NullLogger());

        static::assertSame([
            'my-erp-no-priv' => [],
            'my-erp-with-priv' => ['entity:read'],
        ], $provider->getAppToolPrivileges());
    }

    public function testReturnsEmptyMapAndLogsErrorWhenStorageThrows(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willThrowException(new \RuntimeException('DB down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to load app MCP tool privileges', static::arrayHasKey('exception'));

        $provider = new AppMcpPrivilegeProvider($storage, $logger);

        static::assertSame([], $provider->getAppToolPrivileges());
    }

    public function testMapsAppToolsToTheirOwningAppGroup(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn([
            $this->feature('publish-content', [], 'my-cms'),
            $this->feature('read-metadata', [], 'my-cms'),
            $this->feature('do-thing', [], 'other-app'),
        ]);

        $provider = new AppMcpPrivilegeProvider($storage, new NullLogger());

        static::assertSame(
            [
                'my-cms-publish-content' => 'my-cms',
                'my-cms-read-metadata' => 'my-cms',
                'other-app-do-thing' => 'other-app',
            ],
            $provider->getAppToolGroups(),
        );
    }

    public function testReturnsEmptyGroupMapAndLogsErrorWhenStorageThrows(): void
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willThrowException(new \RuntimeException('DB down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Failed to load app MCP tool groups', static::arrayHasKey('exception'));

        $provider = new AppMcpPrivilegeProvider($storage, $logger);

        static::assertSame([], $provider->getAppToolGroups());
    }

    /**
     * @param list<string> $requiredPrivileges
     *
     * @return AppFeature<McpToolConfig>
     */
    private function feature(string $name, array $requiredPrivileges, string $appName): AppFeature
    {
        $config = new McpToolConfig($name, 'https://app.example.com/mcp/' . $name, $requiredPrivileges, null, new TranslatedString([]), new TranslatedString([]));

        return new AppFeature('0189aaaabbbbcccc0000000000000001', $appName, true, '0.0.0', true, new \DateTimeImmutable(), $config);
    }
}

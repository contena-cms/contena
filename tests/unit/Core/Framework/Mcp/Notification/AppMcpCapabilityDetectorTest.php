<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Feature\AppFeature;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\App\Feature\TranslatedString;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Contena\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AppMcpCapabilityDetector::class)]
class AppMcpCapabilityDetectorTest extends TestCase
{
    public function testDetectsPersistedCapabilitiesForApp(): void
    {
        $appId = Uuid::randomHex();
        $tool = new McpToolConfig('sync-orders', 'https://app.example.com/mcp/sync-orders', [], null, new TranslatedString([]), new TranslatedString([]));
        $prompt = new McpPromptConfig('order-context', 'https://app.example.com/mcp/order-context', new TranslatedString([]), new TranslatedString([]));

        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forApp')->willReturnMap([
            [$appId, McpToolConfig::class, [new AppFeature($appId, 'my-app', true, '1.0.0', true, new \DateTimeImmutable(), $tool)]],
            [$appId, McpPromptConfig::class, [new AppFeature($appId, 'my-app', true, '1.0.0', true, new \DateTimeImmutable(), $prompt)]],
            [$appId, McpResourceConfig::class, []],
        ]);

        $capabilities = (new AppMcpCapabilityDetector($storage))->persistedForApp($appId);

        static::assertEquals(new McpListChangedNotificationSet(tools: true, resources: false, prompts: true), $capabilities);
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\System\SystemConfig\CachedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;

/**
 * @internal
 */
#[CoversClass(CacheInvalidationSubscriber::class)]
class CacheInvalidationSubscriberTest extends TestCase
{
    public function testGlobalChangeInvalidatesObjectAndAllDependentHttpCaches(): void
    {
        $invalidator = $this->createMock(CacheInvalidator::class);
        $expects = $this->exactly(2);
        $invalidator->expects($expects)
            ->method('invalidate')
            ->willReturnCallback(static function (array $tags, bool $force = false) use ($expects): void {
                if ($expects->numberOfInvocations() === 1) {
                    static::assertSame([CachedSystemConfigLoader::CACHE_TAG], $tags);
                    static::assertTrue($force);

                    return;
                }

                static::assertSame(['system.config-'], $tags);
                static::assertFalse($force);
            });

        new CacheInvalidationSubscriber($invalidator)->invalidateConfigKey(
            new SystemConfigMultipleChangedEvent(['example.config.enabled' => true], null, false)
        );
    }

    public function testSilentChangeOnlyInvalidatesObjectCache(): void
    {
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->once())
            ->method('invalidate')
            ->with([CachedSystemConfigLoader::CACHE_TAG], true);

        new CacheInvalidationSubscriber($invalidator)->invalidateConfigKey(
            new SystemConfigMultipleChangedEvent(
                ['example.config.enabled' => true],
                null,
                true,
            )
        );
    }
}

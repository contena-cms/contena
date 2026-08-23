<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Api\Controller\CacheController;
use Contena\Core\Framework\Api\Event\InvalidateExpiredCacheRequestEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Test\Stub\EventDispatcher\AssertingEventDispatcher;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CacheController::class)]
class CacheControllerTest extends TestCase
{
    public function testClearCache(): void
    {
        $cacheClearerMock = $this->createMock(CacheClearer::class);
        $cacheClearerMock->expects($this->once())
            ->method('clear');

        $controller = new CacheController(
            $cacheClearerMock,
            static::createStub(CacheInvalidator::class),
            new NullAdapter(),
            static::createStub(EntityIndexerRegistry::class),
            new EventDispatcher()
        );

        $controller->clearCache();
    }

    public function testClearDelayedCache(): void
    {
        $cacheInvalidatorMock = $this->createMock(CacheInvalidator::class);
        $cacheInvalidatorMock->expects($this->once())
            ->method('invalidateExpired');

        $eventDispatcher = new AssertingEventDispatcher($this, [
            InvalidateExpiredCacheRequestEvent::class => 1,
        ]);

        $controller = new CacheController(
            static::createStub(CacheClearer::class),
            $cacheInvalidatorMock,
            new NullAdapter(),
            static::createStub(EntityIndexerRegistry::class),
            $eventDispatcher,
        );

        $controller->clearDelayedCache(new Request());
    }
}

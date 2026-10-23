<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ReinstallAppsStrategy::class)]
class ReinstallAppsStrategyTest extends TestCase
{
    public function testNameAndDescription(): void
    {
        $appRepository = new StaticEntityRepository([]);

        $strategy = new ReinstallAppsStrategy(
            $appRepository,
            static::createStub(AppManager::class),
            static::createStub(ShopIdProvider::class),
            new NullLogger()
        );

        static::assertSame(ReinstallAppsStrategy::STRATEGY_NAME, $strategy->getName());
        static::assertNotSame('', $strategy->getDescription());
    }

    public function testDeletesShopIdAndReregistersEveryApp(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $appManager = $this->createMock(AppManager::class);
        $calledApps = [];
        $appManager->expects($this->exactly(2))
            ->method('reregister')
            ->willReturnCallback(static function (AppEntity $app, Context $passedContext) use (&$calledApps, $context): void {
                $calledApps[] = $app->getName();
                self::assertSame($context, $passedContext);
            });

        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new ReinstallAppsStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider,
            new NullLogger()
        );

        $strategy->resolve($context);

        static::assertSame(['app-one', 'app-two'], $calledApps);
    }

    public function testContinuesWithRemainingAppsAndReportsFailuresTogether(): void
    {
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');
        $exception = new \RuntimeException('Could not reach app server');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('reregister')
            ->willReturnCallback(static function () use (&$calls, $exception): void {
                if (++$calls === 1) {
                    throw $exception;
                }
            });

        $logger = new TestHandler();

        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new ReinstallAppsStrategy(
            $appRepository,
            $appManager,
            $shopIdProvider,
            new Logger('test', [$logger])
        );

        $this->expectExceptionObject(AppException::reinstallAppsFailed(['app-one']));

        try {
            $strategy->resolve(Context::createDefaultContext());
        } finally {
            $records = $logger->getRecords();
            static::assertCount(1, $records);
            static::assertSame('Failed to re-register app after shop ID change.', $records[0]->message);
            static::assertSame('app-one', $records[0]->context['appName']);
            static::assertSame($exception, $records[0]->context['exception']);
        }
    }
}

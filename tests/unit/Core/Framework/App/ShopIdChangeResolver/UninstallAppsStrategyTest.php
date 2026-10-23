<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(UninstallAppsStrategy::class)]
class UninstallAppsStrategyTest extends TestCase
{
    public function testDeletesShopIdAndDeletesEveryAppLocally(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())->method('deleteShopId');

        $appManager = $this->createMock(AppManager::class);
        $deletedApps = [];
        $appManager->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(static function (AppEntity $app, Context $passedContext) use (&$deletedApps, $context): void {
                $deletedApps[] = $app->getName();
                self::assertSame($context, $passedContext);
            });

        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        $strategy = new UninstallAppsStrategy($appRepository, $shopIdProvider, $appManager);

        $strategy->resolve($context);

        static::assertSame(['app-one', 'app-two'], $deletedApps);
    }
}

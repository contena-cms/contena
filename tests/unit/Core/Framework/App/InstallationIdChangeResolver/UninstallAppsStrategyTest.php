<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\InstallationIdChangeResolver;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\InstallationIdChangeResolver\UninstallAppsStrategy;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Tests\Unit\Core\Framework\App\AppFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UninstallAppsStrategy::class)]
class UninstallAppsStrategyTest extends TestCase
{
    public function testDeletesInstallationIdAndDeletesEveryAppLocally(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $installationIdProvider = $this->createMock(InstallationIdProvider::class);
        $installationIdProvider->expects($this->once())->method('deleteInstallationId');

        $appManager = $this->createMock(AppManager::class);
        $deletedApps = [];
        $appManager->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(static function (AppEntity $app, Context $passedContext) use (&$deletedApps, $context): void {
                $deletedApps[] = $app->getName();
                self::assertSame($context, $passedContext);
            });

        $appRepository = StaticEntityRepository::of(AppCollection::class, [new AppCollection([$appOne, $appTwo])]);

        $strategy = new UninstallAppsStrategy($appRepository, $installationIdProvider, $appManager);

        $strategy->resolve($context);

        static::assertSame(['app-one', 'app-two'], $deletedApps);
    }
}

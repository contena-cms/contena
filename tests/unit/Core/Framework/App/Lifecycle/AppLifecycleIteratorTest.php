<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Contena\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Contena\Core\Framework\App\Lifecycle\AppLoader;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppLifecycleIterator::class)]
class AppLifecycleIteratorTest extends TestCase
{
    public function testInstallMissingApp(): void
    {
        $appLoader = static::createStub(AppLoader::class);
        $appLoader->method('load')->willReturn([
            'ValidManifestApp' => Manifest::createFromXmlFile(__DIR__ . '/_fixtures/appDirValidationTest/ValidManifestApp/manifest.xml'),
        ]);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new EntityCollection(), new EntityCollection()]);

        $lifecycle = new AppLifecycleIterator(
            $repository,
            $appLoader
        );

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->once())->method('install');

        $lifecycle->iterateOverApps(
            $appLifecycle,
            new AppInstallParameters(),
            Context::createCLIContext()
        );
    }

    public function testUpdate(): void
    {
        $existingApp = new PartialEntity();
        $existingApp->setUniqueIdentifier('ValidManifestApp');
        $existingApp->set('id', 'ValidManifestApp');
        $existingApp->set('name', 'ValidManifestApp');
        $existingApp->set('version', '0.0.0');
        $existingApp->set('aclRoleId', '1234');

        $appLoader = static::createStub(AppLoader::class);
        $appLoader->method('load')->willReturn([
            'ValidManifestApp' => Manifest::createFromXmlFile(__DIR__ . '/_fixtures/appDirValidationTest/ValidManifestApp/manifest.xml'),
        ]);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new EntityCollection([$existingApp]), new EntityCollection([$existingApp])]);

        $lifecycle = new AppLifecycleIterator(
            $repository,
            $appLoader
        );

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('install');
        $appLifecycle->expects($this->once())->method('update');

        $lifecycle->iterateOverApps(
            $appLifecycle,
            new AppInstallParameters(),
            Context::createCLIContext()
        );
    }

    public function testInstalledAppSkipped(): void
    {
        $existingApp = new PartialEntity();
        $existingApp->setUniqueIdentifier('ValidManifestApp');
        $existingApp->set('id', 'ValidManifestApp');
        $existingApp->set('name', 'ValidManifestApp');
        $existingApp->set('version', '1.0.0');
        $existingApp->set('aclRoleId', '1234');

        $appLoader = static::createStub(AppLoader::class);
        $appLoader->method('load')->willReturn([
            'ValidManifestApp' => Manifest::createFromXmlFile(__DIR__ . '/_fixtures/appDirValidationTest/ValidManifestApp/manifest.xml'),
        ]);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new EntityCollection([$existingApp]), new EntityCollection([$existingApp])]);

        $lifecycle = new AppLifecycleIterator(
            $repository,
            $appLoader
        );

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('install');
        $appLifecycle->expects($this->never())->method('update');

        $lifecycle->iterateOverApps(
            $appLifecycle,
            new AppInstallParameters(),
            Context::createCLIContext()
        );
    }

    public function testAppGetsRemovedWhenNotOnDisk(): void
    {
        $existingApp = new PartialEntity();
        $existingApp->setUniqueIdentifier('ValidManifestApp');
        $existingApp->set('id', 'ValidManifestApp');
        $existingApp->set('name', 'ValidManifestApp');
        $existingApp->set('version', '1.0.0');
        $existingApp->set('aclRoleId', '1234');

        $appLoader = static::createStub(AppLoader::class);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new EntityCollection([$existingApp]), new EntityCollection([$existingApp])]);

        $lifecycle = new AppLifecycleIterator(
            $repository,
            $appLoader
        );

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('install');
        $appLifecycle->expects($this->never())->method('update');
        $appLifecycle->expects($this->once())->method('uninstall');

        $lifecycle->iterateOverApps(
            $appLifecycle,
            new AppInstallParameters(),
            Context::createCLIContext()
        );
    }

    public function testAppWithPendingSecretIsKeptForRecoveryNotDeleted(): void
    {
        // An app left with a pending secret is mid-recovery: an ambiguous registration kept it so a later
        // installation can re-register against the secret the app may already hold. A refresh runs this
        // cleanup routinely, so it must not uninstall the app and destroy that secret.
        $existingApp = new PartialEntity();
        $existingApp->setUniqueIdentifier('PendingApp');
        $existingApp->set('id', 'PendingApp');
        $existingApp->set('name', 'PendingApp');
        $existingApp->set('version', '1.0.0');
        $existingApp->set('aclRoleId', '1234');
        $existingApp->set('unconfirmedAppSecrets', 'left-over-pending');

        // The app is not on disk, so without the pending-secret guard the cleanup would uninstall it.
        $appLoader = static::createStub(AppLoader::class);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new EntityCollection([$existingApp]), new EntityCollection([$existingApp])]);

        $lifecycle = new AppLifecycleIterator(
            $repository,
            $appLoader
        );

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('uninstall');

        $lifecycle->iterateOverApps(
            $appLifecycle,
            new AppInstallParameters(),
            Context::createCLIContext()
        );
    }

    public function testRefreshSpecificOneDoesNotDeleteOthers(): void
    {
        $existingApp = new PartialEntity();
        $existingApp->setUniqueIdentifier('ValidManifestApp');
        $existingApp->set('id', 'ValidManifestApp');
        $existingApp->set('name', 'ValidManifestApp');
        $existingApp->set('version', '1.0.0');
        $existingApp->set('aclRoleId', '1234');

        $appLoader = static::createStub(AppLoader::class);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new EntityCollection([$existingApp]), new EntityCollection([$existingApp])]);

        $lifecycle = new AppLifecycleIterator(
            $repository,
            $appLoader
        );

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('install');
        $appLifecycle->expects($this->never())->method('update');
        $appLifecycle->expects($this->never())->method('uninstall');

        $lifecycle->iterateOverApps(
            $appLifecycle,
            new AppInstallParameters(),
            Context::createCLIContext(),
            ['Foo']
        );
    }

    public function testInstallationException(): void
    {
        $appLoader = static::createStub(AppLoader::class);
        $appLoader->method('load')->willReturn([
            'ValidManifestApp' => Manifest::createFromXmlFile(__DIR__ . '/_fixtures/appDirValidationTest/ValidManifestApp/manifest.xml'),
        ]);

        /** @var StaticEntityRepository<AppCollection> */
        $repository = new StaticEntityRepository([new EntityCollection(), new EntityCollection()]);

        $lifecycle = new AppLifecycleIterator(
            $repository,
            $appLoader
        );

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->once())->method('install')->willThrowException(new \Exception('Test'));

        $fails = $lifecycle->iterateOverApps(
            $appLifecycle,
            new AppInstallParameters(),
            Context::createCLIContext()
        );

        static::assertNotCount(0, $fails);
        static::assertCount(1, $fails);
    }
}

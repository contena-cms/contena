<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\InstallationIdChangeResolver;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\InstallationIdChangeSuggestedException;
use Contena\Core\Framework\App\InstallationId\FingerprintComparisonResult;
use Contena\Core\Framework\App\InstallationId\InstallationId;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\InstallationIdChangeResolver\MoveInstallationPermanentlyStrategy;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Tests\Unit\Core\Framework\App\AppFixture;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @internal
 */
#[CoversClass(MoveInstallationPermanentlyStrategy::class)]
class MoveInstallationPermanentlyStrategyTest extends TestCase
{
    public function testNameAndDescription(): void
    {
        $appRepository = StaticEntityRepository::of(AppCollection::class, []);

        $strategy = new MoveInstallationPermanentlyStrategy(
            $appRepository,
            static::createStub(AppManager::class),
            static::createStub(InstallationIdProvider::class),
            new NullLogger()
        );

        static::assertSame(MoveInstallationPermanentlyStrategy::STRATEGY_NAME, $strategy->getName());
        static::assertNotEmpty($strategy->getDescription());
    }

    public function testNoResolutionNeededWhenInstallationIdIsNotSuggestedToChange(): void
    {
        $installationIdProvider = $this->createMock(InstallationIdProvider::class);
        $installationIdProvider->expects($this->once())->method('reset');
        $installationIdProvider->expects($this->once())->method('getInstallationId')->willReturn(InstallationId::create('installation-id'));
        $installationIdProvider->expects($this->never())->method('regenerateAndSetInstallationId');

        $appManager = $this->createMock(AppManager::class);
        $appManager->expects($this->never())->method('refreshRegistration');

        $appRepository = StaticEntityRepository::of(AppCollection::class, []);

        $strategy = new MoveInstallationPermanentlyStrategy(
            $appRepository,
            $appManager,
            $installationIdProvider,
            new NullLogger()
        );

        $strategy->resolve(Context::createDefaultContext());
    }

    public function testRefreshesRegistrationForEveryApp(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        $installationIdProvider = $this->createMock(InstallationIdProvider::class);
        $installationIdProvider->expects($this->once())
            ->method('getInstallationId')
            ->willThrowException(new InstallationIdChangeSuggestedException(InstallationId::create('installation-id'), new FingerprintComparisonResult([], [], 75)));
        $installationIdProvider->expects($this->once())
            ->method('regenerateAndSetInstallationId')
            ->with('installation-id');

        $appManager = $this->createMock(AppManager::class);
        $calledApps = [];
        $appManager->expects($this->exactly(2))
            ->method('refreshRegistration')
            ->willReturnCallback(static function (AppEntity $app, Context $passedContext) use (&$calledApps, $context): void {
                $calledApps[] = $app->getName();
                self::assertSame($context, $passedContext);
            });

        $appRepository = StaticEntityRepository::of(AppCollection::class, [new AppCollection([$appOne, $appTwo])]);

        $strategy = new MoveInstallationPermanentlyStrategy(
            $appRepository,
            $appManager,
            $installationIdProvider,
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

        $installationIdProvider = static::createStub(InstallationIdProvider::class);
        $installationIdProvider->method('getInstallationId')
            ->willThrowException(new InstallationIdChangeSuggestedException(InstallationId::create('installation-id'), new FingerprintComparisonResult([], [], 75)));

        $appManager = $this->createMock(AppManager::class);
        $calls = 0;
        $appManager->expects($this->exactly(2))
            ->method('refreshRegistration')
            ->willReturnCallback(static function () use (&$calls, $exception): void {
                if (++$calls === 1) {
                    throw $exception;
                }
            });

        $logger = new TestHandler();

        $appRepository = StaticEntityRepository::of(AppCollection::class, [new AppCollection([$appOne, $appTwo])]);

        $strategy = new MoveInstallationPermanentlyStrategy(
            $appRepository,
            $appManager,
            $installationIdProvider,
            new Logger('test', [$logger])
        );

        $this->expectExceptionObject(AppException::installationMoveFailed(['app-one']));

        try {
            $strategy->resolve(Context::createDefaultContext());
        } finally {
            $records = $logger->getRecords();
            static::assertCount(1, $records);
            static::assertSame('Failed to re-register app after installation ID change.', $records[0]->message);
            static::assertSame('app-one', $records[0]->context['appName']);
            static::assertSame($exception, $records[0]->context['exception']);
        }
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Contena\Core\Framework\Store\Services\FirstRunWizardService;
use Contena\Core\Service\LifecycleManager;
use Contena\Core\Service\ScheduledTask\InstallServicesTask;
use Contena\Core\Service\ScheduledTask\InstallServicesTaskHandler;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(InstallServicesTaskHandler::class)]
class InstallServicesTaskHandlerTest extends TestCase
{
    public function testServicesAreInstalledWhenTheFirstRunWizardIsNotPending(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->expects($this->once())->method('reconcile');

        $this->createHandler($manager, frwPending: false)->run();
    }

    public function testServicesAreNotInstalledWhileTheFirstRunWizardIsPending(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->expects($this->never())->method('reconcile');

        $this->createHandler($manager, frwPending: true)->run();
    }

    public function testTheTaskKeepsItsScheduleWhenTheFirstRunWizardIsNotPending(): void
    {
        $handler = $this->createHandler(static::createStub(LifecycleManager::class), frwPending: false);

        static::assertNull($handler->getNextExecutionTime(new InstallServicesTask(), new ScheduledTaskEntity()));
    }

    public function testTheTaskRetriesInFifteenMinutesWhileTheFirstRunWizardIsPending(): void
    {
        $handler = $this->createHandler(static::createStub(LifecycleManager::class), frwPending: true);
        $handler->setClock(new MockClock('2026-10-06 12:00:00'));

        static::assertEquals(
            new \DateTimeImmutable('2026-10-06 12:15:00'),
            $handler->getNextExecutionTime(new InstallServicesTask(), new ScheduledTaskEntity())
        );
    }

    private function createHandler(LifecycleManager $manager, bool $frwPending): InstallServicesTaskHandler
    {
        $firstRunWizardService = static::createStub(FirstRunWizardService::class);
        $firstRunWizardService->method('frwShouldRun')->willReturn($frwPending);

        return new InstallServicesTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $manager,
            $firstRunWizardService,
        );
    }
}

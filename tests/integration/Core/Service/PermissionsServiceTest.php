<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Service\Event\PermissionsGrantedEvent;
use Contena\Core\Service\Event\PermissionsRevokedEvent;
use Contena\Core\Service\Permission\PermissionsService;
use Contena\Core\Service\ServiceException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

/**
 * @internal
 */
class PermissionsServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PermissionsService $permissionsService;

    private SystemConfigService $systemConfigService;

    private TraceableEventDispatcher $eventDispatcher;

    private Context $context;

    protected function setUp(): void
    {
        $this->permissionsService = $this->getContainer()->get(PermissionsService::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->context = Context::createDefaultContext(new AdminApiSource('test-admin_user-id'));
    }

    protected function tearDown(): void
    {
        $this->systemConfigService->delete('core.services.permissionsConsent');
    }

    public function testGrantPermissionsIntegration(): void
    {
        $profilerNeedsToBeDisabledAgain = !self::getContainer()->get('profiler')->isEnabled();
        if ($profilerNeedsToBeDisabledAgain) {
            self::getContainer()->get('profiler')->enable();
        }

        $revision = '2025-06-13';

        $this->permissionsService->grant($revision, $this->context);

        $storedRevision = $this->systemConfigService->getString('core.services.permissionsConsent');
        static::assertNotSame('', $storedRevision);

        // Verify the stored data contains the expected revision
        $decodedData = json_decode($storedRevision, true);
        static::assertIsArray($decodedData);
        static::assertArrayHasKey('revision', $decodedData);

        $storedDate = new \DateTimeImmutable($decodedData['revision']);
        static::assertSame($revision, $storedDate->format('Y-m-d'));

        $calledListeners = $this->eventDispatcher->getCalledListeners();
        $permissionsGrantedEvents = array_filter($calledListeners, function ($listener) {
            return $listener['event'] === PermissionsGrantedEvent::class;
        });

        static::assertNotCount(0, $permissionsGrantedEvents, 'PermissionsGrantedEvent should have been dispatched');

        if ($profilerNeedsToBeDisabledAgain) {
            self::getContainer()->get('profiler')->disable();
        }
    }

    public function testRevokePermissionsIntegration(): void
    {
        $profilerNeedsToBeDisabledAgain = !self::getContainer()->get('profiler')->isEnabled();
        if ($profilerNeedsToBeDisabledAgain) {
            self::getContainer()->get('profiler')->enable();
        }

        $revision = '2025-06-13';
        $this->permissionsService->grant($revision, $this->context);

        // Verify permissions were granted
        $storedRevision = $this->systemConfigService->getString('core.services.permissionsConsent');
        static::assertNotSame('', $storedRevision);

        $this->permissionsService->revoke($this->context);

        $storedRevision = $this->systemConfigService->getString('core.services.permissionsConsent');
        static::assertSame('', $storedRevision);

        $calledListeners = $this->eventDispatcher->getCalledListeners();
        $permissionsRevokedEvents = array_filter($calledListeners, static function ($listener) {
            return $listener['event'] === PermissionsRevokedEvent::class;
        });

        static::assertNotCount(0, $permissionsRevokedEvents, 'PermissionsRevokedEvent should have been dispatched');

        if ($profilerNeedsToBeDisabledAgain) {
            self::getContainer()->get('profiler')->disable();
        }
    }

    public function testGrantPermissionsWithInvalidRevisionIntegration(): void
    {
        $invalidRevision = 'invalid-date';

        $this->expectExceptionObject(ServiceException::invalidPermissionsRevisionFormat($invalidRevision));

        try {
            $this->permissionsService->grant($invalidRevision, $this->context);
        } finally {
            $storedRevision = $this->systemConfigService->getString('core.services.permissionsConsent');
            static::assertSame('', $storedRevision);
        }
    }

    public function testMultipleGrantPermissionsCallsOverridesPrevious(): void
    {
        $firstRevision = '2025-06-13';
        $secondRevision = '2025-06-14';

        $this->permissionsService->grant($firstRevision, $this->context);

        $storedRevision = $this->systemConfigService->getString('core.services.permissionsConsent');
        $decodedData = json_decode($storedRevision, true);
        $storedDate = new \DateTimeImmutable($decodedData['revision']);
        static::assertSame($firstRevision, $storedDate->format('Y-m-d'));

        $this->permissionsService->grant($secondRevision, $this->context);

        $storedRevision = $this->systemConfigService->getString('core.services.permissionsConsent');
        $decodedData = json_decode($storedRevision, true);
        $storedDate = new \DateTimeImmutable($decodedData['revision']);
        static::assertSame($secondRevision, $storedDate->format('Y-m-d'));
    }

    public function testGrantPermissionsWithInvalidContextThrowsException(): void
    {
        $this->expectExceptionObject(ServiceException::invalidPermissionsContext());

        $this->permissionsService->grant('2025-06-13', Context::createDefaultContext());
    }

    public function testAreGrantedReturnsTrueAfterGrantingPermissions(): void
    {
        static::assertFalse($this->permissionsService->areGranted());
        $revision = '2025-06-13';
        $this->permissionsService->grant($revision, $this->context);

        static::assertTrue($this->permissionsService->areGranted());
    }

    public function testAreGrantedReturnsFalseAfterRevokingPermissions(): void
    {
        $revision = '2025-06-13';
        $this->permissionsService->grant($revision, $this->context);

        static::assertTrue($this->permissionsService->areGranted());
        $this->permissionsService->revoke($this->context);
        static::assertFalse($this->permissionsService->areGranted());
    }

    public function testAreGrantedReturnsFalseWhenNoPermissionsExist(): void
    {
        $this->systemConfigService->delete('core.services.permissionsConsent');
        static::assertFalse($this->permissionsService->areGranted());
    }

    public function testAreGrantedReturnsFalseWithCorruptedPermissionsData(): void
    {
        $this->systemConfigService->set('core.services.permissionsConsent', 'invalid-json-data');
        static::assertFalse($this->permissionsService->areGranted());
    }

    public function testAreGrantedReturnsFalseWithIncompletePermissionsData(): void
    {
        $incompleteData = json_encode([
            'identifier' => 'test-identifier',
        ]);
        $this->systemConfigService->set('core.services.permissionsConsent', $incompleteData);
        static::assertFalse($this->permissionsService->areGranted());
    }
}

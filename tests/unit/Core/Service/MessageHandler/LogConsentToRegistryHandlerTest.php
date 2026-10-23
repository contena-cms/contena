<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\Message\LogPermissionToRegistryMessage;
use Contena\Core\Service\MessageHandler\LogConsentToRegistryHandler;
use Contena\Core\Service\Permission\ConsentState;
use Contena\Core\Service\Permission\PermissionsConsent;
use Contena\Core\Service\ServiceRegistry\PermissionLogger;

/**
 * @internal
 */
#[CoversClass(LogConsentToRegistryHandler::class)]
class LogConsentToRegistryHandlerTest extends TestCase
{
    private PermissionLogger&MockObject $permissionLogger;

    private LogConsentToRegistryHandler $handler;

    protected function setUp(): void
    {
        $this->permissionLogger = $this->createMock(PermissionLogger::class);
        $this->handler = new LogConsentToRegistryHandler($this->permissionLogger);
    }

    public function testHandlerCallOffloadsToPermissionLogger(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-admin_user-id',
            grantedAt: new \DateTime('2025-06-13 12:00:00')
        );

        $message = new LogPermissionToRegistryMessage($consent, ConsentState::GRANTED);

        $this->permissionLogger
            ->expects($this->once())
            ->method('logSync')
            ->with($consent, ConsentState::GRANTED);

        $this->handler->__invoke($message);
    }
}

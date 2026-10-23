<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\LifecycleManager;
use Contena\Core\Service\Message\InstallServicesMessage;
use Contena\Core\Service\MessageHandler\InstallServicesHandler;

/**
 * @internal
 */
#[CoversClass(InstallServicesHandler::class)]
class InstallServicesHandlerTest extends TestCase
{
    public function testHandlerDelegatesToServiceLifecycle(): void
    {
        $lifecycleManager = $this->createMock(LifecycleManager::class);
        $lifecycleManager->expects($this->once())
            ->method('reconcile')
            ->with(static::isInstanceOf(Context::class));

        $handler = new InstallServicesHandler($lifecycleManager);
        $handler->__invoke(new InstallServicesMessage());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Service\Message\UpdateServiceMessage;
use Contena\Core\Service\MessageHandler\UpdateServiceHandler;
use Contena\Core\Service\ServiceLifecycle;

/**
 * @internal
 */
#[CoversClass(UpdateServiceHandler::class)]
class UpdateServiceHandlerTest extends TestCase
{
    public function testHandlerUpdatesThenReevaluatesInstalledServices(): void
    {
        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle->expects($this->once())->method('update')->with('MyCoolService');
        $serviceLifecycle->expects($this->once())->method('reevaluateInstalled');

        $handler = new UpdateServiceHandler($serviceLifecycle);
        $handler->__invoke(new UpdateServiceMessage('MyCoolService'));
    }
}

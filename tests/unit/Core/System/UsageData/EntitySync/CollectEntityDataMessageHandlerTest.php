<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\UsageData\EntitySync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\UsageData\EntitySync\CollectEntityDataMessage;
use Contena\Core\System\UsageData\EntitySync\CollectEntityDataMessageHandler;
use Contena\Core\System\UsageData\Services\EntityDispatchService;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(CollectEntityDataMessageHandler::class)]
class CollectEntityDataMessageHandlerTest extends TestCase
{
    public function testInvoke(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->once())
            ->method('dispatchIterateEntityMessages');

        $messageHandler = new CollectEntityDataMessageHandler($entityDispatchService);
        $messageHandler(new CollectEntityDataMessage());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\UsageData\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Contena\Core\System\UsageData\ScheduledTask\CollectEntityDataTaskHandler;
use Contena\Core\System\UsageData\Services\EntityDispatchService;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(CollectEntityDataTaskHandler::class)]
class CollectEntityDataTaskHandlerTest extends TestCase
{
    public function testItStartsCollectingData(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->once())
            ->method('dispatchCollectEntityDataMessage');

        /** @var StaticEntityRepository<ScheduledTaskCollection> */
        $repository = new StaticEntityRepository([], new ScheduledTaskDefinition());

        $taskHandler = new CollectEntityDataTaskHandler(
            $repository,
            static::createStub(LoggerInterface::class),
            $entityDispatchService
        );

        $taskHandler->run();
    }
}

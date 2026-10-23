<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Flow\Api\FlowActionDefinition;
use Contena\Core\Content\Flow\Dispatching\Action\AddUserTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\RemoveOrderTagAction;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(FlowActionCollector::class)]
class FlowActionCollectorTest extends TestCase
{
    public function testCollect(): void
    {
        $addUserTag = new AddUserTagAction(static::createStub(EntityRepository::class));
        $removeOrderTag = new RemoveOrderTagAction(static::createStub(EntityRepository::class));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $appFlowActionRepo = $this->createMock(EntityRepository::class);
        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(new EntityCollection([
                (new AppFlowActionEntity())->assign([
                    'id' => Uuid::randomHex(),
                    'name' => 'slack.app',
                    'requirements' => ['orderAware'],
                    'delayable' => false,
                ]),
            ]));

        $appFlowActionRepo->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $flowActionCollector = new FlowActionCollector(
            [$addUserTag, $removeOrderTag],
            $eventDispatcher,
            $appFlowActionRepo
        );

        $result = $flowActionCollector->collect(Context::createDefaultContext());

        $userRequirements = [];
        $userRequirements[] = 'userAware';

        $orderRequirements = [];
        $orderRequirements[] = 'orderAware';

        static::assertEquals(
            [
                AddUserTagAction::getName() => new FlowActionDefinition(
                    AddUserTagAction::getName(),
                    $userRequirements,
                    true
                ),
                RemoveOrderTagAction::getName() => new FlowActionDefinition(
                    RemoveOrderTagAction::getName(),
                    $orderRequirements,
                    true
                ),
                'slack.app' => new FlowActionDefinition(
                    'slack.app',
                    ['orderAware'],
                    false
                ),
            ],
            $result->getElements()
        );
    }
}

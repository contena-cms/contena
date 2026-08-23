<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Mcp\Resource\StateMachineResource;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Contena\Core\System\StateMachine\StateMachineCollection;
use Contena\Core\System\StateMachine\StateMachineEntity;

/**
 * @internal
 */
#[CoversClass(StateMachineResource::class)]
class StateMachineResourceTest extends TestCase
{
    public function testReturnsFormattedStateMachines(): void
    {
        $openState = new StateMachineStateEntity();
        $openState->setId(Uuid::randomHex());
        $openState->setTechnicalName('open');
        $openState->setName('Open');

        $doneState = new StateMachineStateEntity();
        $doneState->setId(Uuid::randomHex());
        $doneState->setTechnicalName('done');
        $doneState->setName('Done');

        $transition = new StateMachineTransitionEntity();
        $transition->setId(Uuid::randomHex());
        $transition->setActionName('complete');
        $transition->setFromStateMachineState($openState);
        $transition->setToStateMachineState($doneState);

        $machine = new StateMachineEntity();
        $machine->setId(Uuid::randomHex());
        $machine->setTechnicalName('content.state');
        $machine->setName('Content State');
        $machine->setStates(new StateMachineStateCollection([$openState, $doneState]));
        $machine->setTransitions(new StateMachineTransitionCollection([$transition]));

        $collection = new StateMachineCollection([$machine]);
        $context = Context::createDefaultContext();
        $searchResult = new EntitySearchResult(1, $collection, null, new Criteria(), $context);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($searchResult);

        $resource = new StateMachineResource($repository);
        $result = ($resource)();

        static::assertSame('contena://state-machines', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);

        $data = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data);
        static::assertSame('content.state', $data[0]['technicalName']);
        static::assertCount(2, $data[0]['states']);
        static::assertCount(1, $data[0]['transitions']);
        static::assertSame('complete', $data[0]['transitions'][0]['actionName']);
        static::assertSame('open', $data[0]['transitions'][0]['fromState']);
        static::assertSame('done', $data[0]['transitions'][0]['toState']);
    }
}

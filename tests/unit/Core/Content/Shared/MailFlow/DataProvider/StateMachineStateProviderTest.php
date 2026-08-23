<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Content\Shared\MailFlow\DataProvider\StateMachineStateProvider;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<StateMachineStateProvider>
 */
#[CoversClass(StateMachineStateProvider::class)]
class StateMachineStateProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): StateMachineStateProvider {
        return new StateMachineStateProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return StateMachineStateDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return ['stateMachine'];
    }
}

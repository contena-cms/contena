<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(EntityWriteEvent::class)]
class EntityWriteEventTest extends TestCase
{
    public function testGetters(): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);

        $registry = new StaticDefinitionInstanceRegistry(
            [new DateDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $command = new DeleteCommand(
            $registry->getByEntityName(DateDefinition::ENTITY_NAME),
            ['id' => $ids->getBytes('p1')],
            new EntityExistence(DateDefinition::ENTITY_NAME, ['id' => $ids->get('p1')], true, true, true, [])
        );

        $event = EntityWriteEvent::create($writeContext, [
            $command,
        ]);

        static::assertSame($writeContext, $event->getWriteContext());
        static::assertSame($context, $event->getContext());
        static::assertSame([$command], $event->getCommands());
    }

    public function testGetCommandsForEntity(): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);

        $registry = new StaticDefinitionInstanceRegistry(
            [new DateDefinition(), new MediaDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $dateDelete = new DeleteCommand(
            $registry->get(DateDefinition::class),
            ['id' => $ids->getBytes('p1')],
            new EntityExistence(DateDefinition::ENTITY_NAME, ['id' => $ids->getBytes('p1')], true, true, true, [])
        );

        $mediaDelete = new DeleteCommand(
            $registry->get(MediaDefinition::class),
            ['id' => $ids->getBytes('m1')],
            new EntityExistence('media', ['id' => $ids->getBytes('m1')], true, true, true, [])
        );

        $event = EntityWriteEvent::create($writeContext, [
            $dateDelete,
            $mediaDelete,
        ]);

        static::assertSame([$dateDelete, $mediaDelete], $event->getCommands());
        static::assertSame([$dateDelete], $event->getCommandsForEntity(DateDefinition::ENTITY_NAME));
        static::assertSame([$mediaDelete], $event->getCommandsForEntity('media'));

        static::assertSame([$ids->get('p1')], $event->getIds(DateDefinition::ENTITY_NAME));
        static::assertSame([$ids->get('m1')], $event->getIds('media'));
    }

    public function testCallbacksAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);

        $event = EntityWriteEvent::create($writeContext, []);

        $callbackFactory = static fn () => new class {
            public int $counter = 0;

            public function __invoke(): void
            {
                ++$this->counter;
            }
        };

        $callback1 = $callbackFactory();
        $callback2 = $callbackFactory();

        $event->addSuccess($callback1);
        $event->addSuccess($callback1);
        $event->addError($callback2);

        $event->success();

        static::assertSame(2, $callback1->counter);

        $event->error();
        static::assertSame(1, $callback2->counter);
    }
}

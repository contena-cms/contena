<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Stock\AvailableStockMirrorSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(AvailableStockMirrorSubscriber::class)]
class AvailableStockMirrorSubscriberTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function getDefinition(): BlogDefinition
    {
        new StaticDefinitionInstanceRegistry(
            [$definition = new BlogDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        return $definition;
    }

    public function testBeforeWriteOnlyReactsToLiveVersions(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId($this->ids->create('version'));

        $subscriber = new AvailableStockMirrorSubscriber();

        $definition = $this->getDefinition();

        $command = new UpdateCommand(
            $definition,
            ['stock' => 10],
            ['id' => $this->ids->getBytes('blog-1')],
            new EntityExistence(
                BlogDefinition::ENTITY_NAME,
                ['id' => $this->ids->get('blog-1')],
                true,
                false,
                false,
                []
            ),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->__invoke($event);

        static::assertFalse($command->hasField('available_stock'));
    }

    public function testThatDeleteCommandIsIgnored(): void
    {
        $context = Context::createDefaultContext();

        $subscriber = new AvailableStockMirrorSubscriber();

        $definition = $this->getDefinition();

        $command = new DeleteCommand(
            $definition,
            ['id' => $this->ids->getBytes('blog-1')],
            new EntityExistence(
                BlogDefinition::ENTITY_NAME,
                ['id' => $this->ids->get('blog-1')],
                true,
                false,
                false,
                []
            ),
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->__invoke($event);

        static::assertFalse($command->hasField('available_stock'));
    }

    public function testThatAvailableStockIsCopiedOnInsert(): void
    {
        $context = Context::createDefaultContext();

        $subscriber = new AvailableStockMirrorSubscriber();

        $definition = $this->getDefinition();

        $command = new InsertCommand(
            $definition,
            ['stock' => 10],
            ['id' => $this->ids->getBytes('blog-1')],
            new EntityExistence(
                BlogDefinition::ENTITY_NAME,
                ['id' => $this->ids->get('blog-1')],
                false,
                false,
                false,
                []
            ),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->__invoke($event);

        static::assertTrue($command->hasField('available_stock'));
        static::assertSame(10, $command->getPayload()['available_stock']);
    }

    public function testThatAvailableStockIsCopiedOnUpdate(): void
    {
        $context = Context::createDefaultContext();

        $subscriber = new AvailableStockMirrorSubscriber();

        $definition = $this->getDefinition();

        $command = new UpdateCommand(
            $definition,
            ['stock' => 10],
            ['id' => $this->ids->getBytes('blog-1')],
            new EntityExistence(
                BlogDefinition::ENTITY_NAME,
                ['id' => $this->ids->get('blog-1')],
                true,
                false,
                false,
                []
            ),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->__invoke($event);

        static::assertTrue($command->hasField('available_stock'));
        static::assertSame(10, $command->getPayload()['available_stock']);
    }

    public function testThatAvailableStockIsNotCopiedOnUpdateIfNotInPayload(): void
    {
        $context = Context::createDefaultContext();

        $subscriber = new AvailableStockMirrorSubscriber();

        $definition = $this->getDefinition();

        $command = new UpdateCommand(
            $definition,
            [],
            ['id' => $this->ids->getBytes('blog-1')],
            new EntityExistence(
                BlogDefinition::ENTITY_NAME,
                ['id' => $this->ids->get('blog-1')],
                true,
                false,
                false,
                []
            ),
            '/0'
        );

        $event = EntityWriteEvent::create(
            WriteContext::createFromContext($context),
            [$command],
        );

        $subscriber->__invoke($event);

        static::assertFalse($command->hasField('available_stock'));
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\BlogStream\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter\BlogStreamFilterDefinition;
use Contena\Core\Content\BlogStream\BlogStreamDefinition;
use Contena\Core\Content\BlogStream\DataAbstractionLayer\BlogStreamFilterChangeSetSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(BlogStreamFilterChangeSetSubscriber::class)]
class BlogStreamFilterChangeSetSubscriberTest extends TestCase
{
    private BlogStreamFilterChangeSetSubscriber $subscriber;

    private IdsCollection $ids;

    private BlogStreamFilterDefinition $filterDefinition;

    private BlogStreamDefinition $streamDefinition;

    protected function setUp(): void
    {
        $this->subscriber = new BlogStreamFilterChangeSetSubscriber();
        $this->ids = new IdsCollection();

        new StaticDefinitionInstanceRegistry(
            [
                $this->filterDefinition = new BlogStreamFilterDefinition(),
                $this->streamDefinition = new BlogStreamDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testHasEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'triggerChangeSet'],
            BlogStreamFilterChangeSetSubscriber::getSubscribedEvents()
        );
    }

    public function testRequestsChangeSetForDeletedFilter(): void
    {
        $command = new DeleteCommand(
            $this->filterDefinition,
            ['id' => $this->ids->getBytes('filter')],
            static::createStub(EntityExistence::class),
        );

        $this->dispatch($command);

        static::assertTrue($command->requiresChangeSet());
    }

    public function testRequestsChangeSetForUpdateWithoutStreamIdInPayload(): void
    {
        $command = new UpdateCommand(
            $this->filterDefinition,
            ['value' => '5'],
            ['id' => $this->ids->getBytes('filter')],
            static::createStub(EntityExistence::class),
            '/0',
        );

        $this->dispatch($command);

        static::assertTrue($command->requiresChangeSet());
    }

    /**
     * A reassignment carries the new stream in its payload, but the stream losing the filter is only
     * recoverable from the previous row state, so this write needs a change set too.
     */
    public function testRequestsChangeSetForUpdateThatReassignsTheFilter(): void
    {
        $command = new UpdateCommand(
            $this->filterDefinition,
            ['blog_stream_id' => Uuid::fromHexToBytes($this->ids->get('new-stream')), 'value' => '5'],
            ['id' => $this->ids->getBytes('filter')],
            static::createStub(EntityExistence::class),
            '/0',
        );

        $this->dispatch($command);

        static::assertTrue($command->requiresChangeSet());
    }

    public function testIgnoresOtherEntities(): void
    {
        $command = new DeleteCommand(
            $this->streamDefinition,
            ['id' => Uuid::fromHexToBytes($this->ids->get('stream'))],
            static::createStub(EntityExistence::class),
        );

        $this->dispatch($command);

        static::assertFalse($command->requiresChangeSet());
    }

    private function dispatch(WriteCommand $command): void
    {
        $this->subscriber->triggerChangeSet(new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$command],
        ));
    }
}

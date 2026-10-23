<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Service\BlogReviewCountService;
use Contena\Core\System\User\Subscriber\BlogReviewSubscriber;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(BlogReviewSubscriber::class)]
class BlogReviewSubscriberTest extends TestCase
{
    private BlogReviewCountService&Stub $blogReviewCountService;

    private BlogReviewSubscriber $blogReviewSubscriber;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->blogReviewCountService = static::createStub(BlogReviewCountService::class);
        $this->blogReviewSubscriber = $this->createSubscriber();

        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [BlogReviewDefinition::class, BlogDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            'blog_review.written' => 'createReview',
            EntityDeleteEvent::class => 'detectChangeset',
            'blog_review.deleted' => 'onReviewDeleted',
        ], $this->blogReviewSubscriber->getSubscribedEvents());
    }

    public function testDetectChangesetWithReviewDeleteEvent(): void
    {
        $ids = new IdsCollection();

        $event = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertFalse($command->requiresChangeSet());
        }

        $this->blogReviewSubscriber->detectChangeset($event);

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertTrue($command->requiresChangeSet());
        }
    }

    public function testDetectChangesetWithInvalidCommands(): void
    {
        $ids = new IdsCollection();

        $event = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
            ]
        );

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertFalse($command->requiresChangeSet());
        }

        $this->blogReviewSubscriber->detectChangeset($event);

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertFalse($command->requiresChangeSet());
        }
    }

    public function testOnReviewDeleted(): void
    {
        $event = new EntityDeletedEvent(
            BlogReviewDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    'id',
                    ['id' => 'id'],
                    BlogReviewDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    new ChangeSet(['user_id' => 'user_id'], [], true)
                ),
                // should not trigger update as it has empty changeset
                new EntityWriteResult(
                    'id',
                    ['id' => 'id'],
                    BlogReviewDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    new ChangeSet([], [], true)
                ),
                // should not trigger update as it has wrong entity
                new EntityWriteResult(
                    'id',
                    ['id' => 'id'],
                    BlogDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    new ChangeSet(['user_id' => 'user_id'], [], true)
                ),
            ],
            Context::createDefaultContext(),
        );

        $blogReviewCountService = $this->createMock(BlogReviewCountService::class);
        $blogReviewCountService->expects($this->once())
            ->method('updateReviewCountForUser')
            ->with('user_id');

        $this->createSubscriber($blogReviewCountService)->onReviewDeleted($event);
    }

    public function testCreateReviewWithInvalidEntityName(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $blogReviewCountService = $this->createMock(BlogReviewCountService::class);
        $blogReviewCountService->expects($this->never())->method('updateReviewCount');
        $this->createSubscriber($blogReviewCountService)->createReview($this->getEntityWrittenEvent($ids, true));
    }

    public function testCreateReview(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $blogReviewCountService = $this->createMock(BlogReviewCountService::class);
        $blogReviewCountService->expects($this->once())->method('updateReviewCount')->with($ids);

        $this->createSubscriber($blogReviewCountService)->createReview($this->getEntityWrittenEvent($ids));
    }

    private function createSubscriber(?BlogReviewCountService $blogReviewCountService = null): BlogReviewSubscriber
    {
        return new BlogReviewSubscriber($blogReviewCountService ?? $this->blogReviewCountService);
    }

    /**
     * @param string[] $ids
     */
    private function getEntityWrittenEvent(array $ids = [], bool $invalidEntity = false): EntityWrittenEvent
    {
        $entity = $invalidEntity ? BlogDefinition::ENTITY_NAME : BlogReviewDefinition::ENTITY_NAME;

        $writtenResults = [];
        foreach ($ids as $id) {
            $writtenResult = static::createStub(EntityWriteResult::class);
            $writtenResult->method('getPrimaryKey')->willReturn($id);
            $writtenResults[] = $writtenResult;
        }

        return new EntityWrittenEvent($entity, $writtenResults, Context::createDefaultContext());
    }
}

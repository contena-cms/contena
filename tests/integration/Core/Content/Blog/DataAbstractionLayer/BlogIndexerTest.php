<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogCategoryDenormalizer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPriceUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\RatingAverageUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\SearchKeywordUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\StatesUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\VariantListingUpdater;
use Contena\Core\Content\Blog\Stock\StockStorage;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
class BlogIndexerTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private const AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION = 7085;
    private const UPDATE_IDS_CHUNK_SIZE_OF_INDEXER = 50;
    private const MAX_AMOUNT_OF_IDS_TO_BE_BELOW_CHUNK_SIZE = 49;
    private const AMOUNT_OF_IDS_JUST_ABOVE_CHUNK_SIZE = 51;

    private BlogIndexer $indexer;

    private Connection&Stub $connectionMock;

    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->connectionMock = static::createStub(Connection::class);
        $this->messageBus = self::getContainer()->get('messenger.default_bus');

        $this->indexer = new BlogIndexer(
            self::getContainer()->get(IteratorFactory::class),
            self::getContainer()->get('blog.repository'),
            $this->connectionMock,
            self::getContainer()->get(VariantListingUpdater::class),
            self::getContainer()->get(BlogCategoryDenormalizer::class),
            self::getContainer()->get(InheritanceUpdater::class),
            self::getContainer()->get(RatingAverageUpdater::class),
            self::getContainer()->get(SearchKeywordUpdater::class),
            self::getContainer()->get(ChildCountUpdater::class),
            self::getContainer()->get(ManyToManyIdFieldUpdater::class),
            self::getContainer()->get(StockStorage::class),
            self::getContainer()->get('event_dispatcher'),
            self::getContainer()->get(CheapestPriceUpdater::class),
            self::getContainer()->get(ContentStreamUpdater::class),
            $this->messageBus,
            Feature::isActive('v6.8.0.0') ? null : self::getContainer()->get(StatesUpdater::class),
            new NativeClock()
        );
    }

    public function testUpdateDoesNotReturnTooBigMessage(): void
    {
        $uuids = $this->getUuids(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION);
        $this->prepareGetChildrenIdsMethod(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION);
        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, $uuids);
        $writtenEvent = new EntityWrittenContainerEvent($context, $nestedEvents, []);
        $writtenEvent->setCloned(true);

        $message = $this->indexer->update($writtenEvent);
        static::assertNotNull($message);
        static::assertContains(BlogIndexer::CHILD_COUNT_UPDATER, $message->getSkip());
        $this->messageBus->dispatch($message);

        $this->runWorker();

        static::assertInstanceOf(TraceableMessageBus::class, $this->messageBus);
        $messages = $this->messageBus->getDispatchedMessages();

        $messagesDispatchedInBlogIndexer = array_filter($messages, static function ($message) {
            return $message['caller']['name'] === 'BlogIndexer.php';
        });

        $expectedAmountOfMessagesForParentsAndChildren = (int) ceil(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION / self::UPDATE_IDS_CHUNK_SIZE_OF_INDEXER);
        // Round down because one chunk is returned by the method and not sent in the BlogIndexer directly
        $expectedAmountOfMessagesForUpdatedBlogs = (int) floor(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION / self::UPDATE_IDS_CHUNK_SIZE_OF_INDEXER);
        $expectedAmountOfMessages = $expectedAmountOfMessagesForParentsAndChildren + $expectedAmountOfMessagesForUpdatedBlogs;
        static::assertCount($expectedAmountOfMessages, $messagesDispatchedInBlogIndexer);
    }

    public function testUpdateIncludesRelatedBlogsInSmallMessage(): void
    {
        $blogId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $this->connectionMock->method('fetchFirstColumn')->willReturn([$parentId], [$childId]);
        $context = Context::createDefaultContext();

        $message = $this->indexer->update(new EntityWrittenContainerEvent(
            $context,
            $this->prepareEvent($context, [$blogId]),
            []
        ));

        static::assertNotNull($message);
        $data = $message->getData();
        static::assertIsArray($data);
        static::assertEqualsCanonicalizing([$blogId, $parentId, $childId], array_values($data));
    }

    #[DataProvider('updateCases')]
    public function testUpdate(
        int $numberOfIds,
        int $expectedCountOfMessagesDispatchedInBlogIndexer
    ): void {
        $uuids = $this->getUuids($numberOfIds);
        $this->prepareGetChildrenIdsMethod($numberOfIds);
        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, $uuids);

        $message = $this->indexer->update(new EntityWrittenContainerEvent($context, $nestedEvents, []));
        static::assertNotNull($message);
        $this->messageBus->dispatch($message);

        $this->runWorker();

        static::assertInstanceOf(TraceableMessageBus::class, $this->messageBus);
        $messages = $this->messageBus->getDispatchedMessages();

        $messagesDispatchedInBlogIndexer = array_filter($messages, static function ($message) {
            return $message['caller']['name'] === 'BlogIndexer.php';
        });

        static::assertCount($expectedCountOfMessagesDispatchedInBlogIndexer, $messagesDispatchedInBlogIndexer);
    }

    public static function updateCases(): \Generator
    {
        yield 'Amount of Uuids so low, that the message bus is only used once for parents and children' => [
            'numberOfIds' => self::MAX_AMOUNT_OF_IDS_TO_BE_BELOW_CHUNK_SIZE,
            'expectedCountOfMessagesDispatchedInBlogIndexer' => 1,
        ];
        yield 'Amount of Uuids just so high, that the message bus is used once for to-be-updated blogs and two times for parents and children' => [
            'numberOfIds' => self::AMOUNT_OF_IDS_JUST_ABOVE_CHUNK_SIZE,
            'expectedCountOfMessagesDispatchedInBlogIndexer' => 3,
        ];
    }

    /**
     * @return list<string>
     */
    private function getUuids(int $numberOfIds): array
    {
        $uuids = [];
        for ($i = 0; $i < $numberOfIds; ++$i) {
            $uuids[] = Uuid::randomHex();
        }

        return $uuids;
    }

    private function prepareGetChildrenIdsMethod(int $numberOfUuids): void
    {
        $this->connectionMock->method('fetchFirstColumn')->willReturn($this->getUuids($numberOfUuids));
    }

    /**
     * @param list<string> $uuids
     */
    private function prepareEvent(Context $context, array $uuids): NestedEventCollection
    {
        $results = [];
        foreach ($uuids as $uuid) {
            $results[] = new EntityWriteResult(
                $uuid,
                [],
                BlogDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            );
        }

        return new NestedEventCollection([
            new EntityWrittenEvent(
                BlogDefinition::ENTITY_NAME,
                $results,
                $context
            ),
        ]);
    }
}

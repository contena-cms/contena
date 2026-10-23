<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPriceUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogCategoryDenormalizer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\RatingAverageUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\SearchKeywordUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\StatesUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\VariantListingUpdater;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Stock\AbstractStockStorage;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(BlogIndexer::class)]
class BlogIndexerTest extends TestCase
{
    public function testUpdateSkipChildCountUpdater(): void
    {
        $context = Context::createDefaultContext();
        $writtenEvent = $this->writtenEvent($context, Uuid::randomHex());
        $writtenEvent->setCloned(true);

        $indexer = $this->createIndexer(static::createStub(Connection::class), static::createStub(AbstractStockStorage::class));

        $message = $indexer->update($writtenEvent);
        static::assertNotNull($message);
        static::assertContains(BlogIndexer::CHILD_COUNT_UPDATER, $message->getSkip());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('inheritedFieldChangeProvider')]
    public function testUpdateRecalculatesVariantsThatInheritTheChangedField(array $payload): void
    {
        $parentId = Uuid::randomHex();
        $inheritingVariantId = Uuid::randomHex();
        $variantWithOwnValuesId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(
            [$inheritingVariantId, $variantWithOwnValuesId], // variants of the written blogs
            [], // parents of the written blogs
            [$inheritingVariantId], // variants inheriting `isCloseout` or `minPurchase`
        );

        $context = Context::createDefaultContext();
        $stockStorage = static::createMock(AbstractStockStorage::class);
        $stockStorage->expects($this->once())->method('index')->with([$parentId, $inheritingVariantId], $context);

        $this->createIndexer($connection, $stockStorage)->update($this->writtenEvent($context, $parentId, $payload));
    }

    public static function inheritedFieldChangeProvider(): \Generator
    {
        yield 'closeout switched off on the parent' => [['isCloseout' => false]];
        yield 'min. purchase raised on the parent' => [['minPurchase' => 2]];
    }

    public function testUpdateSkipsTheVariantLookupWhenNoWrittenBlogHasVariants(): void
    {
        $blogId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(
            [], // variants of the written blogs
            [], // parents of the written blogs
            [Uuid::randomHex()], // what the variant lookup would return
        );

        $context = Context::createDefaultContext();
        $stockStorage = static::createMock(AbstractStockStorage::class);
        $stockStorage->expects($this->once())->method('index')->with([$blogId], $context);

        $this->createIndexer($connection, $stockStorage)->update($this->writtenEvent($context, $blogId, ['isCloseout' => false]));
    }

    public function testUpdateSkipsTheVariantLookupForStockOnlyChanges(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(
            [$variantId], // variants of the written blogs
            [], // parents of the written blogs
            [$variantId], // what the variant lookup would return
        );

        $context = Context::createDefaultContext();
        $stockStorage = static::createMock(AbstractStockStorage::class);
        $stockStorage->expects($this->once())->method('index')->with([$parentId], $context);

        $this->createIndexer($connection, $stockStorage)->update($this->writtenEvent($context, $parentId, ['stock' => 0]));
    }

    private function createIndexer(Connection $connection, AbstractStockStorage $stockStorage): BlogIndexer
    {
        return new BlogIndexer(
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            $connection,
            static::createStub(VariantListingUpdater::class),
            static::createStub(BlogCategoryDenormalizer::class),
            static::createStub(InheritanceUpdater::class),
            static::createStub(RatingAverageUpdater::class),
            static::createStub(SearchKeywordUpdater::class),
            static::createStub(ChildCountUpdater::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $stockStorage,
            static::createStub(EventDispatcher::class),
            static::createStub(CheapestPriceUpdater::class),
            static::createStub(ContentStreamUpdater::class),
            static::createStub(MessageBusInterface::class),
            Feature::isActive('v6.8.0.0') ? null : static::createStub(StatesUpdater::class),
            new NativeClock()
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writtenEvent(Context $context, string $blogId, array $payload = []): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(
                BlogDefinition::ENTITY_NAME,
                [new EntityWriteResult($blogId, $payload, BlogDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE)],
                $context
            ),
        ]), []);
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\ChannelRepositoryIterator;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelRepositoryIterator::class)]
class ChannelRepositoryIteratorTest extends TestCase
{
    public function testKeysetFetchSeeksPastCursorAndTracksLastId(): void
    {
        $context = static::createStub(ChannelContext::class);
        $repository = $this->createMock(ChannelRepository::class);
        $repository->method('getDefinition')->willReturn($this->blogDefinition());

        $criteria = new Criteria();
        $criteria->setLimit(2);

        $blogA = $this->blog(5);
        $blogB = $this->blog(9);

        $calls = 0;
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use (&$calls, $context, $blogA, $blogB): EntitySearchResult {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $criteria->getTotalCountMode());
                static::assertSame(0, $criteria->getOffset());

                $ranges = array_values(array_filter(
                    $criteria->getFilters(),
                    static fn ($filter) => $filter instanceof RangeFilter && $filter->getField() === 'autoIncrement'
                ));

                if ($calls === 0) {
                    static::assertSame([], $ranges, 'The first keyset page must not seek.');
                    $collection = new ChannelBlogCollection([$blogA, $blogB]);
                } else {
                    static::assertCount(1, $ranges, 'Subsequent pages must seek past the last cursor.');
                    static::assertSame(9, $ranges[0]->getParameter(RangeFilter::GT));
                    $collection = new ChannelBlogCollection();
                }

                ++$calls;

                return new EntitySearchResult('blog', $collection->count(), $collection, null, $criteria, $context->getContext());
            });

        $iterator = new ChannelRepositoryIterator($repository, $context, $criteria);

        $first = $iterator->fetch();
        static::assertNotNull($first);
        static::assertCount(2, $first->getEntities());
        static::assertSame(9, $iterator->getOffset());

        // Empty page terminates iteration; cursor stays at the last seen value.
        static::assertNull($iterator->fetch());
        static::assertSame(9, $iterator->getOffset());
    }

    public function testKeysetResumesFromProvidedCursor(): void
    {
        $context = static::createStub(ChannelContext::class);
        $repository = $this->createMock(ChannelRepository::class);
        $repository->method('getDefinition')->willReturn($this->blogDefinition());

        $criteria = new Criteria();
        $criteria->setLimit(2);

        $repository->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use ($context): EntitySearchResult {
                $ranges = array_values(array_filter(
                    $criteria->getFilters(),
                    static fn ($filter) => $filter instanceof RangeFilter && $filter->getField() === 'autoIncrement'
                ));
                static::assertCount(1, $ranges, 'A resumed iterator must seek past the provided cursor.');
                static::assertSame(41, $ranges[0]->getParameter(RangeFilter::GT));

                $collection = new ChannelBlogCollection();

                return new EntitySearchResult('blog', 0, $collection, null, $criteria, $context->getContext());
            });

        $iterator = new ChannelRepositoryIterator($repository, $context, $criteria, 41);

        static::assertNull($iterator->fetch());
        static::assertSame(41, $iterator->getOffset());
    }

    public function testCriteriaWithOwnSortingKeepsOffsetPagination(): void
    {
        $context = static::createStub(ChannelContext::class);
        $repository = $this->createMock(ChannelRepository::class);
        $repository->method('getDefinition')->willReturn($this->blogDefinition());

        $criteria = new Criteria();
        $criteria->setLimit(2);
        $criteria->addSorting(new FieldSorting('name'));

        $repository->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) use ($context): EntitySearchResult {
                $ranges = array_filter(
                    $criteria->getFilters(),
                    static fn ($filter) => $filter instanceof RangeFilter && $filter->getField() === 'autoIncrement'
                );
                static::assertSame([], $ranges, 'A criteria with its own sorting must not switch to keyset.');

                $collection = new ChannelBlogCollection([$this->blog(1)]);

                return new EntitySearchResult('blog', 1, $collection, null, $criteria, $context->getContext());
            });

        $iterator = new ChannelRepositoryIterator($repository, $context, $criteria);

        static::assertNotNull($iterator->fetch());
        static::assertSame(2, $iterator->getOffset(), 'Offset mode resumes via a plain row offset.');
    }

    private function blogDefinition(): BlogDefinition
    {
        // BlogDefinition has an AutoIncrementField; hasAutoIncrement() is final and cannot be mocked.
        $registry = new StaticDefinitionInstanceRegistry(
            [CategoryDefinition::class, BlogCategoryDefinition::class, BlogDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->get(BlogDefinition::class);
        static::assertInstanceOf(BlogDefinition::class, $definition);

        return $definition;
    }

    private function blog(int $autoIncrement): ChannelBlogEntity
    {
        $blog = new ChannelBlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));
        $blog->setId(Uuid::randomHex());
        $blog->setAutoIncrement($autoIncrement);

        return $blog;
    }
}

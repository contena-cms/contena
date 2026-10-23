<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogStream;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter\BlogStreamFilterCollection;
use Contena\Core\Content\BlogStream\BlogStreamCollection;
use Contena\Core\Content\BlogStream\BlogStreamEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class BlogStreamFilterRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogStreamFilterCollection>
     */
    private EntityRepository $repository;

    private string $streamId;

    private Context $context;

    /**
     * @var EntityRepository<BlogStreamCollection>
     */
    private EntityRepository $blogStreamRepository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog_stream_filter.repository');
        $this->blogStreamRepository = static::getContainer()->get('blog_stream.repository');
        $this->streamId = Uuid::randomHex();
        $this->context = Context::createDefaultContext();
        $this->blogStreamRepository->upsert([['id' => $this->streamId, 'name' => 'Test stream']], $this->context);
    }

    public function testCreateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->create([
            ['id' => $id, 'type' => 'equals', 'value' => 'awesome', 'field' => 'blog.name', 'blogStreamId' => $this->streamId],
        ], $this->context);

        /** @var BlogStreamEntity $entity */
        $entity = $this->blogStreamRepository->search(new Criteria([$this->streamId]), $this->context)->getEntities()->get($this->streamId);
        static::assertSame([['type' => 'equals', 'field' => 'blog.name', 'value' => 'awesome']], $entity->getApiFilter());
    }

    public function testUpdateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->create([
            ['id' => $id, 'type' => 'equals', 'value' => 'new awesome', 'field' => 'blog.name', 'blogStreamId' => $this->streamId],
        ], $this->context);
        $this->repository->upsert([
            ['id' => $id, 'type' => 'range', 'field' => 'blog.weight', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100], 'blogStreamId' => $this->streamId],
        ], $this->context);

        /** @var BlogStreamEntity $entity */
        $entity = $this->blogStreamRepository->search(new Criteria([$this->streamId]), $this->context)->getEntities()->get($this->streamId);
        static::assertSame([['type' => 'range', 'field' => 'blog.weight', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100]]], $entity->getApiFilter());
    }

    public function testRangeEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->create([
            ['id' => $id, 'type' => 'range', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100], 'field' => 'blog.weight', 'blogStreamId' => $this->streamId],
        ], $this->context);

        /** @var BlogStreamEntity $entity */
        $entity = $this->blogStreamRepository->search(new Criteria([$this->streamId]), $this->context)->getEntities()->get($this->streamId);
        static::assertSame([['type' => 'range', 'field' => 'blog.weight', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100]]], $entity->getApiFilter());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ContentStream;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterCollection;
use Contena\Core\Content\ContentStream\ContentStreamCollection;
use Contena\Core\Content\ContentStream\ContentStreamEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ContentStreamFilterRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ContentStreamFilterCollection>
     */
    private EntityRepository $repository;

    private string $streamId;

    private Context $context;

    /**
     * @var EntityRepository<ContentStreamCollection>
     */
    private EntityRepository $contentStreamRepository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('content_stream_filter.repository');
        $this->contentStreamRepository = static::getContainer()->get('content_stream.repository');
        $this->streamId = Uuid::randomHex();
        $this->context = Context::createDefaultContext();
        $this->contentStreamRepository->upsert([['id' => $this->streamId, 'name' => 'Test stream']], $this->context);
    }

    public function testCreateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->create([
            ['id' => $id, 'type' => 'equals', 'value' => 'awesome', 'field' => 'blog.name', 'contentStreamId' => $this->streamId],
        ], $this->context);

        /** @var ContentStreamEntity $entity */
        $entity = $this->contentStreamRepository->search(new Criteria([$this->streamId]), $this->context)->getEntities()->get($this->streamId);
        static::assertSame([['type' => 'equals', 'field' => 'blog.name', 'value' => 'awesome']], $entity->getApiFilter());
    }

    public function testUpdateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->create([
            ['id' => $id, 'type' => 'equals', 'value' => 'new awesome', 'field' => 'blog.name', 'contentStreamId' => $this->streamId],
        ], $this->context);
        $this->repository->upsert([
            ['id' => $id, 'type' => 'range', 'field' => 'blog.weight', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100], 'contentStreamId' => $this->streamId],
        ], $this->context);

        /** @var ContentStreamEntity $entity */
        $entity = $this->contentStreamRepository->search(new Criteria([$this->streamId]), $this->context)->getEntities()->get($this->streamId);
        static::assertSame([['type' => 'range', 'field' => 'blog.weight', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100]]], $entity->getApiFilter());
    }

    public function testRangeEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->create([
            ['id' => $id, 'type' => 'range', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100], 'field' => 'blog.weight', 'contentStreamId' => $this->streamId],
        ], $this->context);

        /** @var ContentStreamEntity $entity */
        $entity = $this->contentStreamRepository->search(new Criteria([$this->streamId]), $this->context)->getEntities()->get($this->streamId);
        static::assertSame([['type' => 'range', 'field' => 'blog.weight', 'parameters' => [RangeFilter::GT => 0.5, RangeFilter::LT => 100]]], $entity->getApiFilter());
    }
}

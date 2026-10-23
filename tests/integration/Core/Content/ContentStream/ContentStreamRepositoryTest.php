<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ContentStream;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterCollection;
use Contena\Core\Content\ContentStream\ContentStreamCollection;
use Contena\Core\Content\ContentStream\ContentStreamEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ContentStreamRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ContentStreamCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('content_stream.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCreateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([['id' => $id, 'name' => 'Test stream']], $this->context);

        /** @var ContentStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertTrue($entity->isInvalid());
        static::assertNull($entity->getApiFilter());
        static::assertSame('Test stream', $entity->getName());
        static::assertSame($id, $entity->getId());
    }

    public function testUpdateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([['id' => $id, 'name' => 'Test stream']], $this->context);
        $this->repository->upsert([['id' => $id, 'name' => 'New Name']], $this->context);

        /** @var ContentStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertTrue($entity->isInvalid());
        static::assertNull($entity->getApiFilter());
        static::assertSame('New Name', $entity->getName());
        static::assertSame($id, $entity->getId());
    }

    public function testCreateEntityWithFilters(): void
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([['id' => $id, 'name' => 'Test stream', 'filters' => [['type' => 'contains', 'field' => 'name', 'value' => 'awesome']]]], $this->context);

        /** @var ContentStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertFalse($entity->isInvalid());
        static::assertNotNull($entity->getApiFilter());
        static::assertSame('Test stream', $entity->getName());
        static::assertSame($id, $entity->getId());
    }

    public function testCreateEntityWithMultiFilters(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Test stream',
            'filters' => [
                [
                    'type' => 'multi',
                    'operator' => 'OR',
                    'queries' => [
                        [
                            'type' => 'multi',
                            'operator' => 'AND',
                            'queries' => [
                                [
                                    'type' => 'multi',
                                    'operator' => 'OR',
                                    'queries' => [
                                        [
                                            'type' => 'equals',
                                            'field' => 'blog.name',
                                            'value' => 'awesome',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->upsert([$data], $this->context);

        /** @var ContentStreamEntity $entity */
        $entity = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertFalse($entity->isInvalid());
        static::assertNotNull($entity->getApiFilter());
        static::assertSame('Test stream', $entity->getName());
        static::assertSame($id, $entity->getId());
        static::assertEquals($data['filters'], $entity->getApiFilter());
    }

    public function testFetchFilters(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Test stream',
            'filters' => [
                [
                    'type' => 'multi',
                    'operator' => 'OR',
                    'queries' => [
                        [
                            'type' => 'multi',
                            'operator' => 'AND',
                            'queries' => [
                                [
                                    'type' => 'multi',
                                    'operator' => 'OR',
                                    'queries' => [
                                        [
                                            'type' => 'equals',
                                            'field' => 'blog.name',
                                            'value' => 'awesome',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->upsert([$data], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('filters');

        /** @var ContentStreamEntity $entity */
        $entity = $this->repository->search($criteria, $this->context)->getEntities()->get($id);

        /** @var ContentStreamFilterCollection $filters */
        $filters = $entity->getFilters();
        static::assertNotNull($filters);

        static::assertCount(4, $filters);
        static::assertCount(1, $filters->filterByProperty('field', 'blog.name')->getElements());
        static::assertCount(3, $filters->filterByProperty('type', 'multi')->getElements());
        static::assertCount(1, $filters->filterByProperty('type', 'multi')->filterByProperty('operator', 'AND')->getElements());
        static::assertCount(2, $filters->filterByProperty('type', 'multi')->filterByProperty('operator', 'OR')->getElements());
    }

    public function testFetchWithQueriesFilter(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Test stream',
            'filters' => [
                [
                    'type' => 'multi',
                    'operator' => 'OR',
                    'queries' => [
                        [
                            'type' => 'multi',
                            'operator' => 'AND',
                            'queries' => [
                                [
                                    'type' => 'multi',
                                    'operator' => 'OR',
                                    'queries' => [
                                        [
                                            'type' => 'equals',
                                            'field' => 'blog.name',
                                            'value' => 'awesome',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->upsert([$data], $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('content_stream.filters.queries.queries.queries.field', 'blog.name'));
        /** @var ContentStreamEntity $entity */
        $entity = $this->repository->search($criteria, $this->context)->getEntities()->get($id);

        static::assertNotNull($entity);
        static::assertSame('Test stream', $entity->getName());
    }
}

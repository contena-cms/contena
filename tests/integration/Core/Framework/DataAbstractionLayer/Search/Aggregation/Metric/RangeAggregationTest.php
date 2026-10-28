<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Test\Product\ProductBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\RangeResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class RangeAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('product.repository');
        $this->context = Context::createDefaultContext();
    }

    /**
     * @return iterable<string, array{rangesDefinition: mixed, rangesExpectedResult: mixed}>
     */
    public static function rangeAggregationDataProvider(): iterable
    {
        yield 'default ranges test cases' => [
            'rangesDefinition' => [
                [],
                ['key' => 'all'],
                ['key' => 'custom_key', 'from' => 0, 'to' => 15],
                ['to' => 10],
                ['from' => 11, 'to' => 20],
                ['from' => 20],
                ['from' => 10, 'to' => 10],
            ],
            'rangesExpectedResult' => [
                '*-*' => 8,
                'all' => 8,
                'custom_key' => 2,
                '*-10' => 1,
                '11-20' => 2,
                '20-*' => 4,
                '10-10' => 0,
            ],
        ];
    }

    /**
     * @param array<int, array<string, string|float>> $rangesDefinition
     * @param array<string, int> $rangesExpectedResult
     */
    #[DataProvider('rangeAggregationDataProvider')]
    public function testRangeAggregation(array $rangesDefinition, array $rangesExpectedResult): void
    {
        $ids = new IdsCollection();

        $data = [
            (new ProductBuilder($ids, 'a'))->price(5, 5)->build(),
            (new ProductBuilder($ids, 'b'))->price(10, 10)->build(),
            (new ProductBuilder($ids, 'c'))->price(15, 15)->build(),
            (new ProductBuilder($ids, 'd'))->price(15, 15)->build(),
            (new ProductBuilder($ids, 'e'))->price(25, 25)->build(),
            (new ProductBuilder($ids, 'f'))->price(26, 26)->build(),
            (new ProductBuilder($ids, 'g'))->price(30, 30)->build(),
            (new ProductBuilder($ids, 'h'))->price(100, 100)->build(),
        ];

        $this->repository->create($data, $this->context);

        $criteria = new Criteria([
            $ids->get('a'),
            $ids->get('b'),
            $ids->get('c'),
            $ids->get('d'),
            $ids->get('e'),
            $ids->get('f'),
            $ids->get('g'),
            $ids->get('h'),
        ]);
        $criteria->addAggregation(
            new RangeAggregation(
                'test-range-aggregation',
                'price.gross',
                $rangesDefinition
            )
        );

        $aggregationCollection = $this->repository->aggregate($criteria, $this->context);
        static::assertTrue($aggregationCollection->has('test-range-aggregation'));
        static::assertInstanceOf(RangeResult::class, $aggregationCollection->get('test-range-aggregation'));

        $rangesResult = $aggregationCollection->get('test-range-aggregation')->getRanges();

        static::assertCount(\count($rangesDefinition), $rangesResult);
        foreach ($rangesResult as $key => $count) {
            static::assertArrayHasKey($key, $rangesExpectedResult);
            static::assertSame($rangesExpectedResult[$key], $count);
        }
    }
}

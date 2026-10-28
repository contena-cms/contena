<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\SalesChannelApiSource;
use Contena\Core\Framework\Api\Context\ShopApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Exception\ApiProtectionException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\RuntimeFieldInCriteriaException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiCriteriaValidator::class)]
class ApiCriteriaValidatorTest extends TestCase
{
    private ApiCriteriaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ApiCriteriaValidator(
            new StaticDefinitionInstanceRegistry(
                [
                    SalesChannelProductDefinition::class,
                ],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class)
            )
        );
    }

    /**
     * @param class-string<\Exception>|null $expectedException
     */
    #[DataProvider('criteriaProvider')]
    public function testCriteria(Criteria $criteria, Context $context, ?string $expectedException): void
    {
        $e = null;

        try {
            $this->validator->validate('product', $criteria, $context);
        } catch (\Exception $e) {
        }

        if (!$expectedException) {
            static::assertNull($e);
        } else {
            static::assertInstanceOf($expectedException, $e);
        }
    }

    public function testAFieldTheApiDoesNotExposeIsABadRequest(): void
    {
        $exception = new ApiProtectionException('orderLineItems.id');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->expectExceptionObject($exception);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderLineItems.id', 1));
        $this->validator->validate('product', $criteria, new Context(new SalesChannelApiSource('test')));
    }

    public function testARuntimeFieldInTheCriteriaIsABadRequest(): void
    {
        $exception = new RuntimeFieldInCriteriaException('variation');
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->expectExceptionObject($exception);

        $criteria = (new Criteria())->addFilter(new ContainsFilter('variation', Uuid::randomHex()));
        $this->validator->validate('product', $criteria, new Context(new AdminApiSource('test')));
    }

    public static function criteriaProvider(): \Generator
    {
        $store = new Context(new ShopApiSource('test'));
        $sales = new Context(new SalesChannelApiSource('test'));
        $system = new Context(new SystemSource());
        $admin = new Context(new AdminApiSource('test'));

        yield 'Test order line item access in store api' => [
            (new Criteria())->addFilter(new EqualsFilter('orderLineItems.id', 1)),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Test order line item access in sales channel api' => [
            (new Criteria())->addFilter(new EqualsFilter('orderLineItems.id', 1)),
            $sales,
            ApiProtectionException::class,
        ];

        yield 'Allow price sorting in store api' => [
            (new Criteria())->addSorting(new FieldSorting('price')),
            $store,
            null,
        ];

        yield 'Allow cheapest price sorting in store api' => [
            (new Criteria())->addSorting(new FieldSorting('cheapestPrice')),
            $store,
            null,
        ];

        yield 'Allow price filtering in store api' => [
            (new Criteria())->addFilter(new RangeFilter('price', ['gt' => 10])),
            $store,
            null,
        ];

        yield 'Allow avg price aggregation in store api' => [
            (new Criteria())->addAggregation(new AvgAggregation('avg', 'price')),
            $store,
            null,
        ];

        yield 'Test order line item access in system scope' => [
            (new Criteria())->addFilter(new EqualsFilter('orderLineItems.id', 1)),
            $system,
            null,
        ];

        yield 'Test order line item access in admin api' => [
            (new Criteria())->addFilter(new EqualsFilter('orderLineItems.id', 1)),
            $admin,
            null,
        ];

        yield 'Test post-filter order line item access in store api' => [
            (new Criteria())->addPostFilter(new EqualsFilter('orderLineItems.id', 1)),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Test sorting order line item access in store api' => [
            (new Criteria())->addSorting(new FieldSorting('orderLineItems.id')),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Test query order line item access in store api' => [
            (new Criteria())->addQuery(new ScoreQuery(new EqualsFilter('orderLineItems.id', 1), 100)),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Test aggregation order line item access in store api' => [
            (new Criteria())->addAggregation(new TermsAggregation('agg', 'orderLineItems.id')),
            $store,
            ApiProtectionException::class,
        ];

        yield 'Test filter for runtime field' => [
            (new Criteria())->addFilter(new ContainsFilter('variation', Uuid::randomHex())),
            $admin,
            RuntimeFieldInCriteriaException::class,
        ];

        yield 'Test aggregate for runtime field' => [
            (new Criteria())->addAggregation(new TermsAggregation('agg', 'variation')),
            $admin,
            RuntimeFieldInCriteriaException::class,
        ];
    }
}

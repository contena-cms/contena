<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ContentStream\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ContentStream\Service\AbstractBlogStreamBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(AbstractBlogStreamBuilder::class)]
class AbstractBlogStreamBuilderTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testForwardsLegacyBuildFiltersCallToEnrichCriteria(): void
    {
        $filter = new EqualsFilter('active', true);
        $builder = new class($filter) extends AbstractBlogStreamBuilder {
            public function __construct(private readonly Filter $filter)
            {
            }

            public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
            {
                $criteria->addFilter($this->filter);
            }
        };

        static::assertSame([$filter], $builder->buildFilters('stream-id', Context::createDefaultContext()));
    }

    public function testLegacyBuildFiltersCallThrowsWhenV68IsActive(): void
    {
        $builder = new class extends AbstractBlogStreamBuilder {
            public function enrichCriteria(Criteria $criteria, string $id, Context $context): void
            {
            }
        };

        $this->expectException(FeatureException::class);

        $builder->buildFilters('stream-id', Context::createDefaultContext());
    }
}

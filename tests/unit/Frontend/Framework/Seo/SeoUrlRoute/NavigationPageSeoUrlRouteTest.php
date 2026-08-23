<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * @internal
 */
#[CoversClass(NavigationPageSeoUrlRoute::class)]
class NavigationPageSeoUrlRouteTest extends TestCase
{
    public function testPrepareCriteria(): void
    {
        $navigationPageSeoUrlRoute = new NavigationPageSeoUrlRoute(
            new CategoryDefinition(),
            static::createStub(CategoryBreadcrumbBuilder::class)
        );

        $channel = new ChannelEntity();

        $criteria = new Criteria();
        $navigationPageSeoUrlRoute->prepareCriteria($criteria, $channel);

        $filters = $criteria->getFilters();
        /** @var MultiFilter $multiFilter */
        $multiFilter = $filters[0];
        static::assertInstanceOf(MultiFilter::class, $multiFilter);
        static::assertSame('AND', $multiFilter->getOperator());
        $multiFilterQueries = $multiFilter->getQueries();

        static::assertCount(2, $multiFilterQueries);
        static::assertInstanceOf(EqualsFilter::class, $multiFilterQueries[0]);
        $this->assertEqualsFilter(
            $multiFilterQueries[0],
            'active',
            true
        );

        $notFilter = $multiFilterQueries[1];
        static::assertInstanceOf(NotFilter::class, $notFilter);
        static::assertSame('OR', $notFilter->getOperator());

        $notFilterQueries = $notFilter->getQueries();
        static::assertCount(1, $notFilterQueries);
        static::assertInstanceOf(EqualsFilter::class, $notFilterQueries[0]);
        $this->assertEqualsFilter(
            $notFilterQueries[0],
            'type',
            CategoryDefinition::TYPE_FOLDER
        );
    }

    private function assertEqualsFilter(
        EqualsFilter $equalsFilter,
        string $field,
        string|bool $value
    ): void {
        static::assertSame($field, $equalsFilter->getField());
        static::assertSame($value, $equalsFilter->getValue());
    }
}

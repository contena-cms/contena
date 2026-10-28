<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Country\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\SalesChannel\SalesChannelCountryDefinition;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(SalesChannelCountryDefinition::class)]
class SalesChannelCountryDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelCountryDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('country.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Currency\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\SalesChannel\SalesChannelCurrencyDefinition;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(SalesChannelCurrencyDefinition::class)]
class SalesChannelCurrencyDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelCurrencyDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('currency.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}

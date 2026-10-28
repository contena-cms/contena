<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Language\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Language\SalesChannel\SalesChannelLanguageDefinition;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(SalesChannelLanguageDefinition::class)]
class SalesChannelLanguageDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheSalesChannel(): void
    {
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        (new SalesChannelLanguageDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('language.salesChannels.id', $context->getSalesChannelId())], $criteria->getFilters());
    }
}

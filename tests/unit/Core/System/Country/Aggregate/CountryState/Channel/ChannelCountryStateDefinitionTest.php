<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Country\Aggregate\CountryState\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\Aggregate\CountryState\Channel\ChannelCountryStateDefinition;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ChannelCountryStateDefinition::class)]
class ChannelCountryStateDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheChannel(): void
    {
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();

        (new ChannelCountryStateDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('country_state.country.channels.id', $context->getChannelId())], $criteria->getFilters());
    }
}

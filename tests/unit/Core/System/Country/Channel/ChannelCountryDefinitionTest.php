<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Country\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Country\Channel\ChannelCountryDefinition;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ChannelCountryDefinition::class)]
class ChannelCountryDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheChannel(): void
    {
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();

        (new ChannelCountryDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('country.channels.id', $context->getChannelId())], $criteria->getFilters());
    }
}

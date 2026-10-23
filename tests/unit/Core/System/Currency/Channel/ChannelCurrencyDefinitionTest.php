<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Currency\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\Channel\ChannelCurrencyDefinition;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ChannelCurrencyDefinition::class)]
class ChannelCurrencyDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheChannel(): void
    {
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();

        (new ChannelCurrencyDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('currency.channels.id', $context->getChannelId())], $criteria->getFilters());
    }
}

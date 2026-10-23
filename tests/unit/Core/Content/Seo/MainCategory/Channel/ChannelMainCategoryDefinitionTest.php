<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\MainCategory\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\MainCategory\Channel\ChannelMainCategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ChannelMainCategoryDefinition::class)]
class ChannelMainCategoryDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheChannel(): void
    {
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();

        (new ChannelMainCategoryDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('channelId', $context->getChannelId())], $criteria->getFilters());
    }
}

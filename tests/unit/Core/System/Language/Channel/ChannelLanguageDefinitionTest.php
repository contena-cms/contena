<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Language\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Language\Channel\ChannelLanguageDefinition;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ChannelLanguageDefinition::class)]
class ChannelLanguageDefinitionTest extends TestCase
{
    public function testProcessCriteriaScopesToTheChannel(): void
    {
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();

        (new ChannelLanguageDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('language.channels.id', $context->getChannelId())], $criteria->getFilters());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Member\Channel\ChannelMemberAddressDefinition;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(ChannelMemberAddressDefinition::class)]
class ChannelMemberAddressDefinitionTest extends TestCase
{
    public function testProcessCriteria(): void
    {
        $definition = new ChannelMemberAddressDefinition();
        $criteria = new Criteria();
        $member = new MemberEntity();
        $member->setId(TestDefaults::CHANNEL);
        $context = Generator::generateChannelContext(member: $member);

        $definition->processCriteria($criteria, $context);

        static::assertNotEmpty($criteria->getFilters());

        $filter = $criteria->getFilters()[0] ?? null;
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame('memberId', $filter->getField());
        static::assertSame($context->getMember()?->getId(), $filter->getValue());
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressDefinition;

class ChannelMemberAddressDefinition extends MemberAddressDefinition implements ChannelDefinitionInterface
{
    public function getEntityClass(): string
    {
        return ChannelMemberAddressEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ChannelMemberAddressCollection::class;
    }

    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('memberId', $context->getMember()?->getId()));
    }
}

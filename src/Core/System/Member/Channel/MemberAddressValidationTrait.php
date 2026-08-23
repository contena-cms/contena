<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;

trait MemberAddressValidationTrait
{
    private function validateAddress(string $id, ChannelContext $context, MemberEntity $member): void
    {
        $criteria = new Criteria([$id])
            ->addFilter(new EqualsFilter('memberId', $member->getId()));

        $total = $this->addressRepository->searchIds($criteria, $context->getContext())->getTotal();
        if ($total !== 0) {
            return;
        }

        throw MemberException::addressNotFound($id);
    }
}

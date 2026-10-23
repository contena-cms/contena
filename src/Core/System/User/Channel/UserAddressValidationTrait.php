<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;

trait UserAddressValidationTrait
{
    private function validateAddress(string $id, ChannelContext $context, UserEntity $user): void
    {
        $criteria = (new Criteria([$id]))
            ->addFilter(new EqualsFilter('userId', $user->getId()));

        $total = $this->addressRepository->searchIds($criteria, $context->getContext())->getTotal();
        if ($total !== 0) {
            return;
        }

        throw UserException::addressNotFound($id);
    }
}

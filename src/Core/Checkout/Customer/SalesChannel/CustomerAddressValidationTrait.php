<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
trait CustomerAddressValidationTrait
{
    private function validateAddress(string $id, SalesChannelContext $context, CustomerEntity $customer): void
    {
        $criteria = (new Criteria([$id]))
            ->addFilter(new EqualsFilter('customerId', $customer->getId()));

        $total = $this->addressRepository->searchIds($criteria, $context->getContext())->getTotal();
        if ($total !== 0) {
            return;
        }

        throw CustomerException::addressNotFound($id);
    }
}

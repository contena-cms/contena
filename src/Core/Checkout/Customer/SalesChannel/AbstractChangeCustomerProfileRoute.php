<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SuccessResponse;

/**
 * This route can be used to change profile information about the logged-in user
 * The required fields are "salutationId", "firstName" and "lastName"
 */
#[Package('checkout')]
abstract class AbstractChangeCustomerProfileRoute
{
    abstract public function getDecorated(): AbstractChangeCustomerProfileRoute;

    abstract public function change(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse;
}

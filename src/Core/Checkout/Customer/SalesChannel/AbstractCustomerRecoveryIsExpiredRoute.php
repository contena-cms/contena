<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used get the CustomerRecoveryIsExpiredResponse entry for a given hash
 * The required parameter is: "hash"
 */
#[Package('checkout')]
abstract class AbstractCustomerRecoveryIsExpiredRoute
{
    abstract public function getDecorated(): AbstractCustomerRecoveryIsExpiredRoute;

    abstract public function load(RequestDataBag $data, SalesChannelContext $context): CustomerRecoveryIsExpiredResponse;
}

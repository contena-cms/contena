<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\ContextTokenResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractImitateCustomerRoute
{
    abstract public function getDecorated(): AbstractImitateCustomerRoute;

    abstract public function imitateCustomerLogin(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse;
}

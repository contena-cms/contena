<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\ContextTokenResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route allows changing configurations inside the context.
 * Following parameters are allowed to change: "currencyId", "languageId", "billingAddressId", "shippingAddressId",
 * "paymentMethodId", "shippingMethodId", "countryId" and "countryStateId"
 */
#[Package('framework')]
abstract class AbstractContextSwitchRoute
{
    abstract public function getDecorated(): AbstractContextSwitchRoute;

    abstract public function switchContext(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse;
}

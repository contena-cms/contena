<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\CustomerGroupRegistration;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads the customer group registration page
 */
#[Package('discovery')]
abstract class AbstractCustomerGroupRegistrationPageLoader
{
    abstract public function getDecorated(): AbstractCustomerGroupRegistrationPageLoader;

    abstract public function load(Request $request, SalesChannelContext $salesChannelContext): CustomerGroupRegistrationPage;
}

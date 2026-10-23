<?php declare(strict_types=1);

namespace Contena\Core\System\User\Service;

use Contena\Core\System\User\UserException;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

class GuestAuthenticator
{
    public function validate(OrderEntity $order, Request $request): void
    {
        $isOrderByGuest = $order->getOrderUser()?->getUser()?->getGuest();

        if (!$isOrderByGuest) {
            throw UserException::userNotLoggedIn();
        }

        $email = RequestParamHelper::get($request, 'email');
        $zipcode = RequestParamHelper::get($request, 'zipcode');
        if (!$email || !$zipcode) {
            throw UserException::guestNotAuthenticated();
        }

        // Do not trim the zipcode here. Existing guest orders may contain leading or trailing
        // whitespace in the stored billing zipcode and must remain accessible with that value.
        // See ticket: https://github.com/contena-cms/contena/issues/16005
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null
            || mb_strtolower($email) !== mb_strtolower($order->getOrderUser()?->getEmail() ?: '')
            || mb_strtoupper($zipcode) !== mb_strtoupper($billingAddress->getZipcode() ?: '')) {
            throw UserException::wrongGuestCredentials();
        }
    }
}

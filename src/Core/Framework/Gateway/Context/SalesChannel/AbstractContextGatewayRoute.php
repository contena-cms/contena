<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\SalesChannel;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\ContextTokenResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
abstract class AbstractContextGatewayRoute
{
    abstract public function getDecorated(): AbstractContextGatewayRoute;

    abstract public function load(Request $request, Cart $cart, SalesChannelContext $context): ContextTokenResponse;
}

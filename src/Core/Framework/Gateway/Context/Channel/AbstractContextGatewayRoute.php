<?php declare(strict_types=1);

namespace Contena\Core\Framework\Gateway\Context\Channel;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractContextGatewayRoute
{
    abstract public function getDecorated(): AbstractContextGatewayRoute;

    abstract public function load(Request $request, Cart $cart, ChannelContext $context): ContextTokenResponse;
}

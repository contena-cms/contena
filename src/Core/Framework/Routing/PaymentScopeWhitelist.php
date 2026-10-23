<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Checkout\Payment\Controller\PaymentController;
use Contena\Core\Framework\Log\Package;

class PaymentScopeWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === PaymentController::class;
    }
}

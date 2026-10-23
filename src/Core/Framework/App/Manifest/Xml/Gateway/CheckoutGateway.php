<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Manifest\Xml\Gateway;

use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
class CheckoutGateway extends AbstractGateway
{
    final public const PERMISSION = 'checkout_gateway';
}

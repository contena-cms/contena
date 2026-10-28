<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerNotLoggedInRoutingException extends RoutingException
{
}

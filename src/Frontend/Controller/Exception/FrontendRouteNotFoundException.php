<?php declare(strict_types=1);

namespace Contena\Frontend\Controller\Exception;

use Contena\Core\Framework\Log\Package;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * @codeCoverageIgnore
 */
class FrontendRouteNotFoundException extends RouteNotFoundException
{
    public function __construct(string $route, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('Route "%s" not found.', $route),
            previous: $previous
        );
    }
}

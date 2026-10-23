<?php declare(strict_types=1);

namespace Contena\Frontend\Controller\Exception;


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

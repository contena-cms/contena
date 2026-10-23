<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing\Exception;

use Contena\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidRouteScopeException extends RoutingException
{
    public function __construct(?string $routeName)
    {
        parent::__construct(
            Response::HTTP_PRECONDITION_FAILED,
            parent::INVALID_ROUTE_SCOPE,
            'Invalid route scope for route {{ routeName }}.',
            ['routeName' => $routeName ?? '']
        );
    }
}

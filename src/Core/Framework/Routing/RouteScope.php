<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

class RouteScope extends AbstractRouteScope
{
    final public const ID = 'default';

    protected array $allowedPaths = ['_wdt', '_profiler', '_error'];

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return self::ID;
    }
}

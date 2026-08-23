<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

class RouteScope extends AbstractRouteScope
{
    final public const string ID = 'default';

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

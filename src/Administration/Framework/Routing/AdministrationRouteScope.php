<?php declare(strict_types=1);

namespace Contena\Administration\Framework\Routing;

use Contena\Core\Framework\Routing\AbstractRouteScope;
use Contena\Core\Framework\Routing\ApiContextRouteScopeDependant;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Symfony\Component\HttpFoundation\Request;

class AdministrationRouteScope extends AbstractRouteScope implements ApiContextRouteScopeDependant
{
    final public const string ID = 'administration';
    final public const string ALLOWED_PATH = 'admin';

    /**
     * @internal
     */
    public function __construct(string $administrationPathName = self::ALLOWED_PATH)
    {
        $this->allowedPaths = [$administrationPathName, ApiRouteScope::ALLOWED_PATH];
    }

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return self::ID;
    }
}

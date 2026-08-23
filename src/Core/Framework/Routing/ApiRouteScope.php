<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class ApiRouteScope extends AbstractRouteScope implements ApiContextRouteScopeDependant
{
    final public const string ID = 'api';
    final public const string ALLOWED_PATH = 'api';

    protected array $allowedPaths = [self::ALLOWED_PATH, 'ct-domain-hash.html'];

    public function isAllowed(Request $request): bool
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        $authRequired = $request->attributes->get('auth_required', true);
        if (!$context instanceof Context) {
            throw RoutingException::missingRouteAttribute(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, (string) $request->attributes->get('_route', ''));
        }
        $source = $context->getSource();

        if (!$authRequired) {
            return $source instanceof SystemSource || $source instanceof AdminApiSource;
        }

        return $context->getSource() instanceof AdminApiSource;
    }

    /**
     * @codeCoverageIgnore no logic
     */
    public function getId(): string
    {
        return self::ID;
    }
}

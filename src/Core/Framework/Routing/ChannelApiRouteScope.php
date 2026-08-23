<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class ChannelApiRouteScope extends AbstractRouteScope implements ChannelContextRouteScopeDependant
{
    final public const string ID = 'channel-api';
    final public const string ALLOWED_PATH = 'channel-api';

    protected array $allowedPaths = [self::ALLOWED_PATH];

    public function isAllowed(Request $request): bool
    {
        if (!$request->attributes->get('auth_required', false)) {
            return true;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            throw RoutingException::missingRouteAttribute(
                PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
                (string) $request->attributes->get('_route', '')
            );
        }

        return $context->getSource() instanceof ChannelApiSource;
    }

    public function getId(): string
    {
        return self::ID;
    }
}

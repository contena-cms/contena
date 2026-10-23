<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class StoreApiRouteScope extends AbstractRouteScope implements ChannelContextRouteScopeDependant
{
    final public const ID = 'store-api';
    final public const ALLOWED_PATH = 'store-api';

    protected array $allowedPaths = [self::ALLOWED_PATH];

    public function isAllowed(Request $request): bool
    {
        if (!$request->attributes->get('auth_required', false)) {
            return true;
        }

        /** @var Context $requestContext */
        $requestContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        if (!$request->attributes->get('auth_required', true)) {
            return $requestContext->getSource() instanceof SystemSource;
        }

        return $requestContext->getSource() instanceof ChannelApiSource;
    }

    public function getId(): string
    {
        return self::ID;
    }
}

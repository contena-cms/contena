<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Extension\ContextRouteExtension;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ContextRoute extends AbstractContextRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractContextRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/context', name: 'channel-api.context', methods: ['GET'])]
    public function load(ChannelContext $context): ContextLoadRouteResponse
    {
        return $this->extensions->publish(
            name: ContextRouteExtension::NAME,
            extension: new ContextRouteExtension($context),
            function: $this->_load(...),
        );
    }

    private function _load(ChannelContext $context): ContextLoadRouteResponse
    {
        $response = new ContextLoadRouteResponse($context);
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

        return $response;
    }
}

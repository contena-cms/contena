<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Channel;

use Contena\Core\Content\Seo\Extension\SeoUrlRouteExtension;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SeoUrlRoute extends AbstractSeoUrlRoute
{
    /**
     * @internal
     *
     * @param ChannelRepository<SeoUrlCollection> $channelRepository
     */
    public function __construct(private readonly ChannelRepository $channelRepository, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractSeoUrlRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/seo-url',
        name: 'store-api.seo.url',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => SeoUrlDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria): SeoUrlRouteResponse
    {
        return $this->extensions->publish(
            name: SeoUrlRouteExtension::NAME,
            extension: new SeoUrlRouteExtension($request, $context, $criteria),
            function: fn (Request $request, ChannelContext $context, Criteria $criteria): SeoUrlRouteResponse => $this->_load($context, $criteria),
        );
    }

    private function _load(ChannelContext $context, Criteria $criteria): SeoUrlRouteResponse
    {
        return new SeoUrlRouteResponse($this->channelRepository->search($criteria, $context));
    }
}

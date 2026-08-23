<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Channel;

use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class SeoUrlRoute extends AbstractSeoUrlRoute
{
    /**
     * @internal
     *
     * @param ChannelRepository<SeoUrlCollection> $channelRepository
     */
    public function __construct(private readonly ChannelRepository $channelRepository)
    {
    }

    public function getDecorated(): AbstractSeoUrlRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/seo-url',
        name: 'channel-api.seo.url',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => SeoUrlDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria): SeoUrlRouteResponse
    {
        return new SeoUrlRouteResponse($this->channelRepository->search($criteria, $context));
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo\Channel\FixturesPhp;

use Contena\Core\Content\Category\Channel\AbstractCategoryRoute;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Channel\CategoryRouteResponse;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class StoreApiSeoResolverTestRoute
{
    public function __construct(
        private readonly AbstractCategoryRoute $categoryRoute,
        private readonly AbstractChannelContextFactory $contextFactory,
    ) {
    }

    #[Route(
        path: '/store-api/test/store-api-seo-resolver/no-auth-required',
        name: 'store-api.test.store_api_seo_resolver.no_auth_required',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function noAuthRequiredAction(Request $request): CategoryRouteResponse
    {
        $channelId = $request->query->get('sales-channel-id');
        \assert($channelId !== null);

        return $this->categoryRoute->load(
            CategoryRoute::HOME,
            $request,
            $this->contextFactory->create(Uuid::randomHex(), $channelId)
        );
    }
}

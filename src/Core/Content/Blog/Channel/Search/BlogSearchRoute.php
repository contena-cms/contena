<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Search;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Extension\BlogSearchRouteExtension;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilderInterface;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BlogSearchRoute extends AbstractBlogSearchRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BlogSearchBuilderInterface $searchBuilder,
        private readonly BlogListingLoader $blogListingLoader,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractBlogSearchRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/search',
        name: 'store-api.search',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria): BlogSearchRouteResponse
    {
        return $this->extensions->publish(
            name: BlogSearchRouteExtension::NAME,
            extension: new BlogSearchRouteExtension($request, $context, $criteria),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, ChannelContext $context, Criteria $criteria): BlogSearchRouteResponse
    {
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $criteria->addFilter(
            new BlogAvailableFilter($context->getChannelId(), BlogVisibilityDefinition::VISIBILITY_SEARCH)
        );

        if (RequestParamHelper::get($request, 'search')) {
            $this->searchBuilder->build($request, $criteria, $context);
        }

        $result = $this->blogListingLoader->load($criteria, $context);

        $result = BlogListingResult::createFrom($result);

        return new BlogSearchRouteResponse($result);
    }
}

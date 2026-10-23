<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Suggest;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Extension\BlogSuggestRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class BlogSuggestRoute extends AbstractBlogSuggestRoute
{
    public const STATE = 'suggest-route-context';

    /**
     * @internal
     */
    public function __construct(
        private readonly BlogListingLoader $blogListingLoader,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractBlogSuggestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/search-suggest',
        name: 'channel-api.search.suggest',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria): BlogSuggestRouteResponse
    {
        return $this->extensions->publish(
            name: BlogSuggestRouteExtension::NAME,
            extension: new BlogSuggestRouteExtension($request, $context, $criteria),
            function: fn (Request $request, ChannelContext $context, Criteria $criteria): BlogSuggestRouteResponse => $this->_load($context, $criteria),
        );
    }

    private function _load(ChannelContext $context, Criteria $criteria): BlogSuggestRouteResponse
    {
        $result = $this->blogListingLoader->load($criteria, $context);

        $result = BlogListingResult::createFrom($result);

        return new BlogSuggestRouteResponse($result);
    }
}

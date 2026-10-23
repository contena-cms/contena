<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Extension\BlogListRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BlogListRoute extends AbstractBlogListRoute
{
    /**
     * @internal
     *
     * @param ChannelRepository<BlogCollection> $blogRepository
     */
    public function __construct(private readonly ChannelRepository $blogRepository, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractBlogListRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/blog',
        name: 'store-api.blog.search',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(Criteria $criteria, ChannelContext $context): BlogListResponse
    {
        return $this->extensions->publish(
            name: BlogListRouteExtension::NAME,
            extension: new BlogListRouteExtension($criteria, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Criteria $criteria, ChannelContext $context): BlogListResponse
    {
        return new BlogListResponse($this->blogRepository->search($criteria, $context));
    }
}

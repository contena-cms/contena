<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Garan;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Extension\GaranLabelRouteExtension;
use Contena\Core\Content\Blog\Garan\GaranLabelResolver;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class GaranLabelRoute extends AbstractGaranLabelRoute
{

    public function getDecorated(): AbstractGaranLabelRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/blog/{blogId}/garan-label',
        name: 'store-api.blog.garan-label',
        methods: [Request::METHOD_GET]
    )]
    public function load(string $blogId, ChannelContext $context): GaranLabelRouteResponse
    {
        return $this->extensions->publish(
            name: GaranLabelRouteExtension::NAME,
            extension: new GaranLabelRouteExtension($blogId, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $blogId, ChannelContext $context): GaranLabelRouteResponse
    {
        $blog = $this->loadBlog($blogId, $context);

        return new GaranLabelRouteResponse(
            $this->resolver->resolve($blog, GaranLabelResolver::LABEL_TYPE_FULL),
            $this->resolver->resolve($blog, GaranLabelResolver::LABEL_TYPE_NESTED),
        );
    }

    private function loadBlog(string $blogId, ChannelContext $context): ChannelBlogEntity
    {
        $criteria = new Criteria([$blogId]);
        $criteria->setTitle('blog-garan-label-route');
        $criteria->addAssociation('manufacturer');
        $criteria->addFilter(new BlogAvailableFilter($context->getChannelId(), BlogVisibilityDefinition::VISIBILITY_LINK));

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        if (!$blog instanceof ChannelBlogEntity) {
            throw BlogException::blogNotFound($blogId);
        }

        return $blog;
    }
}

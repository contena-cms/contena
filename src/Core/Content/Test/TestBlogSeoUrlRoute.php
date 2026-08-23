<?php declare(strict_types=1);

namespace Contena\Core\Content\Test;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class TestBlogSeoUrlRoute implements SeoUrlRouteInterface
{
    final public const string ROUTE_NAME = 'test.blog.page';

    final public const string DEFAULT_TEMPLATE = '{{ blog.id }}';

    public function __construct(private readonly BlogDefinition $blogDefinition)
    {
    }

    #[Route(path: '/test/{blogId}', name: 'test.blog.page', options: ['seo' => true], methods: ['GET'])]
    public function route(): Response
    {
        return new Response();
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->blogDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true
        );
    }

    public function prepareCriteria(Criteria $criteria, ChannelEntity $channel): void
    {
        // no-op, dummy implementation
    }

    /**
     * @param BlogEntity $entity
     */
    public function getMapping(Entity $entity, ?ChannelEntity $channel): SeoUrlMapping
    {
        return new SeoUrlMapping(
            $entity,
            ['blogId' => $entity->getId()],
            ['blog' => $entity->jsonSerialize()]
        );
    }
}

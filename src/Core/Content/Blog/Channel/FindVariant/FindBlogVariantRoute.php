<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\FindVariant;

use Contena\Core\Content\Blog\Extension\FindBlogVariantRouteExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class FindBlogVariantRoute extends AbstractFindBlogVariantRoute
{
    /**
     * @internal
     *
     * @param ChannelRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly ChannelRepository $blogRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractBlogCloseoutFilterFactory $blogCloseoutFilterFactory,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractFindBlogVariantRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/blog/{blogId}/find-variant',
        name: 'store-api.blog.find-variant',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $blogId, Request $request, ChannelContext $context): FindBlogVariantRouteResponse
    {
        return $this->extensions->publish(
            name: FindBlogVariantRouteExtension::NAME,
            extension: new FindBlogVariantRouteExtension($blogId, $request, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $blogId, Request $request, ChannelContext $context): FindBlogVariantRouteResponse
    {
        $switchedGroup = RequestParamHelper::get($request, 'switchedGroup');

        $options = RequestParamHelper::get($request, 'options', []);

        foreach ($options as $optionId) {
            if (!\is_string($optionId)) {
                throw BlogException::invalidOptionsParameter();
            }
        }

        if (Feature::isActive('CACHE_REWORK')) {
            $this->cacheTagCollector->addTag(EntityCacheKeyGenerator::buildBlogTag($blogId));
        }

        $variantId = $this->searchForOptions($blogId, $context, $options);

        if ($variantId !== null) {
            return new FindBlogVariantRouteResponse(new FoundCombination($variantId, $options));
        }

        while (\count($options) > 1) {
            foreach ($options as $groupId => $_optionId) {
                if ($groupId !== $switchedGroup) {
                    unset($options[$groupId]);

                    break;
                }
            }

            $variantId = $this->searchForOptions($blogId, $context, $options);

            if ($variantId) {
                return new FindBlogVariantRouteResponse(new FoundCombination($variantId, $options));
            }
        }

        throw BlogException::variantNotFound($blogId, $options);
    }

    /**
     * @param array<string> $options
     */
    private function searchForOptions(
        string $blogId,
        ChannelContext $channelContext,
        array $options
    ): ?string {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('blog.parentId', $blogId))
            ->setLimit(1);

        foreach ($options as $optionId) {
            $criteria->addFilter(new EqualsFilter('blog.optionIds', $optionId));
        }

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            $channelContext->getChannelId()
        )) {
            $criteria->addFilter($this->blogCloseoutFilterFactory->create($channelContext));
        }

        return $this->blogRepository->searchIds($criteria, $channelContext)->firstId();
    }
}

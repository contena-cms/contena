<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Channel;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Contena\Core\Framework\ContentSystem\Cache\CacheFinalizer;
use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\ContentPipeline;
use Contena\Core\Framework\ContentSystem\ContentSection;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Contena\Core\Framework\ContentSystem\Output\Format\AbstractResponseFactory;
use Contena\Core\Framework\ContentSystem\RenderableLayout;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
class ContentRoute extends AbstractContentRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly ContentSection $section,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly AbstractResponseFactory $responseFactory,
        private readonly ContentPipeline $contentPipeline,
        private readonly CacheFinalizer $cacheFinalizer,
    ) {
    }

    public function getDecorated(): AbstractContentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $path, Request $request, ChannelContext $context): AbstractContentRouteResponse
    {
        $resolved = $this->specificationResolver->resolve($path, $request, $context);
        $specification = $resolved->specification;

        foreach ($this->section->buildRouteCacheTags($resolved->layoutId) as $tag) {
            $this->cacheTagCollector->addTag($tag);
        }

        $layoutEntity = $this->contentLayoutRepository
            ->search(new Criteria([$resolved->layoutId]), $context->getContext())
            ->getEntities()
            ->first();

        if (!$layoutEntity instanceof ContentLayoutEntity) {
            throw ContentSystemException::layoutNotFound($resolved->layoutId);
        }

        $cacheContext = new RenderingCacheContext();
        $cacheContext->addTags($specification->cacheTags);

        $contentPage = $this->contentPipeline->load(
            RenderableLayout::fromEntity($layoutEntity),
            $specification,
            $cacheContext,
            $this->responseFactory->getRenderingMode(),
            $context,
        );

        $this->cacheFinalizer->finalize($request, $cacheContext);

        return $this->responseFactory->createResponse($contentPage);
    }
}

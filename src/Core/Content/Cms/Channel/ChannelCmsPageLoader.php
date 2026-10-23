<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Channel;

use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Contena\Core\Content\Cms\Events\CmsPageLoaderCriteriaEvent;
use Contena\Core\Content\Cms\Channel\Struct\BlogBoxStruct;
use Contena\Core\Content\Cms\Channel\Struct\BlogSliderStruct;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ChannelCmsPageLoader implements ChannelCmsPageLoaderInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<CmsPageCollection> $cmsPageRepository
     */
    public function __construct(
        private readonly EntityRepository $cmsPageRepository,
        private readonly CmsSlotsDataResolver $slotDataResolver,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function load(
        Request $request,
        Criteria $criteria,
        ChannelContext $context,
        ?array $config = null,
        ?ResolverContext $resolverContext = null
    ): EntitySearchResult {
        $this->dispatcher->dispatch(new CmsPageLoaderCriteriaEvent($request, $criteria, $context));
        $config ??= [];

        // ensure sections, blocks and slots are loaded, slots and blocks can be restricted by caller
        $criteria->addAssociation('sections.backgroundMedia');
        $criteria->addAssociation('sections.blocks.backgroundMedia');
        $criteria->addAssociation('sections.blocks.slots');

        // step 1, load cms pages with blocks and slots
        $result = $this->cmsPageRepository->search($criteria, $context->getContext());
        $pages = $result->getEntities();

        foreach ($pages as $page) {
            $sections = $page->getSections();

            if ($sections === null) {
                continue;
            }

            $sections->sort(static fn (CmsSectionEntity $a, CmsSectionEntity $b) => $a->getPosition() <=> $b->getPosition());

            if (!$resolverContext) {
                $resolverContext = new ResolverContext($context, $request);
            }

            // step 2, sort blocks into sectionPositions
            foreach ($sections as $section) {
                $blocks = $section->getBlocks();
                if ($blocks === null) {
                    continue;
                }
                $blocks->sort(static fn (CmsBlockEntity $a, CmsBlockEntity $b) => $a->getPosition() <=> $b->getPosition());

                foreach ($blocks as $block) {
                    $slots = $block->getSlots();
                    if ($slots === null) {
                        continue;
                    }
                    $slots->sort(static fn (CmsSlotEntity $a, CmsSlotEntity $b) => $a->getSlot() <=> $b->getSlot());
                }
            }

            // step 3, find config overwrite
            $overwrite = $config[$page->getId()] ?? $config;

            // step 4, overwrite slot config
            $this->overwriteSlotConfig($sections, $overwrite);

            // step 5, resolve slot data
            $this->loadSlotData($sections, $resolverContext);
        }

        $this->dispatcher->dispatch(new CmsPageLoadedEvent($request, $pages, $context));

        $this->cacheTagCollector->addTag(...$this->extractBlogIds($pages));

        return $result;
    }

    private function loadSlotData(CmsSectionCollection $sections, ResolverContext $resolverContext): void
    {
        $blocks = $sections->getBlocks();
        $slots = $this->slotDataResolver->resolve($blocks->getSlots(), $resolverContext);

        $blocks->setSlots($slots);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function overwriteSlotConfig(CmsSectionCollection $sections, array $config): void
    {
        foreach ($sections->getBlocks()->getSlots() as $slot) {
            if ($slot->getConfig() === null && $slot->getTranslation('config') !== null) {
                $slot->setConfig($slot->getTranslation('config'));
            }

            if ($config === []) {
                continue;
            }

            if (!isset($config[$slot->getId()])) {
                continue;
            }

            $defaultConfig = $slot->getConfig() ?? [];
            $merged = $this->overrideArray($defaultConfig, $config[$slot->getId()]);

            $slot->setConfig($merged);
            $slot->addTranslated('config', $merged);
        }
    }

    /**
     * @param EntityCollection<CmsPageEntity> $pages
     *
     * @return array<string>
     */
    private function extractBlogIds(EntityCollection $pages): array
    {
        $ids = [];
        $streamIds = [];

        /** @var CmsPageEntity $page */
        foreach ($pages as $page) {
            $slots = $page->getElementsOfType('blog-slider');

            /** @var CmsSlotEntity $slot */
            foreach ($slots as $slot) {
                $slider = $slot->getData();

                if (!$slider instanceof BlogSliderStruct) {
                    continue;
                }

                if ($slider->getStreamId() !== null) {
                    $streamIds[] = $slider->getStreamId();
                }

                if ($slider->getBlogs() === null) {
                    continue;
                }
                foreach ($slider->getBlogs() as $blog) {
                    $ids[] = $blog->getId();
                    $ids[] = $blog->getParentId();
                }
            }

            $slots = $page->getElementsOfType('blog-box');
            /** @var CmsSlotEntity $slot */
            foreach ($slots as $slot) {
                $box = $slot->getData();

                if (!$box instanceof BlogBoxStruct) {
                    continue;
                }
                if ($box->getBlog() === null) {
                    continue;
                }

                $ids[] = $box->getBlog()->getId();
                $ids[] = $box->getBlog()->getParentId();
            }

            $ids = array_values(array_unique(array_filter($ids)));
        }

        return [
            ...array_map(EntityCacheKeyGenerator::buildBlogTag(...), $ids),
            ...array_map(EntityCacheKeyGenerator::buildStreamTag(...), $streamIds),
            ...array_map(EntityCacheKeyGenerator::buildCmsTag(...), $pages->getIds()),
        ];
    }

    /**
     * Recursively overrides the original array with values from the override array.
     * Merges recursively for associative arrays and replaces completely for index arrays.
     *
     * @param array<string, mixed> $original
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function overrideArray(array $original, array $override): array
    {
        foreach ($override as $key => $value) {
            $originalValue = $original[$key] ?? null;
            if (
                \is_array($originalValue)
                && \is_array($value)
                && !array_is_list($originalValue)
                && !array_is_list($value)
            ) {
                $original[$key] = $this->overrideArray($originalValue, $value);
                continue;
            }

            // Simple value override
            $original[$key] = $value;
        }

        return $original;
    }
}

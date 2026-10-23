<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms\BlogSlider;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\BlogSliderStruct;
use Contena\Core\Content\Blog\Events\BlogSliderStaticCriteriaEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class StaticBlogProcessor extends AbstractBlogSliderProcessor
{
    private const STATIC_SEARCH_KEY = 'blog-slider';

    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getDecorated(): AbstractBlogSliderProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function getSource(): string
    {
        return 'static';
    }

    public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $blogs = $config->get('blogs');
        \assert($blogs instanceof FieldConfig);
        $criteria = new Criteria($blogs->getArrayValue());
        $criteria->addAssociation('options.group');

        $this->eventDispatcher->dispatch(new BlogSliderStaticCriteriaEvent($slot, $criteria, $resolverContext->getChannelContext()));

        $collection = new CriteriaCollection();
        $collection->add(self::STATIC_SEARCH_KEY . '_' . $slot->getUniqueIdentifier(), BlogDefinition::class, $criteria);

        return $collection;
    }

    public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void
    {
        $key = self::STATIC_SEARCH_KEY . '_' . $slot->getUniqueIdentifier();
        $searchResult = $result->get($key);

        if (!$searchResult) {
            return;
        }

        $blogs = $searchResult->getEntities();
        if (!$blogs instanceof BlogCollection) {
            return;
        }

        $context = $resolverContext->getChannelContext();

        if ($this->hideUnavailableBlogs($context)) {
            $blogs = $this->filterOutOutOfStockHiddenCloseoutBlogs($blogs);
        }

        $criteriaIds = array_unique($searchResult->getCriteria()->getIds());
        if ($criteriaIds !== [] && $searchResult->getCriteria()->getSorting() === []) {
            $configuredIds = $slot->getFieldConfig()->get('blogs')?->getArrayValue() ?? [];
            usort(
                $criteriaIds,
                static fn (string $a, string $b): int => array_search($a, $configuredIds, true) <=> array_search($b, $configuredIds, true)
            );
            $blogs->sortByIdArray($criteriaIds);
        }

        $slider = new BlogSliderStruct();
        $slider->setBlogs($blogs);

        $slot->setData($slider);
    }

    protected function hideUnavailableBlogs(ChannelContext $context): bool
    {
        return $this->systemConfigService->getBool(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            $context->getChannelId()
        );
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Cms\Type;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\ImageSliderItemStruct;
use Contena\Core\Content\Cms\Channel\Struct\ImageSliderStruct;
use Contena\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

class ImageSliderTypeDataResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractDefaultMediaResolver $mediaResolver)
    {
    }

    public function getType(): string
    {
        return 'image-slider';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $sliderItemsConfig = $slot->getFieldConfig()->get('sliderItems');
        if ($sliderItemsConfig === null || $sliderItemsConfig->isDefault()) {
            return null;
        }

        $criteriaCollection = new CriteriaCollection();

        if ($sliderItemsConfig->isMapped() && $resolverContext instanceof EntityResolverContext && $sliderItemsConfig->getStringValue() === 'blog.media') {
            $resolved = $this->resolveEntityValue($resolverContext->getEntity(), $sliderItemsConfig->getStringValue());

            if ($this->isBlogMediaResolved($resolved)) {
                return null;
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('blogId', $resolverContext->getEntity()->getUniqueIdentifier()));
            $criteria->addAssociation('media');
            $criteria->addSorting(new FieldSorting('position'));

            $criteriaCollection->add('blog_media_' . $slot->getUniqueIdentifier(), BlogMediaDefinition::class, $criteria);

            return $criteriaCollection;
        }

        if ($sliderItemsConfig->isMapped()) {
            return null;
        }

        $sliderItems = $sliderItemsConfig->getArrayValue();
        $mediaIds = array_column($sliderItems, 'mediaId');

        $criteria = new Criteria($mediaIds);
        $criteriaCollection->add('media_' . $slot->getUniqueIdentifier(), MediaDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $imageSlider = new ImageSliderStruct();
        $slot->setData($imageSlider);

        $navigation = $config->get('navigation');
        if ($navigation !== null && $navigation->isStatic()) {
            $imageSlider->setNavigation($navigation->getArrayValue());
        }

        $imageSlider->setUseFetchPriorityOnFirstItem((bool) $config->get('useFetchPriorityOnFirstItem'));

        $sliderItemsConfig = $config->get('sliderItems');
        if ($sliderItemsConfig === null) {
            return;
        }

        if ($sliderItemsConfig->isStatic()) {
            foreach ($sliderItemsConfig->getArrayValue() as $sliderItem) {
                $this->addMedia($slot, $imageSlider, $result, $sliderItem);
            }
        }

        if ($sliderItemsConfig->isDefault()) {
            foreach ($sliderItemsConfig->getArrayValue() as $sliderItem) {
                $this->addDefaultMediaToImageSlider($imageSlider, $sliderItem);
            }
        }

        if ($sliderItemsConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $sliderItems = null;

            if ($sliderItemsConfig->getStringValue() === 'blog.media') {
                $searchResult = $result->get('blog_media_' . $slot->getUniqueIdentifier());
                if ($searchResult instanceof EntitySearchResult) {
                    $sliderItems = $searchResult->getEntities();
                }
            }

            if (!$sliderItems instanceof BlogMediaCollection) {
                $sliderItems = $this->resolveEntityValue($resolverContext->getEntity(), $sliderItemsConfig->getStringValue());
            }

            if (!$sliderItems instanceof BlogMediaCollection || $sliderItems->count() < 1) {
                return;
            }

            if ($sliderItemsConfig->getStringValue() === 'blog.media') {
                $blogEntity = $resolverContext->getEntity();
                if ($blogEntity instanceof BlogEntity) {
                    $blogCoverId = $blogEntity->getCoverId();
                    $blogCover = $blogEntity->getCover();
                    if ($blogCoverId !== null && $blogCover !== null) {
                        $sliderItems = new BlogMediaCollection(array_merge(
                            [$blogCoverId => $blogCover],
                            $sliderItems->getElements()
                        ));
                    }
                }
            }

            foreach ($sliderItems->getMedia() as $media) {
                $imageSliderItem = new ImageSliderItemStruct();
                $imageSliderItem->setMedia($media);
                $imageSlider->addSliderItem($imageSliderItem);
            }
        }
    }

    /**
     * @param array{url?: string, ariaLabel?: string, newTab?: bool, mediaId: string} $config
     */
    private function addMedia(CmsSlotEntity $slot, ImageSliderStruct $imageSlider, ElementDataCollection $result, array $config): void
    {
        $imageSliderItem = new ImageSliderItemStruct();

        if (!empty($config['url'])) {
            $imageSliderItem->setUrl($config['url']);
            $imageSliderItem->setAriaLabel($config['ariaLabel'] ?? null);
            $imageSliderItem->setNewTab($config['newTab'] ?? false);
        }

        $searchResult = $result->get('media_' . $slot->getUniqueIdentifier());
        if (!$searchResult) {
            return;
        }

        $media = $searchResult->getEntities()->get($config['mediaId']);
        if (!$media instanceof MediaEntity) {
            return;
        }

        $imageSliderItem->setMedia($media);
        $imageSlider->addSliderItem($imageSliderItem);
    }

    /**
     * @param array{fileName: string} $config
     */
    private function addDefaultMediaToImageSlider(ImageSliderStruct $imageSlider, array $config): void
    {
        $media = $this->mediaResolver->getDefaultCmsMediaEntity($config['fileName']);

        if ($media === null) {
            return;
        }

        $imageSliderItem = new ImageSliderItemStruct();
        $imageSliderItem->setMedia($media);
        $imageSlider->addSliderItem($imageSliderItem);
    }

    private function isBlogMediaResolved(?Struct $blogMedia): bool
    {
        if (!$blogMedia instanceof BlogMediaCollection) {
            return false;
        }

        foreach ($blogMedia as $blogMediaEntity) {
            if ($blogMediaEntity->getMedia() === null) {
                return false;
            }
        }

        return true;
    }
}

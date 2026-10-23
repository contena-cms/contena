<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms;

use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\ManufacturerLogoStruct;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ManufacturerLogoCmsElementResolver extends AbstractBlogDetailCmsElementResolver
{
    public function getType(): string
    {
        return 'manufacturer-logo';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $mediaConfig = $slot->getFieldConfig()->get('media');
        $criteriaCollection = parent::collect($slot, $resolverContext) ?? new CriteriaCollection();

        if ($mediaConfig !== null && $mediaConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $media = $this->resolveEntityValue($resolverContext->getEntity(), $mediaConfig->getStringValue());
            $resolverEntity = $resolverContext->getEntity();

            if (!$media instanceof MediaEntity
                && $resolverEntity instanceof ChannelBlogEntity
                && ($manufacturerId = $resolverEntity->getManufacturerId()) !== null
                && (!$resolverEntity->getManufacturer() instanceof BlogManufacturerEntity || $resolverEntity->getManufacturer()->getMedia() === null)
            ) {
                $criteria = new Criteria([$manufacturerId]);
                $criteria->addAssociation('media');
                $criteriaCollection->add('mapped_product_manufacturer_' . $slot->getUniqueIdentifier(), BlogManufacturerDefinition::class, $criteria);
            }
        }

        if ($mediaConfig === null || $mediaConfig->isMapped() || $mediaConfig->getValue() === null) {
            return $criteriaCollection->all() !== [] ? $criteriaCollection : null;
        }

        $criteria = new Criteria([$mediaConfig->getStringValue()]);
        $criteriaCollection->add('media_' . $slot->getUniqueIdentifier(), MediaDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $manufacturerStruct = new ManufacturerLogoStruct();
        $slot->setData($manufacturerStruct);

        $urlConfig = $config->get('url');
        if ($urlConfig !== null) {
            $manufacturerStruct->setUrl($this->getConfigUrl($urlConfig, $resolverContext));
        }

        $newTabConfig = $config->get('newTab');
        if ($newTabConfig !== null) {
            $manufacturerStruct->setNewTab($newTabConfig->getBoolValue());
        }

        $mediaConfig = $config->get('media');

        if ($mediaConfig !== null) {
            $media = $this->getMedia($slot, $result, $mediaConfig, $resolverContext);
            if ($media !== null) {
                $manufacturerStruct->setMedia($media);
                $manufacturerStruct->setMediaId($media->getId());
            }
        }

        $mappedManufacturer = $this->getMappedManufacturer($slot, $result);
        if ($mappedManufacturer !== null) {
            $manufacturerStruct->setManufacturer($mappedManufacturer);
        }

        if ($manufacturerStruct->getManufacturer() === null && $resolverContext instanceof EntityResolverContext && $resolverContext->getDefinition() instanceof ChannelBlogDefinition) {
            /** @var ChannelBlogEntity $blog */
            $blog = $resolverContext->getEntity();
            $manufacturerStruct->setManufacturer($blog->getManufacturer());
        }
    }

    private function getConfigUrl(FieldConfig $config, ResolverContext $resolverContext): ?string
    {
        if ($config->isStatic()) {
            return $config->getStringValue();
        }

        if (!$resolverContext instanceof EntityResolverContext) {
            return null;
        }

        return $this->resolveEntityValue($resolverContext->getEntity(), $config->getStringValue());
    }

    private function getMedia(CmsSlotEntity $slot, ElementDataCollection $result, FieldConfig $config, ResolverContext $resolverContext): ?MediaEntity
    {
        if ($config->isStatic()) {
            $searchResult = $result->get('media_' . $slot->getUniqueIdentifier());
            if (!$searchResult) {
                return null;
            }

            /** @var MediaEntity|null $media */
            $media = $searchResult->getEntities()->get($config->getStringValue());

            return $media;
        }

        if (!$resolverContext instanceof EntityResolverContext) {
            return null;
        }

        $mappedManufacturer = $this->getMappedManufacturer($slot, $result);
        if ($mappedManufacturer !== null) {
            return $mappedManufacturer->getMedia();
        }

        return $this->resolveEntityValue($resolverContext->getEntity(), $config->getStringValue());
    }

}

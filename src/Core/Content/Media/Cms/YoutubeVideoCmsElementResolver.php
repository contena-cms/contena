<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Cms;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\ImageStruct;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;

class YoutubeVideoCmsElementResolver extends AbstractCmsElementResolver
{
    public function getType(): string
    {
        return 'youtube-video';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $mediaConfig = $slot->getFieldConfig()->get('previewMedia');
        if ($mediaConfig === null || $mediaConfig->getValue() === null) {
            return null;
        }

        $mediaId = null;

        if ($mediaConfig->isMapped()) {
            if (!$resolverContext instanceof EntityResolverContext) {
                return null;
            }

            $mappedMedia = $this->resolveEntityValue($resolverContext->getEntity(), $mediaConfig->getStringValue());

            if ($mappedMedia instanceof MediaEntity) {
                return null;
            }

            if (!\is_string($mappedMedia) || $mappedMedia === '') {
                return null;
            }

            $mediaId = $mappedMedia;
        }

        if ($mediaConfig->isStatic()) {
            $mediaId = $mediaConfig->getStringValue();
        }

        if ($mediaId === null) {
            return null;
        }

        $criteria = new Criteria([$mediaId]);

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('media_' . $slot->getUniqueIdentifier(), MediaDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $image = new ImageStruct();
        $slot->setData($image);

        $mediaConfig = $config->get('previewMedia');
        if ($mediaConfig && $mediaConfig->getValue()) {
            $this->addMediaEntity($slot, $image, $result, $mediaConfig, $resolverContext);
        }
    }

    private function addMediaEntity(CmsSlotEntity $slot, ImageStruct $image, ElementDataCollection $result, FieldConfig $config, ResolverContext $resolverContext): void
    {
        if ($config->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $media = $this->resolveEntityValue($resolverContext->getEntity(), $config->getStringValue());

            if ($media instanceof MediaEntity) {
                $image->setMediaId($media->getUniqueIdentifier());
                $image->setMedia($media);

                return;
            }

            if (\is_string($media) && $media !== '') {
                $image->setMediaId($media);

                $searchResult = $result->get('media_' . $slot->getUniqueIdentifier());
                if (!$searchResult) {
                    return;
                }

                $mappedMedia = $searchResult->getEntities()->get($media);
                if (!$mappedMedia instanceof MediaEntity) {
                    return;
                }

                $image->setMedia($mappedMedia);
            }

            return;
        }

        if ($config->isStatic()) {
            $image->setMediaId($config->getStringValue());

            $searchResult = $result->get('media_' . $slot->getUniqueIdentifier());
            if (!$searchResult) {
                return;
            }

            $media = $searchResult->getEntities()->get($config->getStringValue());
            if (!$media instanceof MediaEntity) {
                return;
            }

            $image->setMedia($media);
        }
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductContentLayout;

use Contena\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorDataLoader;
use Contena\Core\Content\Product\ContentSystem\DataLoader\ProductConfiguratorLoaderConfig;
use Contena\Core\Content\Product\ContentSystem\DataLoader\ProductReviewDataLoader;
use Contena\Core\Content\Product\ContentSystem\DataLoader\ProductReviewLoaderConfig;
use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('inventory')]
class ProductContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'product_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'product';

    final public const CONFIG_KEY_DEFAULT_CONTENT_LAYOUT = 'core.content_system.default_product_content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ProductContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ProductContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return [EntityCacheKeyGenerator::buildProductTag($entityId)];
    }

    public function getContentLayoutDefaultConfigKey(): string
    {
        return self::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT;
    }

    public function getPageDataRequirements(): array
    {
        return [
            ...parent::getPageDataRequirements(),
            new DataRequirement(
                'configuratorSettings',
                ProductConfiguratorDataLoader::SOURCE,
                new ProductConfiguratorLoaderConfig(productId: 'productId'),
            ),
        ];
    }

    protected function getEntityAssociations(): array
    {
        return [
            'manufacturer.media',
            'options.group',
            'properties.group',
            'mainCategories.category',
            'media.media',
        ];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('product_id', 'productId');
    }
}

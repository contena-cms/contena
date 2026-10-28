<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Aggregate\CategoryContentLayout;

use Contena\Core\Content\Category\SalesChannel\CategoryRoute;
use Contena\Core\Content\Product\ContentSystem\DataLoader\ProductListingDataLoader;
use Contena\Core\Content\Product\ContentSystem\DataLoader\ProductListingLoaderConfig;
use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class CategoryContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'category_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'category';

    final public const CONFIG_KEY_DEFAULT_CONTENT_LAYOUT = 'core.content_system.default_category_content_layout';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CategoryContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CategoryContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return [CategoryRoute::buildName($entityId)];
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
                'productListing',
                ProductListingDataLoader::SOURCE,
                new ProductListingLoaderConfig(
                    property: 'categoryId',
                    associations: ['cover', 'cover.media', 'cover.media.thumbnails', 'manufacturer'],
                )
            ),
        ];
    }

    protected function getEntityAssociations(): array
    {
        return ['media', 'translations'];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('category_id', 'categoryId');
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Aggregate\CategoryContentLayout;

use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;

/**
 * @internal
 *
 * @final
 */
class CategoryContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'category_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'category';

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

    protected function getEntityAssociations(): array
    {
        return ['media', 'translations'];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('category_id', 'categoryId');
    }
}

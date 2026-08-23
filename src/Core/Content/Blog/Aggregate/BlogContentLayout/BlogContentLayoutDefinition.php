<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogContentLayout;

use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;

/**
 * @internal
 *
 * @final
 */
class BlogContentLayoutDefinition extends AbstractContentLayoutAssignableDefinition
{
    final public const ENTITY_NAME = 'blog_content_layout';

    final public const CONTENT_LAYOUT_ENTITY_TYPE = 'blog';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BlogContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BlogContentLayoutCollection::class;
    }

    public function getContentLayoutEntityType(): string
    {
        return self::CONTENT_LAYOUT_ENTITY_TYPE;
    }

    public function getCacheTags(string $entityId): array
    {
        return [EntityCacheKeyGenerator::buildBlogTag($entityId)];
    }

    protected function getEntityAssociations(): array
    {
        return [
            'cover.media',
            'openGraphMedia',
            'mainCategories.category',
            'media.media',
            'categories',
            'tags',
        ];
    }

    protected function defineEntityIdField(): IdField
    {
        return new IdField('blog_id', 'blogId');
    }
}

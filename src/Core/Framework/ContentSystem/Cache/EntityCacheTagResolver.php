<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Cache;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\ProductStream\ProductStreamDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Log\Package;

/**
 * Resolves cache tags for entities based on the existing cache invalidation system.
 *
 * Only entities with defined cache tag patterns are supported. For unsupported
 * entities, null is returned, indicating the page should not be cached.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class EntityCacheTagResolver
{
    /**
     * @var array<string, string>
     */
    private const TAG_PATTERNS = [
        ProductDefinition::ENTITY_NAME => 'product-',
        CategoryDefinition::ENTITY_NAME => 'category-route-',
        ProductStreamDefinition::ENTITY_NAME => 'product-stream-',
    ];

    public function resolve(EntityDefinition $definition, string $primaryKey): ?string
    {
        $entityName = $definition->getEntityName();

        $prefix = self::TAG_PATTERNS[$entityName] ?? null;

        if ($prefix === null) {
            return null;
        }

        return $prefix . $primaryKey;
    }
}

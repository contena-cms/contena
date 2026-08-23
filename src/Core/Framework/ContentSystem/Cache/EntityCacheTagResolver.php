<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Cache;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;

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
class EntityCacheTagResolver
{
    /**
     * @var array<string, string>
     */
    private const array TAG_PATTERNS = [
        BlogDefinition::ENTITY_NAME => 'blog-',
        CategoryDefinition::ENTITY_NAME => 'category-route-',
        LandingPageDefinition::ENTITY_NAME => 'landing-page-route-',
        ContentLayoutDefinition::ENTITY_NAME => 'content-layout-',
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

<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Feature;

/**
 * The typed data of a single feature an app declares in its manifest, stored as the
 * payload of one `app_feature` row.
 *
 * Implementations are plain value objects. Mapping between the object and the stored
 * payload is owned by the matching AppFeatureDefinition.
 *
 * @internal
 */
interface AppFeatureConfig
{
    /**
     * The name of the feature instance, eg the mcp tool name or the cookie name
     */
    public function getName(): string;
}

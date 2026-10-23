<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\MediaType;


/**
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
class SpatialObjectType extends MediaType implements SpatialMediaTypeInterface
{
    protected string $name = 'SPATIAL_OBJECT';
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Hydration\DataLoader;

/**
 * The semantics of one config key of a {@see LoaderConfigSpecification}: what the stored value names.
 *
 * @internal
 */
enum ConfigKeyKind: string
{
    case Literal = 'literal';                     // opaque value, interpreted only by the loader
    case PropertyReference = 'propertyReference'; // names an element property whose stored value feeds the loader
    case EntityName = 'entityName';               // names a registered DAL entity
}

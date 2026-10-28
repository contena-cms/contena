<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Xml\CustomFieldTypes;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class MultiEntitySelectField extends SingleEntitySelectField
{
    public const COMPONENT_NAME = 'sw-entity-multi-id-select';
}

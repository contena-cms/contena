<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class ManyToManyField extends AssociationField
{
    use RequiredTrait;

    protected string $type = 'many-to-many';

    protected string $onDelete = 'cascade';
}

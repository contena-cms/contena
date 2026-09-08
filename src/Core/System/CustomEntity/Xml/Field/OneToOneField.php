<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class OneToOneField extends AssociationField
{
    use RequiredTrait;

    protected string $type = 'one-to-one';
}

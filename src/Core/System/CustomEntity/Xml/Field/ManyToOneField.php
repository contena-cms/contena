<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;


/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class ManyToOneField extends AssociationField
{
    use RequiredTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

    protected string $type = 'many-to-one';
}

<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;


/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class OneToOneField extends AssociationField
{
    use RequiredTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

    protected string $type = 'one-to-one';
}

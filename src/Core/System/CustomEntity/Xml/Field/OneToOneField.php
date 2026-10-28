<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class OneToOneField extends AssociationField
{
    use RequiredTrait;

    protected string $type = 'one-to-one';
}

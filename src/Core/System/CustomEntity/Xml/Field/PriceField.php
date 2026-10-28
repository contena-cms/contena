<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class PriceField extends Field
{
    use RequiredTrait;

    protected string $type = 'price';
}

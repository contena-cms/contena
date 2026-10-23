<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;


/**
 * @internal
 */
class PriceField extends Field
{
    use RequiredTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

    protected string $type = 'price';
}

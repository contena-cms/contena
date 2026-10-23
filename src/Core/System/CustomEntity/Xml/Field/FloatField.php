<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;


/**
 * @internal
 */
class FloatField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'float';

    protected ?float $default = null;

    public function getDefault(): ?float
    {
        return $this->default;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;


/**
 * @internal
 */
class BoolField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'bool';

    protected ?bool $default = null;

    public function getDefault(): ?bool
    {
        return $this->default;
    }
}

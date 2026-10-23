<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;


/**
 * @internal
 */
class IntField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'int';

    protected ?int $default = null;

    public function getDefault(): ?int
    {
        return $this->default;
    }
}

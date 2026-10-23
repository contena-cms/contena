<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;


/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class DateField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'date';

    protected ?string $default = null;

    public function getDefault(): ?string
    {
        return $this->default;
    }
}

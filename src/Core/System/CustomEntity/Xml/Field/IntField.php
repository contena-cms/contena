<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

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

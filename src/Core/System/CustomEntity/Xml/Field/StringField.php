<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class StringField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'string';

    protected ?string $default = null;

    public function getDefault(): ?string
    {
        return $this->default;
    }
}

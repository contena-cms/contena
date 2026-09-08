<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class EmailField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;

    protected string $type = 'email';

    protected ?string $default = null;

    public function getDefault(): ?string
    {
        return $this->default;
    }
}

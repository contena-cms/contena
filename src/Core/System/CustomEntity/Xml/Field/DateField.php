<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
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

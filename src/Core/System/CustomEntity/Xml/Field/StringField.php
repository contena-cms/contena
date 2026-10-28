<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
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

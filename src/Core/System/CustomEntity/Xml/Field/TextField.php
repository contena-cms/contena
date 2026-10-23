<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;


/**
 * @internal
 */
class TextField extends Field
{
    use RequiredTrait;
    use TranslatableTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;
use Contena\Core\System\CustomEntity\Xml\Field\Traits\TranslatableTrait;

    protected bool $allowHtml = false;

    protected string $type = 'text';

    protected ?string $default = null;

    public function allowHtml(): bool
    {
        return $this->allowHtml;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }
}

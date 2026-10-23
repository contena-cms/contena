<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field\Traits;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
trait TranslatableTrait
{
    protected bool $translatable;

    public function isTranslatable(): bool
    {
        return $this->translatable;
    }
}

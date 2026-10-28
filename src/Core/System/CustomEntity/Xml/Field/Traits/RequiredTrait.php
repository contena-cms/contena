<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field\Traits;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
trait RequiredTrait
{
    protected bool $required = false;

    public function isRequired(): bool
    {
        return $this->required;
    }
}

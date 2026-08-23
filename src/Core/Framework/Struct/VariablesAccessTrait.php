<?php declare(strict_types=1);

namespace Contena\Core\Framework\Struct;

trait VariablesAccessTrait
{
    /**
     * @return array<string, mixed>
     */
    public function getVars(): array
    {
        return get_object_vars($this);
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<LicenseStruct>
 *
 * @codeCoverageIgnore
 */
class LicenseCollection extends Collection
{
    protected int $total = 0;

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    protected function getExpectedClass(): ?string
    {
        return LicenseStruct::class;
    }
}

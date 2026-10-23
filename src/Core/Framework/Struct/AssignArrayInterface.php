<?php declare(strict_types=1);

namespace Contena\Core\Framework\Struct;

use Contena\Core\Framework\Log\Package;

interface AssignArrayInterface
{
    /**
     * @param array<array-key, mixed> $options
     *
     * @return $this
     */
    public function assign(array $options);

    /**
     * @param array<array-key, mixed> $options
     */
    public function assignRecursive(array $options): static;
}

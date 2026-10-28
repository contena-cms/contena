<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
class CloneBehavior
{
    public function __construct(
        private readonly array $overwrites = [],
        private readonly bool $cloneChildren = true
    ) {
    }

    public function getOverwrites(): array
    {
        return $this->overwrites;
    }

    public function cloneChildren(): bool
    {
        return $this->cloneChildren;
    }
}

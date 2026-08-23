<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\InvalidatorStorage;

abstract class AbstractInvalidatorStorage
{
    /**
     * @param array<string> $tags
     */
    abstract public function store(array $tags): void;

    /**
     * @return list<string>
     */
    abstract public function loadAndDelete(): array;
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache;

use Symfony\Contracts\EventDispatcher\Event;

class InvalidateCacheEvent extends Event
{
    /**
     * @param array<string> $keys
     */
    public function __construct(protected array $keys)
    {
    }

    /**
     * @return array<string>
     */
    public function getKeys(): array
    {
        return $this->keys;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

class InvalidateBlogCache implements BlogChangedEventInterface
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        private readonly array $ids,
        public readonly bool $force = false
    ) {
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

interface BlogChangedEventInterface
{
    /**
     * @return list<string>
     */
    public function getIds(): array;
}

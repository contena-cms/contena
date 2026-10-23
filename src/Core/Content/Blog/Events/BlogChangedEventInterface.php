<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Framework\Log\Package;

interface BlogChangedEventInterface
{
    /**
     * @return list<string>
     */
    public function getIds(): array;
}

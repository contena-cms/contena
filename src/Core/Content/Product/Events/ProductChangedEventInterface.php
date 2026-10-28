<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Events;

use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductChangedEventInterface
{
    /**
     * @return list<string>
     */
    public function getIds(): array;
}

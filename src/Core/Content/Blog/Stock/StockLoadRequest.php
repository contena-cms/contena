<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Stock;

use Contena\Core\Framework\Log\Package;

class StockLoadRequest
{
    /**
     * @param array<string> $blogIds
     */
    public function __construct(public array $blogIds)
    {
    }
}

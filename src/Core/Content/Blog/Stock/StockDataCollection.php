<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Stock;

use Contena\Core\Framework\Log\Package;

class StockDataCollection
{
    /**
     * @var array<string, StockData>
     */
    private array $stocks = [];

    /**
     * @param array<StockData> $stocks
     */
    public function __construct(array $stocks)
    {
        foreach ($stocks as $stock) {
            $this->add($stock);
        }
    }

    public function add(StockData $stock): void
    {
        $this->stocks[$stock->blogId] = $stock;
    }

    public function getStockForBlogId(string $blogId): ?StockData
    {
        return $this->stocks[$blogId] ?? null;
    }

    /**
     * @return array<StockData>
     */
    public function all(): array
    {
        return $this->stocks;
    }
}

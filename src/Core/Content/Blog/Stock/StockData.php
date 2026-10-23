<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Stock;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

final class StockData extends Struct
{
    public function __construct(
        public readonly string $blogId,
        public readonly int $stock,
        public readonly bool $available,
        public readonly ?int $minPurchase = null,
        public readonly ?int $maxPurchase = null,
        public readonly ?bool $isCloseout = null,
    ) {
    }

    /**
     * @param array{blogId: string, stock: int, available: bool, minPurchase?: int, maxPurchase?: int, isCloseout?: bool, extraData?: array<mixed>} $info
     */
    public static function fromArray(array $info): self
    {
        return new self(
            $info['blogId'],
            $info['stock'],
            $info['available'],
            $info['minPurchase'] ?? null,
            $info['maxPurchase'] ?? null,
            $info['isCloseout'] ?? null,
        );
    }
}

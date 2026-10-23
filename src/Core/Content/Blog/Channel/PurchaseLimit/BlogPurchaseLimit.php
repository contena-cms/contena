<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\PurchaseLimit;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class BlogPurchaseLimit extends Struct
{
    public function __construct(
        protected string $blogId,
        protected int $minPurchase,
        protected int $purchaseSteps,
        protected int $maxPurchase,
        protected ?int $stock = null,
    ) {
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function getMinPurchase(): int
    {
        return $this->minPurchase;
    }

    public function getPurchaseSteps(): int
    {
        return $this->purchaseSteps;
    }

    public function getMaxPurchase(): int
    {
        return $this->maxPurchase;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function getApiAlias(): string
    {
        return 'blog_purchase_limit';
    }
}

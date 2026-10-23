<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Order;

use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Page\Page;

class AccountOrderPage extends Page
{
    /**
     * @var EntitySearchResult<OrderCollection>
     */
    protected EntitySearchResult $orders;

    protected ?string $deepLinkCode = null;

    /**
     * @return EntitySearchResult<OrderCollection>
     */
    public function getOrders(): EntitySearchResult
    {
        return $this->orders;
    }

    /**
     * @param EntitySearchResult<OrderCollection> $orders
     */
    public function setOrders(EntitySearchResult $orders): void
    {
        $this->orders = $orders;
    }

    public function getDeepLinkCode(): ?string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(?string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }
}

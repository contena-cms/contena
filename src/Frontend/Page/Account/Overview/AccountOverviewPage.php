<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\Overview;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Page;
use Contena\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPagelet;

#[Package('checkout')]
class AccountOverviewPage extends Page
{
    protected ?OrderEntity $newestOrder = null;

    protected CustomerEntity $customer;

    protected NewsletterAccountPagelet $newsletterAccountPagelet;

    public function setNewestOrder(OrderEntity $order): void
    {
        $this->newestOrder = $order;
    }

    public function getNewestOrder(): ?OrderEntity
    {
        return $this->newestOrder;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getNewsletterAccountPagelet(): NewsletterAccountPagelet
    {
        return $this->newsletterAccountPagelet;
    }

    public function setNewsletterAccountPagelet(NewsletterAccountPagelet $newsletterAccountPagelet): void
    {
        $this->newsletterAccountPagelet = $newsletterAccountPagelet;
    }
}

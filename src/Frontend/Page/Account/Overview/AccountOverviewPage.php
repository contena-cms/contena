<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Overview;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Page\Page;
use Contena\Frontend\Pagelet\Newsletter\Account\NewsletterAccountPagelet;

class AccountOverviewPage extends Page
{
    protected ?OrderEntity $newestOrder = null;

    protected UserEntity $user;

    protected NewsletterAccountPagelet $newsletterAccountPagelet;

    public function setNewestOrder(OrderEntity $order): void
    {
        $this->newestOrder = $order;
    }

    public function getNewestOrder(): ?OrderEntity
    {
        return $this->newestOrder;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
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

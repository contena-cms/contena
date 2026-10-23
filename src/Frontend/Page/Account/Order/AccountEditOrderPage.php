<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Order;

use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Page\Page;

class AccountEditOrderPage extends Page
{
    protected OrderEntity $order;

    protected PaymentMethodCollection $paymentMethods;

    protected ?string $selectedPaymentMethodId = null;

    protected PromotionCollection $activePromotions;

    protected ?string $deepLinkCode = null;

    protected bool $paymentChangeable = true;

    protected ?string $errorCode = null;

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getSelectedPaymentMethodId(): ?string
    {
        return $this->selectedPaymentMethodId;
    }

    public function setSelectedPaymentMethodId(?string $selectedPaymentMethodId): void
    {
        $this->selectedPaymentMethodId = $selectedPaymentMethodId;
    }

    public function getDeepLinkCode(): ?string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(?string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    public function getActivePromotions(): PromotionCollection
    {
        return $this->activePromotions;
    }

    public function setActivePromotions(PromotionCollection $activePromotions): void
    {
        $this->activePromotions = $activePromotions;
    }

    public function isPaymentChangeable(): bool
    {
        return $this->paymentChangeable;
    }

    public function setPaymentChangeable(bool $paymentChangeable): void
    {
        $this->paymentChangeable = $paymentChangeable;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): void
    {
        $this->errorCode = $errorCode;
    }
}

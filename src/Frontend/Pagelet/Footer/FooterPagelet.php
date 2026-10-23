<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Footer;

use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Pagelet\NavigationPagelet;

/**
 * @codeCoverageIgnore
 */
class FooterPagelet extends NavigationPagelet
{
    /**
     * @internal
     */
    public function __construct(
        ?Tree $navigation,
        protected CategoryCollection $serviceMenu,
        protected PaymentMethodCollection $paymentMethods,
        protected ShippingMethodCollection $shippingMethods,
    ) {
        parent::__construct($navigation);
    }

    public function getServiceMenu(): CategoryCollection
    {
        return $this->serviceMenu;
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->shippingMethods;
    }
}

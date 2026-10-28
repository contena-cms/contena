<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Event;

use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class GuestCustomerRegisterEvent extends CustomerRegisterEvent implements FlowEventAware
{
    final public const EVENT_NAME = 'checkout.customer.guest_register';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}

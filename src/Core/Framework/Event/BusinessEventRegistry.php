<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Contena\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Contena\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Contena\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Contena\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Contena\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Contena\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Contena\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Contena\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Contena\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Contena\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Contena\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Contena\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Contena\Core\Checkout\DocumentV2\Event\DocumentDeletedEvent;
use Contena\Core\Checkout\DocumentV2\Event\DocumentGeneratedEvent;
use Contena\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Contena\Core\Content\ContactForm\Event\ContactFormEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Contena\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Contena\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent;
use Contena\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Contena\Core\Content\ProductExport\Event\ProductExportLoggingEvent;
use Contena\Core\Content\RevocationRequest\Event\RevocationRequestEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;

#[Package('framework')]
class BusinessEventRegistry
{
    /**
     * @var list<class-string<FlowEventAware>>
     */
    private array $classes = [
        CustomerBeforeLoginEvent::class,
        CustomerLoginEvent::class,
        CustomerLogoutEvent::class,
        CustomerDeletedEvent::class,
        UserRecoveryRequestEvent::class,
        CheckoutOrderPlacedEvent::class,
        OrderPaymentMethodChangedEvent::class,
        DocumentGeneratedEvent::class,
        DocumentDeletedEvent::class,
        CustomerAccountRecoverRequestEvent::class,
        CustomerDoubleOptInRegistrationEvent::class,
        CustomerGroupRegistrationAccepted::class,
        CustomerGroupRegistrationDeclined::class,
        CustomerRegisterEvent::class,
        DoubleOptInGuestOrderEvent::class,
        GuestCustomerRegisterEvent::class,
        ContactFormEvent::class,
        ReviewFormEvent::class,
        MailBeforeSentEvent::class,
        MailBeforeValidateEvent::class,
        MailSentEvent::class,
        NewsletterConfirmEvent::class,
        NewsletterRegisterEvent::class,
        NewsletterUnsubscribeEvent::class,
        ProductExportLoggingEvent::class,
        CustomerPasswordChangedEvent::class,
        RevocationRequestEvent::class,
    ];

    /**
     * @param list<class-string<FlowEventAware>> $classes
     */
    public function addClasses(array $classes): void
    {
        $classes = array_values(array_unique(array_merge($this->classes, $classes)));

        $this->classes = $classes;
    }

    /**
     * @return list<class-string<FlowEventAware>>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }
}

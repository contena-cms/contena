<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\System\User\Event\UserAccountRecoverRequestEvent;
use Contena\Core\System\User\Event\UserBeforeLoginEvent;
use Contena\Core\System\User\Event\UserDeletedEvent;
use Contena\Core\System\User\Event\UserDoubleOptInRegistrationEvent;
use Contena\Core\System\User\Event\UserGroupRegistrationAccepted;
use Contena\Core\System\User\Event\UserGroupRegistrationDeclined;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\Event\UserLogoutEvent;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\System\User\Event\DoubleOptInGuestOrderEvent;
use Contena\Core\System\User\Event\GuestUserRegisterEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\Blog\Channel\Review\Event\ReviewFormEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryRequestEvent;

final class BusinessEvents
{
    public const CHECKOUT_CUSTOMER_BEFORE_LOGIN = UserBeforeLoginEvent::EVENT_NAME;

    public const CHECKOUT_CUSTOMER_LOGIN = UserLoginEvent::EVENT_NAME;

    public const CHECKOUT_CUSTOMER_LOGOUT = UserLogoutEvent::EVENT_NAME;

    public const CHECKOUT_CUSTOMER_DELETED = UserDeletedEvent::EVENT_NAME;

    public const USER_RECOVERY_REQUEST = AdminUserRecoveryRequestEvent::EVENT_NAME;

    public const CHECKOUT_ORDER_PLACED = CheckoutOrderPlacedEvent::EVENT_NAME;

    public const CHECKOUT_ORDER_PAYMENT_METHOD_CHANGED = OrderPaymentMethodChangedEvent::EVENT_NAME;

    public const DOCUMENT_GENERATION_COMPLETED = DocumentGeneratedEvent::EVENT_NAME;

    public const DOCUMENT_GENERATION_DELETED = DocumentDeletedEvent::EVENT_NAME;

    public const CUSTOMER_ACCOUNT_RECOVER_REQUEST = UserAccountRecoverRequestEvent::EVENT_NAME;

    public const CUSTOMER_DOUBLE_OPT_IN_REGISTRATION = UserDoubleOptInRegistrationEvent::EVENT_NAME;

    public const CUSTOMER_GROUP_REGISTRATION_ACCEPTED = UserGroupRegistrationAccepted::EVENT_NAME;

    public const CUSTOMER_GROUP_REGISTRATION_DECLINED = UserGroupRegistrationDeclined::EVENT_NAME;

    public const CUSTOMER_REGISTER = UserRegisterEvent::EVENT_NAME;

    public const DOUBLE_OPT_IN_GUEST_ORDER = DoubleOptInGuestOrderEvent::EVENT_NAME;

    public const GUEST_CUSTOMER_REGISTER = GuestUserRegisterEvent::EVENT_NAME;

    public const CONTACT_FORM = ContactFormEvent::EVENT_NAME;

    public const REVIEW_FORM = ReviewFormEvent::EVENT_NAME;

    public const MAIL_BEFORE_SENT = MailBeforeSentEvent::EVENT_NAME;

    public const MAIL_BEFORE_VALIDATE = MailBeforeValidateEvent::EVENT_NAME;

    public const MAIL_SENT = MailSentEvent::EVENT_NAME;

    public const NEWSLETTER_CONFIRM = NewsletterConfirmEvent::EVENT_NAME;

    public const NEWSLETTER_REGISTER = NewsletterRegisterEvent::EVENT_NAME;

    public const NEWSLETTER_UNSUBSCRIBE = NewsletterUnsubscribeEvent::EVENT_NAME;

    public const BLOG_EXPORT_LOGGING = BlogExportLoggingEvent::NAME;

    private function __construct()
    {
    }
}

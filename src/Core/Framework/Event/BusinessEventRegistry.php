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
use Contena\Core\System\User\Event\UserPasswordChangedEvent;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\System\User\Event\DoubleOptInGuestOrderEvent;
use Contena\Core\System\User\Event\GuestUserRegisterEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\Blog\Channel\Review\Event\ReviewFormEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryRequestEvent;

class BusinessEventRegistry
{
    /**
     * @var list<class-string<FlowEventAware>>
     */
    private array $classes = [
        UserBeforeLoginEvent::class,
        UserLoginEvent::class,
        UserLogoutEvent::class,
        UserDeletedEvent::class,
        AdminUserRecoveryRequestEvent::class,
        UserAccountRecoverRequestEvent::class,
        UserDoubleOptInRegistrationEvent::class,
        UserGroupRegistrationAccepted::class,
        UserGroupRegistrationDeclined::class,
        UserRegisterEvent::class,
        DoubleOptInGuestOrderEvent::class,
        GuestUserRegisterEvent::class,
        ReviewFormEvent::class,
        MailBeforeSentEvent::class,
        MailBeforeValidateEvent::class,
        MailSentEvent::class,
        UserPasswordChangedEvent::class,
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

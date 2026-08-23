<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\System\Member\Event\MemberAccountRecoverRequestEvent;
use Contena\Core\System\Member\Event\MemberBeforeLoginEvent;
use Contena\Core\System\Member\Event\MemberDeletedEvent;
use Contena\Core\System\Member\Event\MemberDoubleOptInRegistrationEvent;
use Contena\Core\System\Member\Event\MemberGroupRegistrationAccepted;
use Contena\Core\System\Member\Event\MemberGroupRegistrationDeclined;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Contena\Core\System\Member\Event\MemberRegisterEvent;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;

final class BusinessEvents
{
    public const string MEMBER_BEFORE_LOGIN = MemberBeforeLoginEvent::EVENT_NAME;

    public const string MEMBER_LOGIN = MemberLoginEvent::EVENT_NAME;

    public const string MEMBER_LOGOUT = MemberLogoutEvent::EVENT_NAME;

    public const string MEMBER_DELETED = MemberDeletedEvent::EVENT_NAME;

    public const string USER_RECOVERY_REQUEST = UserRecoveryRequestEvent::EVENT_NAME;

    public const string MEMBER_ACCOUNT_RECOVER_REQUEST = MemberAccountRecoverRequestEvent::EVENT_NAME;

    public const string MEMBER_DOUBLE_OPT_IN_REGISTRATION = MemberDoubleOptInRegistrationEvent::EVENT_NAME;

    public const string MEMBER_GROUP_REGISTRATION_ACCEPTED = MemberGroupRegistrationAccepted::EVENT_NAME;

    public const string MEMBER_GROUP_REGISTRATION_DECLINED = MemberGroupRegistrationDeclined::EVENT_NAME;

    public const string MEMBER_REGISTER = MemberRegisterEvent::EVENT_NAME;

    public const string MAIL_BEFORE_SENT = MailBeforeSentEvent::EVENT_NAME;

    public const string MAIL_BEFORE_VALIDATE = MailBeforeValidateEvent::EVENT_NAME;

    public const string MAIL_SENT = MailSentEvent::EVENT_NAME;

    private function __construct()
    {
    }
}

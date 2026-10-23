<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\Framework\Event\FlowEventAware;

/**
 * @codeCoverageIgnore
 */
class GuestUserRegisterEvent extends UserRegisterEvent implements FlowEventAware
{
    final public const EVENT_NAME = 'checkout.user.guest_register';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Examples;

use Contena\Core\System\User\Extension\UserRecoveryIsExpiredRouteExtension;
use Contena\Core\System\User\Channel\UserRecoveryIsExpiredResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Decides whether a recovery hash is expired from your own store instead of
 * the core user_recovery lookup.
 */
readonly class UserRecoveryIsExpiredRouteExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserRecoveryIsExpiredRouteExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(UserRecoveryIsExpiredRouteExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->data (the recovery hash), $event->context

        $event->result = new UserRecoveryIsExpiredResponse(false);

        // stop propagation so the core expiry check is skipped
        $event->stopPropagation();
    }
}

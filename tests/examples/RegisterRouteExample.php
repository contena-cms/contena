<?php declare(strict_types=1);

namespace Contena\Tests\Examples;

use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Extension\RegisterRouteExtension;
use Contena\Core\System\User\Channel\UserResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Takes over the registration — create the user in your own way (or
 * `throw` from here to abort it) instead of running the core registration.
 */
readonly class RegisterRouteExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            RegisterRouteExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(RegisterRouteExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->data, $event->context, $event->validateFrontendUrl

        $user = (new UserEntity())->assign(['id' => 'example']);

        $event->result = new UserResponse($user);

        // stop propagation so the core registration is skipped
        $event->stopPropagation();
    }
}

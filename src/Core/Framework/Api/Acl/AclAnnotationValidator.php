<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl;

use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class AclAnnotationValidator implements EventSubscriberInterface
{
    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['validate', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
        ];
    }

    public function validate(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        $privileges = $request->attributes->get(PlatformRequest::ATTRIBUTE_ACL);

        if (!$privileges) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        if (!$context instanceof Context) {
            throw ApiException::missingPrivileges([]);
        }

        foreach ($privileges as $privilege) {
            if (!$context->isAllowed($privilege)) {
                throw ApiException::missingPrivileges([$privilege]);
            }
        }
    }
}

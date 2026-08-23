<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\EventListener;

use Contena\Core\ChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class ResponseExceptionListener implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly bool $debug = false)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onKernelException', -1],
            ],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if ($event->getRequest()->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST)) {
            return;
        }

        $exception = $event->getThrowable();

        $event->setResponse(new ErrorResponseFactory()->getResponseFromException($exception, $this->debug));
    }
}

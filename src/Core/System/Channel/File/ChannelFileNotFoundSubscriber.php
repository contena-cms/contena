<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File;

use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextRequestRestorer;
use Contena\Core\System\Channel\File\Discovery\ChannelFile;
use Contena\Core\System\Channel\File\Loader\ChannelFileLoader;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serves configured channel files after normal routing missed.
 *
 * Explicit routes keep precedence because this subscriber only handles unresolved 404s. It validates the
 * public file path before resolving a missing channel context, so unrelated 404 pages stay cheap.
 *
 * @internal
 */
class ChannelFileNotFoundSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ChannelFileLoader $loader,
        private readonly ChannelFileRequestPathResolver $requestPathResolver,
        private readonly ChannelContextRequestRestorer $contextRestorer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onNotFound', -90],
        ];
    }

    public function onNotFound(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() !== Response::HTTP_NOT_FOUND) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            return;
        }

        if (!\in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_HEAD], true)) {
            return;
        }

        try {
            $templatePath = $this->requestPathResolver->buildTemplatePath(
                ChannelFile::DEFAULT_FILE_FAMILY,
                ltrim($request->getPathInfo(), '/'),
            );
        } catch (ChannelException) {
            return;
        }

        $context = $this->contextRestorer->restore($request);
        if ($context === null) {
            return;
        }

        $file = $this->loader->load($templatePath, $context);
        if ($file === null) {
            return;
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);

        $event->allowCustomResponseCode();
        $event->setResponse(new Response(
            $file->content,
            Response::HTTP_OK,
            ['content-type' => $file->contentType],
        ));
    }
}

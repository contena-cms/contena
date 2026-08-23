<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing\NotFound;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextRequestRestorer;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Contena\Frontend\Framework\Routing\Exception\ErrorRedirectRequestEvent;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class NotFoundSubscriber implements EventSubscriberInterface, ResetInterface
{
    private const string ALL_TAG = 'error-page';
    private const string SYSTEM_CONFIG_KEY = 'core.basicInformation.http404Page';

    /**
     * Catch the errors only once in a request cycle, otherwise we get an endless loop.
     */
    private bool $handled = false;

    private readonly string $sessionName;

    /**
     * @internal
     *
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        private readonly HttpKernelInterface $httpKernel,
        private readonly ChannelContextRequestRestorer $contextRestorer,
        private bool $kernelDebug,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        array $sessionOptions = []
    ) {
        $this->sessionName = $sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['onError', -100],
            ],
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function onError(ExceptionEvent $event): void
    {
        if ($this->kernelDebug || $this->handled) {
            return;
        }

        $this->handled = true;

        $request = $event->getRequest();

        $event->stopPropagation();

        $channelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID, '');
        $domainId = $request->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_ID, '');
        $languageId = $request->attributes->get(PlatformRequest::HEADER_LANGUAGE_ID, '');

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT)) {
            $this->contextRestorer->restore($request);
        }

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE)) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [FrontendRouteScope::ID]);
        }

        $is404StatusCode = $event->getThrowable() instanceof HttpException
            && $event->getThrowable()->getStatusCode() === Response::HTTP_NOT_FOUND;

        if (!$is404StatusCode) {
            /** @var Context|null $context */
            $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
            $event->setResponse($this->renderErrorPage($request, $event->getThrowable(), $context ?? Context::createDefaultContext()));

            return;
        }

        /** @var ChannelContext $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        $name = self::buildName($channelId, $domainId, $languageId);
        $key = $this->generateKey($channelId, $domainId, $languageId, $request, $context);

        $response = $this->cache->get($key, function (ItemInterface $item) use ($event, $name, $context, $request): Response {
            $response = $this->renderErrorPage($request, $event->getThrowable(), $context->getContext());

            $item->tag($this->generateTags($name, $event->getRequest(), $context));

            foreach ($response->headers->getCookies() as $cookie) {
                if ($cookie->getName() === $this->sessionName) {
                    $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
                }
            }

            return $response;
        });

        $event->setResponse($response);
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() !== self::SYSTEM_CONFIG_KEY) {
            return;
        }

        $this->cacheInvalidator->invalidate([self::ALL_TAG]);
    }

    public function reset(): void
    {
        $this->handled = false;
    }

    private static function buildName(string $channelId, string $domainId, string $languageId): string
    {
        return 'error-page-' . $channelId . $domainId . $languageId;
    }

    private function generateKey(string $channelId, string $domainId, string $languageId, Request $request, ChannelContext $context): string
    {
        $key = self::buildName($channelId, $domainId, $languageId) . $this->generator->getChannelContextHash($context);

        $event = new NotFoundPageCacheKeyEvent($key, $request, $context);

        $this->eventDispatcher->dispatch($event);

        return $event->getKey();
    }

    /**
     * @return array<string>
     */
    private function generateTags(string $name, Request $request, ChannelContext $context): array
    {
        $event = new NotFoundPageTagsEvent([$name, self::ALL_TAG], $request, $context);

        $this->eventDispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }

    /**
     * We enable HTTP cache for this request, so external reverse proxies can cache 404 pages too.
     */
    private function renderErrorPage(Request $request, \Throwable $exception, Context $context): Response
    {
        $errorRequest = $request->duplicate(null, null, [
            ...$request->attributes->all(),
            '_controller' => '\\Contena\\Frontend\\Controller\\ErrorController::error',
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            PlatformRequest::ATTRIBUTE_CAPTCHA => false,
            'exception' => $exception,
        ]);

        $this->eventDispatcher->dispatch(new ErrorRedirectRequestEvent($errorRequest, $exception, $context));

        return $this->httpKernel->handle($errorRequest, HttpKernelInterface::MAIN_REQUEST);
    }
}

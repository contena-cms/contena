<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Api;

use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ChannelApiResponseListener implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly StructEncoder $encoder,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private readonly MediaUrlPlaceholderHandlerInterface $mediaUrlPlaceholderHandler,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['encodeResponse', 10000],
        ];
    }

    public function encodeResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!$response instanceof ChannelApiResponse) {
            return;
        }

        $this->dispatch($event);

        $request = $event->getRequest();

        $fields = new ResponseFields(
            RequestParamHelper::get($request, 'includes', []),
            RequestParamHelper::get($request, 'excludes', []),
        );

        $encoded = $this->encoder->encode($response->getObject(), $fields);

        $jsonResponse = new JsonResponse(null, $response->getStatusCode(), $response->headers->all());
        $jsonResponse->setEncodingOptions(\JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES);
        $jsonResponse->setData($encoded);

        $content = $this->mediaUrlPlaceholderHandler->replace((string) $jsonResponse->getContent());

        $channelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        if ($channelContext instanceof ChannelContext) {
            $content = $this->seoUrlPlaceholderHandler->replace($content, '', $channelContext);
        }

        $jsonResponse->setContent($content);

        $event->setResponse($jsonResponse);
    }

    /**
     * Equivalent to `\Contena\Core\Framework\Routing\RouteEventSubscriber::render`
     */
    private function dispatch(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->attributes->has('_route')) {
            return;
        }

        $name = $request->attributes->get('_route') . '.encode';
        $this->dispatcher->dispatch($event, $name);
    }
}

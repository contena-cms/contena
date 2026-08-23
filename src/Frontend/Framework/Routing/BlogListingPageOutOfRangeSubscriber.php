<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Content\Blog\BlogException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class BlogListingPageOutOfRangeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if ($event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->attributes->has(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST)) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$throwable instanceof BlogException || !$throwable->is(BlogException::LISTING_PAGE_OUT_OF_RANGE)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->buildRedirectTarget($request), Response::HTTP_MOVED_PERMANENTLY));
    }

    private function buildRedirectTarget(Request $request): string
    {
        $originalUri = $request->attributes->get(RequestTransformer::ORIGINAL_REQUEST_URI);
        if (!\is_string($originalUri) || $originalUri === '') {
            $originalUri = $request->getRequestUri();
        }

        $parts = parse_url($originalUri);
        $path = \is_array($parts) && isset($parts['path']) ? (string) $parts['path'] : '/';
        $queryString = \is_array($parts) ? ($parts['query'] ?? '') : '';
        $params = [];
        if (\is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $params);
        }
        unset($params['p']);

        return $params === [] ? $path : $path . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }
}

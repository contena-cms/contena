<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Doctrine\DBAL\Connection;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves a Channel API request's language and domain settings from the configured domain URL.
 *
 * @internal
 */
class ChannelApiDomainResolver implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    public function __construct(
        private readonly Connection $connection,
        private readonly RouteScopeRegistry $routeScopeRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                'resolveDomain',
                KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_STORE_API_DOMAIN_RESOLVE,
            ],
        ];
    }

    public function resolveDomain(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $domainUrl = trim((string) $request->headers->get(PlatformRequest::HEADER_DOMAIN, ''));

        if ($domainUrl === '' || !$this->isRequestScoped($request, ChannelApiRouteScope::class)) {
            return;
        }

        $channelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID);
        if (!\is_string($channelId) || $channelId === '') {
            return;
        }

        $domain = $this->fetchDomain($channelId, $domainUrl);
        if ($domain === null) {
            throw RoutingException::channelDomainNotFound($domainUrl);
        }

        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_ID, $domain['id']);
        $request->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $domain['snippetSetId']);

        if ($request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, '') === '') {
            $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $domain['languageId']);
        }
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    /**
     * @return array{id: string, languageId: string, snippetSetId: string}|null
     */
    private function fetchDomain(string $channelId, string $domainUrl): ?array
    {
        $domain = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(id)) AS id, LOWER(HEX(language_id)) AS languageId, LOWER(HEX(snippet_set_id)) AS snippetSetId
             FROM channel_domain
             WHERE channel_id = :channelId
               AND (url = :url OR url = CONCAT(:url, \'/\'))',
            [
                'channelId' => Uuid::fromHexToBytes($channelId),
                'url' => rtrim($domainUrl, '/'),
            ]
        );

        if ($domain === false) {
            return null;
        }

        return [
            'id' => (string) $domain['id'],
            'languageId' => (string) $domain['languageId'],
            'snippetSetId' => (string) $domain['snippetSetId'],
        ];
    }
}

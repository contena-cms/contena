<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Channel;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Cookie\CookieException;
use Contena\Core\Content\Cookie\Event\CookieConsentLoggedEvent;
use Contena\Core\Defaults;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Persists anonymous cookie consent decisions of frontend visitors so system
 * operators can demonstrate that consent was obtained (GDPR Recital 42).
 *
 * Alongside every log entry, a snapshot of the current cookie banner
 * configuration is stored once per configuration hash, preserving what the
 * banner looked like when the consent was given.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class CookieConsentLogRoute extends AbstractCookieConsentLogRoute
{
    final public const ACTION_ACCEPT_ALL = 'accept_all';
    final public const ACTION_ACCEPT_REQUIRED = 'accept_required';
    final public const ACTION_ACCEPT_SELECTED = 'accept_selected';

    private const VALID_ACTIONS = [
        self::ACTION_ACCEPT_ALL,
        self::ACTION_ACCEPT_REQUIRED,
        self::ACTION_ACCEPT_SELECTED,
    ];

    private const MAX_ACCEPTED_GROUPS = 100;
    private const MAX_STRING_LENGTH = 255;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCookieRoute $cookieRoute,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getDecorated(): AbstractCookieConsentLogRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/cookie-consent-log', name: 'channel-api.cookie.consent-log', methods: [Request::METHOD_POST])]
    public function log(Request $request, ChannelContext $channelContext): NoContentResponse
    {
        $payload = $this->validatePayload($request);

        $currentConfig = $this->cookieRoute->getCookieGroups($request, $channelContext);

        // The client sends the hash of the configuration it rendered. It normally matches the
        // current one, but may be stale when the banner changed after page load. The log entry
        // keeps the client hash as evidence of what the visitor actually saw.
        $configHash = $payload['cookieConfigHash'] ?? $currentConfig->getHash();

        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $channelId = $channelContext->getChannelId();
        $languageId = $channelContext->getLanguageId();
        $tenantId = $channelContext->getContext()->getTenantId();

        $this->connection->transactional(function () use ($payload, $currentConfig, $configHash, $now, $channelId, $languageId, $tenantId): void {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO `cookie_consent_config_version`
                    (`id`, `tenant_id`, `config_hash`, `channel_id`, `language_id`, `cookie_groups`, `created_at`)
                VALUES
                    (:id, :tenantId, :configHash, :channelId, :languageId, :cookieGroups, :createdAt)',
                [
                    'id' => Uuid::randomBytes(),
                    'tenantId' => $tenantId === null ? null : Uuid::fromHexToBytes($tenantId),
                    'configHash' => $currentConfig->getHash(),
                    'channelId' => Uuid::fromHexToBytes($channelId),
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'cookieGroups' => json_encode($currentConfig->getCookieGroups(), \JSON_THROW_ON_ERROR),
                    'createdAt' => $now,
                ],
            );

            $this->connection->executeStatement(
                'INSERT INTO `cookie_consent_log`
                    (`id`, `tenant_id`, `channel_id`, `language_id`, `consent_action`, `accepted_groups`, `config_hash`, `created_at`)
                VALUES
                    (:id, :tenantId, :channelId, :languageId, :consentAction, :acceptedGroups, :configHash, :createdAt)',
                [
                    'id' => Uuid::randomBytes(),
                    'tenantId' => $tenantId === null ? null : Uuid::fromHexToBytes($tenantId),
                    'channelId' => Uuid::fromHexToBytes($channelId),
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'consentAction' => $payload['consentAction'],
                    'acceptedGroups' => json_encode($payload['acceptedGroups'], \JSON_THROW_ON_ERROR),
                    'configHash' => $configHash,
                    'createdAt' => $now,
                ],
            );
        });

        $this->eventDispatcher->dispatch(new CookieConsentLoggedEvent(
            consentAction: $payload['consentAction'],
            acceptedGroups: $payload['acceptedGroups'],
            configHash: $configHash,
            channelId: $channelId,
            languageId: $languageId,
        ));

        return new NoContentResponse();
    }

    /**
     * The request body is parsed manually because the frontend sends it via
     * navigator.sendBeacon, which cannot guarantee a JSON content type header.
     *
     * @return array{consentAction: string, acceptedGroups: list<string>, cookieConfigHash?: string}
     */
    private function validatePayload(Request $request): array
    {
        try {
            $data = json_decode($request->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw CookieException::invalidConsentLogPayload('body must be valid JSON');
        }

        if (!\is_array($data)) {
            throw CookieException::invalidConsentLogPayload('body must be a JSON object');
        }

        $consentAction = $data['consentAction'] ?? null;
        if (!\is_string($consentAction) || !\in_array($consentAction, self::VALID_ACTIONS, true)) {
            throw CookieException::invalidConsentLogPayload(
                \sprintf('consentAction must be one of: %s', implode(', ', self::VALID_ACTIONS)),
            );
        }

        $acceptedGroups = $data['acceptedGroups'] ?? null;
        if (!\is_array($acceptedGroups) || !array_is_list($acceptedGroups) || \count($acceptedGroups) > self::MAX_ACCEPTED_GROUPS) {
            throw CookieException::invalidConsentLogPayload(
                \sprintf('acceptedGroups must be a list with at most %d entries', self::MAX_ACCEPTED_GROUPS),
            );
        }

        foreach ($acceptedGroups as $group) {
            if (!\is_string($group) || $group === '' || mb_strlen($group) > self::MAX_STRING_LENGTH) {
                throw CookieException::invalidConsentLogPayload('acceptedGroups must contain non-empty strings');
            }
        }

        $payload = [
            'consentAction' => $consentAction,
            'acceptedGroups' => $acceptedGroups,
        ];

        $cookieConfigHash = $data['cookieConfigHash'] ?? null;
        if ($cookieConfigHash !== null) {
            if (!\is_string($cookieConfigHash) || $cookieConfigHash === '' || mb_strlen($cookieConfigHash) > self::MAX_STRING_LENGTH) {
                throw CookieException::invalidConsentLogPayload('cookieConfigHash must be a non-empty string');
            }

            $payload['cookieConfigHash'] = $cookieConfigHash;
        }

        return $payload;
    }
}

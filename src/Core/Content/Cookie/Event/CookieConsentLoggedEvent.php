<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Event;

/**
 * Dispatched whenever a frontend visitor's cookie consent decision was logged.
 *
 * Intentionally separate from the admin consent events
 * (ConsentAcceptedEvent / ConsentRevokedEvent): it is anonymous, high-volume
 * and does not write to the `consent_log` table.
 *
 * @codeCoverageIgnore
 */
readonly class CookieConsentLoggedEvent
{
    /**
     * @param list<string> $acceptedGroups technical names of the accepted cookie groups
     */
    public function __construct(
        public string $consentAction,
        public array $acceptedGroups,
        public string $configHash,
        public string $channelId,
        public string $languageId,
    ) {
    }
}

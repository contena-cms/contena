<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ConsentLog;

/**
 * Discards every decision. The default: consent logging is opt-in, and a system with a
 * third-party consent manager keeps it off.
 *
 * @internal
 */
final class NullCookieConsentLogStorage extends AbstractCookieConsentLogStorage
{
    public const string NAME = 'none';

    public function log(CookieConsentRecord $record): void
    {
    }

    public function snapshot(CookieConsentConfigSnapshot $snapshot): void
    {
    }

    public function cleanup(\DateTimeImmutable $before): void
    {
    }

    public function iterate(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $channelId = null): iterable
    {
        return [];
    }
}

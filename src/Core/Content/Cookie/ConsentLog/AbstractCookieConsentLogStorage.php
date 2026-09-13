<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\ConsentLog;

/**
 * Where cookie consent decisions are kept.
 *
 * The shipped default discards decisions. A system that enables consent logging can
 * use the database or private filesystem storage, or implement this class, tag the
 * service with `contena.cookie_consent.log_storage` and select it via
 * `contena.cookie_consent.log_storage` in the bundle configuration.
 */
abstract class AbstractCookieConsentLogStorage
{
    /**
     * Persists one consent decision.
     */
    abstract public function log(CookieConsentRecord $record): void;

    /**
     * Persists the banner configuration a decision refers to. Called before every
     * `log()`, so it has to be cheap when the hash is already known.
     */
    abstract public function snapshot(CookieConsentConfigSnapshot $snapshot): void;

    /**
     * Deletes decisions recorded before the given point in time.
     */
    abstract public function cleanup(\DateTimeImmutable $before): void;

    /**
     * Decisions recorded from `$from` (inclusive) to `$to` (exclusive), oldest first.
     *
     * @return iterable<CookieConsentRecord>
     */
    abstract public function iterate(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $channelId = null): iterable;
}

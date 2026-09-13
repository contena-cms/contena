<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\ConsentLog;

use Contena\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Contena\Core\Content\Cookie\ConsentLog\NullCookieConsentLogStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(NullCookieConsentLogStorage::class)]
class NullCookieConsentLogStorageTest extends TestCase
{
    public function testItDiscardsEverything(): void
    {
        $storage = new NullCookieConsentLogStorage();
        $now = new \DateTimeImmutable('2026-07-13 12:00:00');

        $storage->snapshot(new CookieConsentConfigSnapshot('data-scope-id', 'hash', [], $now));
        $storage->log(new CookieConsentRecord(
            dataScopeId: 'data-scope-id',
            consentId: 'consent-id',
            consentAction: CookieConsentAction::ACCEPT_ALL,
            groupDecisions: [],
            acceptedCookies: [],
            configHash: 'hash',
            channelId: 'channel-id',
            languageId: 'language-id',
            createdAt: $now,
        ));
        $storage->cleanup($now);

        static::assertSame([], [...$storage->iterate(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2030-01-01'))]);
    }
}

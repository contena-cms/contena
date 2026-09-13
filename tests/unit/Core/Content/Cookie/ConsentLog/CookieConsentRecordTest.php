<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\ConsentLog;

use Contena\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CookieConsentRecord::class)]
class CookieConsentRecordTest extends TestCase
{
    public function testARecordSerializesToPlainValues(): void
    {
        $record = new CookieConsentRecord(
            dataScopeId: 'data-scope-id',
            consentId: 'consent-id',
            consentAction: CookieConsentAction::ACCEPT_SELECTED,
            groupDecisions: ['cookie.groupStatistical' => CookieConsentDecision::PARTIAL],
            acceptedCookies: ['lorem'],
            configHash: 'hash',
            channelId: 'channel-id',
            languageId: 'language-id',
            createdAt: new \DateTimeImmutable('2026-07-13 12:00:00.123', new \DateTimeZone('UTC')),
        );

        static::assertSame([
            'dataScopeId' => 'data-scope-id',
            'consentId' => 'consent-id',
            'consentAction' => 'accept_selected',
            'groupDecisions' => ['cookie.groupStatistical' => 'partial'],
            'acceptedCookies' => ['lorem'],
            'configHash' => 'hash',
            'channelId' => 'channel-id',
            'languageId' => 'language-id',
            'createdAt' => '2026-07-13T12:00:00.123+00:00',
        ], $record->jsonSerialize());
    }
}

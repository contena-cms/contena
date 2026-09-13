<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\ConsentLog;

use Contena\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CookieConsentConfigSnapshot::class)]
class CookieConsentConfigSnapshotTest extends TestCase
{
    public function testASnapshotSerializesToPlainValues(): void
    {
        $snapshot = new CookieConsentConfigSnapshot(
            dataScopeId: 'data-scope-id',
            configHash: 'hash',
            cookieGroups: [['technicalName' => 'cookie.groupRequired']],
            createdAt: new \DateTimeImmutable('2026-07-13 12:00:00', new \DateTimeZone('UTC')),
        );

        static::assertSame([
            'dataScopeId' => 'data-scope-id',
            'configHash' => 'hash',
            'cookieGroups' => [['technicalName' => 'cookie.groupRequired']],
            'createdAt' => '2026-07-13T12:00:00.000+00:00',
        ], $snapshot->jsonSerialize());
    }
}

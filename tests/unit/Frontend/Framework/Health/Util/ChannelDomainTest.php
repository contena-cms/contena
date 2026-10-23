<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomain;

/**
 * @internal
 */
#[CoversClass(ChannelDomain::class)]
class ChannelDomainTest extends TestCase
{
    public function testCreate(): void
    {
        $channelId = 'test-sales-channel-id';
        $url = 'http://localhost:8000';

        $domain = ChannelDomain::create($channelId, $url, 'domain-id', 'language-id', 'currency-id');

        static::assertSame($channelId, $domain->channelId);
        static::assertSame($url, $domain->url);
        // the readiness checks build their lookup context from these, so they travel with the URL
        static::assertSame('domain-id', $domain->id);
        static::assertSame('language-id', $domain->languageId);
        static::assertSame('currency-id', $domain->currencyId);
    }
}

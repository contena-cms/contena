<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\SystemCheck\Util;

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
        $channelId = 'test-channel-id';
        $url = 'http://localhost:8000';

        $domain = ChannelDomain::create($channelId, $url);

        static::assertSame($channelId, $domain->channelId);
        static::assertSame($url, $domain->url);
    }
}

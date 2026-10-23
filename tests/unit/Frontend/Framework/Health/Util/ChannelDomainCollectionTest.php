<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Health\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomain;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainCollection;

/**
 * @internal
 */
#[CoversClass(ChannelDomainCollection::class)]
class ChannelDomainCollectionTest extends TestCase
{
    public function testCreate(): void
    {
        $domain1 = ChannelDomain::create('test-sales-channel-id-1', 'http://localhost:8000', 'domain-1', 'language-1', 'currency-1');
        $domain2 = ChannelDomain::create('test-sales-channel-id-2', 'http://localhost:8001', 'domain-2', 'language-2', 'currency-2');

        $collection = new ChannelDomainCollection([$domain1, $domain2]);

        static::assertCount(2, $collection);

        $domain = $collection->get('test-sales-channel-id-1');
        static::assertInstanceOf(ChannelDomain::class, $domain);
        static::assertSame('http://localhost:8000', $domain->url);

        $domain = $collection->get('test-sales-channel-id-2');
        static::assertInstanceOf(ChannelDomain::class, $domain);
        static::assertSame('http://localhost:8001', $domain->url);
    }
}

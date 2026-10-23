<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\File\ResolvedUrl;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ResolvedUrl::class)]
class ResolvedUrlTest extends TestCase
{
    public function testItExposesHostAndIp(): void
    {
        $resolved = new ResolvedUrl('media.example.com', '93.184.216.34');

        static::assertSame('media.example.com', $resolved->host);
        static::assertSame('93.184.216.34', $resolved->ip);
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Manifest\Xml\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Cookie\Cookies;

/**
 * @internal
 */
#[CoversClass(Cookies::class)]
class CookiesTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getCookies());
        static::assertCount(2, $manifest->getCookies()->getCookies());
    }
}

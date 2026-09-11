<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Manifest\Xml\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\Frontend;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Frontend::class)]
class FrontendTest extends TestCase
{
    public function testFromXml(): void
    {
        $frontend = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml')->getFrontend();

        static::assertNotNull($frontend);
        static::assertSame(100, $frontend->getTemplateLoadPriority());

        $seoUrls = $frontend->getSeoUrls();
        static::assertCount(2, $seoUrls);
        static::assertSame('imprint', $seoUrls[0]->getName());
        static::assertSame('blog-detail', $seoUrls[1]->getName());
    }

    public function testFromXmlWithoutSeoUrls(): void
    {
        $frontend = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test-manifest-withoutShippingMethods.xml')->getFrontend();

        static::assertNotNull($frontend);
        static::assertSame(100, $frontend->getTemplateLoadPriority());
        static::assertSame([], $frontend->getSeoUrls());
    }
}

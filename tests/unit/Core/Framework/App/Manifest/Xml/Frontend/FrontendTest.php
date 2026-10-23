<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Manifest\Xml\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\EntitySeoUrl;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\SeoUrl;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\Frontend;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[CoversClass(Frontend::class)]
class FrontendTest extends TestCase
{
    public function testFromXmlReadsTemplateLoadPriorityAndBothSeoUrlListsInDeclarationOrder(): void
    {
        $frontend = $this->parse(<<<'XML'
            <frontend>
                <template-load-priority>100</template-load-priority>
                <seo-url name="imprint">
                    <path>imprint</path>
                </seo-url>
                <entity-seo-url name="blog-detail" entity="ce_blog">
                    <default-template>blog/{{ ceBlog.translated.title }}</default-template>
                </entity-seo-url>
                <seo-url name="blog">
                    <path>blog</path>
                </seo-url>
                <entity-seo-url name="author-detail" entity="ce_author">
                    <default-template>author/{{ ceAuthor.name }}</default-template>
                </entity-seo-url>
            </frontend>
            XML);

        static::assertSame(100, $frontend->getTemplateLoadPriority());
        static::assertSame(
            ['imprint', 'blog'],
            array_map(static fn (SeoUrl $seoUrl): string => $seoUrl->getName(), $frontend->getSeoUrls())
        );
        static::assertSame(
            ['blog-detail', 'author-detail'],
            array_map(static fn (EntitySeoUrl $seoUrl): string => $seoUrl->getName(), $frontend->getEntitySeoUrls())
        );
    }

    public function testFromXmlWithoutChildrenFallsBackToDefaults(): void
    {
        $frontend = $this->parse('<frontend/>');

        static::assertSame(0, $frontend->getTemplateLoadPriority());
        static::assertSame([], $frontend->getSeoUrls());
        static::assertSame([], $frontend->getEntitySeoUrls());
    }

    private function parse(string $xml): Frontend
    {
        $element = XmlUtils::parse($xml)->documentElement;
        static::assertInstanceOf(\DOMElement::class, $element);

        return Frontend::fromXml($element);
    }
}

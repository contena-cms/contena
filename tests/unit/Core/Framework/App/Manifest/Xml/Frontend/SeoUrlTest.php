<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Manifest\Xml\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\SeoUrl;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SeoUrl::class)]
class SeoUrlTest extends TestCase
{
    public function testStaticSeoUrlFromXml(): void
    {
        $seoUrl = $this->loadSeoUrls()['imprint'];

        static::assertSame('imprint', $seoUrl->getName());
        static::assertSame('imprint', $seoUrl->getHook());
        static::assertNull($seoUrl->getEntity());
        static::assertNull($seoUrl->getDefaultTemplate());
        static::assertSame(['en-GB' => 'Imprint', 'de-DE' => 'Impressum'], $seoUrl->getLabel());
        static::assertSame(['en-GB' => 'imprint', 'de-DE' => 'impressum'], $seoUrl->getPath());
    }

    public function testEntityBoundSeoUrlFromXml(): void
    {
        $seoUrl = $this->loadSeoUrls()['blog-detail'];

        static::assertSame('blog-detail', $seoUrl->getName());
        static::assertSame('blog-detail', $seoUrl->getHook());
        static::assertSame('ce_blog', $seoUrl->getEntity());
        static::assertSame('blog/{{ ceBlog.translated.title }}', $seoUrl->getDefaultTemplate());
        static::assertSame(['en-GB' => 'Blog post'], $seoUrl->getLabel());
        static::assertSame([], $seoUrl->getPath());
    }

    public function testHookDefaultsToTheName(): void
    {
        $seoUrl = SeoUrl::fromArray(['name' => 'blog-overview']);

        static::assertSame('blog-overview', $seoUrl->getHook());
    }

    public function testNameIsRequired(): void
    {
        $this->expectExceptionObject(AppException::invalidArgument('name must not be empty'));

        SeoUrl::fromArray(['hook' => 'imprint']);
    }

    public function testToArrayReturnsTheStaticPersistencePayload(): void
    {
        $payload = $this->loadSeoUrls()['imprint']->toArray('en-GB');

        static::assertSame([
            'name' => 'imprint',
            'hook' => 'imprint',
            'label' => ['en-GB' => 'Imprint', 'de-DE' => 'Impressum'],
            'defaultTemplate' => null,
            'entityName' => null,
            'paths' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
        ], $payload);
    }

    public function testToArrayAddsTheMissingDefaultLocaleTranslation(): void
    {
        $payload = $this->loadSeoUrls()['blog-detail']->toArray('de-DE');

        static::assertSame([
            'name' => 'blog-detail',
            'hook' => 'blog-detail',
            'label' => ['en-GB' => 'Blog post', 'de-DE' => 'Blog post'],
            'defaultTemplate' => 'blog/{{ ceBlog.translated.title }}',
            'entityName' => 'ce_blog',
            'paths' => null,
        ], $payload);
    }

    /**
     * @return array<string, SeoUrl>
     */
    private function loadSeoUrls(): array
    {
        $frontend = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml')->getFrontend();
        static::assertNotNull($frontend);

        $seoUrls = [];
        foreach ($frontend->getSeoUrls() as $seoUrl) {
            $seoUrls[$seoUrl->getName()] = $seoUrl;
        }

        return $seoUrls;
    }
}

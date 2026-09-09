<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Template;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Template\TemplateLoader;
use Contena\Core\Framework\Util\Filesystem;
use Contena\Core\Test\Stub\App\StaticSourceResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TemplateLoader::class)]
class TemplateLoaderTest extends TestCase
{
    private Manifest $manifest;

    protected function setUp(): void
    {
        $this->manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
    }

    public function testGetTemplatePathsForApp(): void
    {
        $templateLoader = new TemplateLoader(
            new StaticSourceResolver([
                'test' => new Filesystem(__DIR__ . '/../Manifest/_fixtures/test'),
            ])
        );

        $templates = $templateLoader->getTemplatePathsForApp($this->manifest);
        \sort($templates);

        static::assertSame(
            [
                'components/Demo/Badge.html.twig',
                'files/agentic/.well-known/ucp.json.twig',
                'frontend/layout/header/header.html.twig',
                'frontend/layout/header/logo.html.twig',
                'frontend/page/sitemap/sitemap.xml.twig',
            ],
            $templates
        );
    }

    public function testGetTemplatePathsForAppWhenViewDirDoesntExist(): void
    {
        $templateLoader = new TemplateLoader(new StaticSourceResolver([]));

        static::assertSame(
            [],
            $templateLoader->getTemplatePathsForApp($this->manifest)
        );
    }

    public function testGetTemplateContent(): void
    {
        $templateLoader = new TemplateLoader(
            new StaticSourceResolver([
                'test' => new Filesystem(__DIR__ . '/../Manifest/_fixtures/test'),
            ])
        );

        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/Resources/views/frontend/layout/header/logo.html.twig',
            $templateLoader->getTemplateContent('frontend/layout/header/logo.html.twig', $this->manifest)
        );
    }

    public function testGetTemplateContentThrowsOnNotFoundFile(): void
    {
        $templateLoader = new TemplateLoader(new StaticSourceResolver([]));

        static::expectException(\RuntimeException::class);
        $templateLoader->getTemplateContent('does/not/exist', $this->manifest);
    }
}

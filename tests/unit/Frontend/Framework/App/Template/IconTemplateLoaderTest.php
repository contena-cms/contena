<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\App\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Template\TemplateLoader;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Util\Filesystem;
use Contena\Core\Test\Stub\App\StaticSourceResolver;
use Contena\Frontend\Framework\App\Template\IconTemplateLoader;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[CoversClass(IconTemplateLoader::class)]
class IconTemplateLoaderTest extends TestCase
{
    private IconTemplateLoader $templateLoader;

    private Manifest $manifest;

    protected function setUp(): void
    {
        $this->manifest = Manifest::createFromXmlFile(__DIR__ . '/../../../Theme/fixtures/Apps/theme/manifest.xml');

        $sourceResolver = new StaticSourceResolver([
            'SwagTheme' => new Filesystem(__DIR__ . '/../../../Theme/fixtures/Apps/theme'),
        ]);

        $this->templateLoader = new IconTemplateLoader(
            new TemplateLoader($sourceResolver),
            new FrontendPluginConfigurationFactory(
                static::createStub(KernelPluginLoader::class),
                $sourceResolver,
                new SymfonyFilesystem(),
            ),
            $sourceResolver,
        );
    }

    public function testGetTemplatePathsForAppReturnsIconPaths(): void
    {
        $templates = $this->templateLoader->getTemplatePathsForApp($this->manifest);
        \sort($templates);

        static::assertSame(
            ['app/frontend/src/assets/icon-pack/custom-icons/activity.svg', 'frontend/layout/header/logo.html.twig'],
            $templates
        );
    }

    public function testGetTemplateContentForAppReturnsIconPaths(): void
    {
        static::assertStringEqualsFile(
            __DIR__ . '/../../../Theme/fixtures/Apps/theme/Resources/app/frontend/src/assets/icon-pack/custom-icons/activity.svg',
            $this->templateLoader->getTemplateContent('app/frontend/src/assets/icon-pack/custom-icons/activity.svg', $this->manifest)
        );
    }
}

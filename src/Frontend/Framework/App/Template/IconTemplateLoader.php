<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\App\Template;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\App\Template\AbstractTemplateLoader;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Framework\FrontendFrameworkException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
class IconTemplateLoader extends AbstractTemplateLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractTemplateLoader $inner,
        private readonly AbstractFrontendPluginConfigurationFactory $frontendPluginConfigurationFactory,
        private readonly SourceResolver $sourceResolver,
    ) {
    }

    public function getTemplatePathsForApp(Manifest $app): array
    {
        $viewPaths = $this->inner->getTemplatePathsForApp($app);

        $fs = $this->sourceResolver->filesystemForManifest($app);

        if (!$fs->has('Resources')) {
            return $viewPaths;
        }

        $frontendConfig = $this->frontendPluginConfigurationFactory->createFromApp($app->getMetadata()->getName(), '');

        if (!$frontendConfig->getIconSets()) {
            return $viewPaths;
        }

        $finder = new Finder();
        $finder->files()
            ->in($fs->path('Resources'))
            ->name(['*.html.twig', '*.svg'])
            ->path(array_values($frontendConfig->getIconSets()))
            ->ignoreUnreadableDirs();

        // return file paths relative to Resources/views directory
        $iconPaths = array_values(array_map(static function (SplFileInfo $file): string {
            return $file->getRelativePathname();
        }, iterator_to_array($finder)));

        return [
            ...array_values($viewPaths),
            ...$iconPaths,
        ];
    }

    public function getTemplateContent(string $path, Manifest $app): string
    {
        if (strrpos($path, '.svg') !== \strlen($path) - 4) {
            return $this->inner->getTemplateContent($path, $app);
        }

        $fs = $this->sourceResolver->filesystemForManifest($app);

        if (!$fs->has('Resources', $path)) {
            throw FrontendFrameworkException::appTemplateFileNotReadable($fs->path('Resources', $path));
        }

        return $fs->read('Resources', $path);
    }
}

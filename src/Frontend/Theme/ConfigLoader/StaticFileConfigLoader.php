<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\ConfigLoader;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\File;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;

class StaticFileConfigLoader extends AbstractConfigLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function getDecorated(): AbstractConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $themeId, Context $context): FrontendPluginConfiguration
    {
        $path = \sprintf('theme-config/%s.json', $themeId);

        if (!$this->filesystem->fileExists($path)) {
            throw ThemeException::configNotFound($path);
        }

        $fileObject = json_decode($this->filesystem->read($path), true, 512, \JSON_THROW_ON_ERROR);
        $fileObject = $this->prepareCollections($fileObject);

        $config = new FrontendPluginConfiguration('');
        $config->assign($fileObject);

        return $config;
    }

    /**
     * @param array<string, mixed> $fileObject
     *
     * @return array<string, mixed>
     */
    private function prepareCollections(array $fileObject): array
    {
        $fileObject['styleFiles'] = array_map(static fn (array $file) => new File('')->assign($file), $fileObject['styleFiles']);
        $fileObject['scriptFiles'] = array_map(static fn (array $file) => new File('')->assign($file), $fileObject['scriptFiles']);

        $fileObject['styleFiles'] = new FileCollection($fileObject['styleFiles']);
        $fileObject['scriptFiles'] = new FileCollection($fileObject['scriptFiles']);

        return $fileObject;
    }
}

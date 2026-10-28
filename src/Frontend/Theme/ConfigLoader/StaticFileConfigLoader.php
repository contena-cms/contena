<?php
declare(strict_types=1);

namespace Contena\Storefront\Theme\ConfigLoader;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\File;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

#[Package('discovery')]
class StaticFileConfigLoader extends AbstractConfigLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }



    private function prepareCollections(array $fileObject): array
    {
        $fileObject['styleFiles'] = array_map(static fn (array $file) => (new File(''))->assign($file), $fileObject['styleFiles']);

        $fileObject['scriptFiles'] = array_map(static fn (array $file) => (new File(''))->assign($file), $fileObject['scriptFiles']);

        $fileObject['styleFiles'] = new FileCollection($fileObject['styleFiles']);
        $fileObject['scriptFiles'] = new FileCollection($fileObject['scriptFiles']);

        return $fileObject;
    }
}

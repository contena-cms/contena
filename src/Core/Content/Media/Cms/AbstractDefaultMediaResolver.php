<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Cms;

use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class AbstractDefaultMediaResolver
{
    abstract public function getDecorated(): AbstractDefaultMediaResolver;

    abstract public function getDefaultCmsMediaEntity(string $mediaAssetFilePath): ?MediaEntity;
}

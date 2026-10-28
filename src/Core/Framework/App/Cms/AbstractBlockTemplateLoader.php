<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Cms;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
abstract class AbstractBlockTemplateLoader
{
    abstract public function getTemplateForBlock(CmsExtensions $cmsExtensions, string $blockName): string;

    abstract public function getStylesForBlock(CmsExtensions $cmsExtensions, string $blockName): string;
}

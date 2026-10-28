<?php declare(strict_types=1);

namespace Contena\Storefront\Theme;

use Contena\Core\Framework\Log\Package;

/**
 * @internal - may be changed in the future
 */
#[Package('discovery')]
abstract class AbstractScssCompiler
{
    abstract public function compileString(
        AbstractCompilerConfiguration $config,
        string $scss,
        ?string $path = null
    ): string;
}

<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

/**
 * @internal - may be changed in the future
 */
abstract class AbstractScssCompiler
{
    abstract public function compileString(
        AbstractCompilerConfiguration $config,
        string $scss,
        ?string $path = null
    ): string;
}

<?php declare(strict_types=1);

namespace Contena\Storefront\Theme;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use Contena\Core\Framework\Log\Package;

/**
 * @internal - may be changed in the future
 */
#[Package('discovery')]
class ScssPhpCompiler extends AbstractScssCompiler
{
    private Compiler $compiler;

    /**
     * @var array{cacheDir?: string, prefix?: string, forceRefresh?: string, checkImportResolutions?: bool}|null
     */
    private readonly ?array $cacheOptions;

    /**
     * @param array{cacheDir?: string, prefix?: string, forceRefresh?: string, checkImportResolutions?: bool}|null $cacheOptions
     */
    public function __construct(?array $cacheOptions = null)
    {
        $this->compiler = new Compiler($cacheOptions);
        $this->cacheOptions = $cacheOptions;
    }

    public function reset(): void
    {
        $this->compiler = new Compiler($this->cacheOptions);
    }

}

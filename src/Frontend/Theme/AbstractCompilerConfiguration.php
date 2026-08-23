<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

/**
 * @internal - may be changed in the future
 */
abstract class AbstractCompilerConfiguration
{
    /**
     * @return array<string, mixed>
     */
    abstract public function getConfiguration(): array;

    /**
     * @return mixed
     */
    abstract public function getValue(string $key);
}

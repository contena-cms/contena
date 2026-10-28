<?php declare(strict_types=1);

namespace Contena\Storefront\Theme;

use Contena\Core\Framework\Log\Package;

/**
 * @internal - may be changed in the future
 */
#[Package('discovery')]
class CompilerConfiguration extends AbstractCompilerConfiguration
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(private readonly array $configuration)
    {
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @return mixed
     */
    public function getValue(string $key)
    {
        return $this->configuration[$key] ?? null;
    }
}

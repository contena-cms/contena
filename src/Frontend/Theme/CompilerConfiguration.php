<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

final class CompilerConfiguration extends AbstractCompilerConfiguration
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(private readonly array $configuration)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getValue(string $key): mixed
    {
        return $this->configuration[$key] ?? null;
    }
}

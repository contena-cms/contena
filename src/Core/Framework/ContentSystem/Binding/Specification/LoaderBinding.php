<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Binding\Specification;

/**
 * One `resolves` entry of a {@see BindingSpecification}. Becomes a `DataRequirement` downstream.
 *
 * @internal
 */
final readonly class LoaderBinding
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $loader,
        public array $config,
    ) {
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Resolution;

use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;

/**
 * A single context value available at an element's position: a provider's key/FQCN plus how it is distributed.
 *
 * `$root` marks the entry as root-ambient: supplied by the layout's bound root source rather than by an
 * element. The root-source registry sets the flag on every entry it resolves.
 */
final readonly class ProvidedContext
{
    public function __construct(
        public string $contextKey,
        public string $fqcn,
        public ContextType $contextType,
        public ?string $providerElementId,
        public DistributionStrategy $distribution,
        public ?string $path = null,
        public bool $root = false,
    ) {
    }
}

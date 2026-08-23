<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Context;

use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;

/**
 * @internal
 */
final readonly class ContextProvider implements \JsonSerializable
{
    public function __construct(
        public ContextType $type,
        public DistributionConfig $distributionConfig
    ) {
    }

    /**
     * Flat wire shape: the type discriminator plus the distribution config spread in.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['type' => $this->type->value, ...$this->distributionConfig->toArray()];
    }
}

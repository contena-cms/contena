<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ShopIdChangeResolver;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
readonly class Resolver
{
    /**
     * @param iterable<ShopIdChangeStrategy> $strategies
     */
    public function __construct(
        private iterable $strategies
    ) {
    }

    public function resolve(string $strategyName, Context $context): void
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $strategyName) {
                $strategy->resolve($context);

                return;
            }
        }

        throw AppException::shopIdChangeResolveStrategyNotFound($strategyName);
    }

    /**
     * @return array<string>
     */
    public function getAvailableStrategies(): array
    {
        $strategies = [];

        foreach ($this->strategies as $strategy) {
            $strategies[$strategy->getName()] = $strategy->getDescription();
        }

        return $strategies;
    }
}

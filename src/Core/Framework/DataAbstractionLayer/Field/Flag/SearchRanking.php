<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Defines the weight for a search query on the entity for this field
 */
class SearchRanking extends Flag
{
    final public const float ASSOCIATION_SEARCH_RANKING = 0.25;
    final public const float MIDDLE_SEARCH_RANKING = 250.0;
    final public const float LOW_SEARCH_RANKING = 80.0;
    final public const float HIGH_SEARCH_RANKING = 500.0;

    public function __construct(
        protected float $ranking,
        protected bool $tokenize = true
    ) {
    }

    public function getRanking(): float
    {
        return $this->ranking;
    }

    public function parse(): \Generator
    {
        yield 'search_ranking' => $this->ranking;
    }

    public function tokenize(): bool
    {
        return $this->tokenize;
    }
}

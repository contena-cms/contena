<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Term;

/**
 * @final
 */
class SearchTerm
{
    /**
     * @internal
     */
    public function __construct(
        protected readonly string $term,
        protected readonly float $score = 1.0
    ) {
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getScore(): float
    {
        return $this->score;
    }
}

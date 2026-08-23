<?php

declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking as SearchRankingFlag;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SearchRanking
{
    final public const float ASSOCIATION_SEARCH_RANKING = SearchRankingFlag::ASSOCIATION_SEARCH_RANKING;
    final public const float MIDDLE_SEARCH_RANKING = SearchRankingFlag::MIDDLE_SEARCH_RANKING;
    final public const float LOW_SEARCH_RANKING = SearchRankingFlag::LOW_SEARCH_RANKING;
    final public const float HIGH_SEARCH_RANKING = SearchRankingFlag::HIGH_SEARCH_RANKING;

    public function __construct(
        public float $ranking,
        public bool $tokenize = true
    ) {
    }
}

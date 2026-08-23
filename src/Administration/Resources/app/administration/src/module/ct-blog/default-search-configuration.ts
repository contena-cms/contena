import { searchRankingPoint } from 'src/app/service/search-ranking.service';

type SearchRankingPoint = {
    HIGH_SEARCH_RANKING: number;
    MIDDLE_SEARCH_RANKING: number;
};

const rankingPoint = searchRankingPoint as SearchRankingPoint;

/** @private */
export default {
    _searchable: true,
    name: {
        _searchable: true,
        _score: rankingPoint.HIGH_SEARCH_RANKING,
    },
    description: {
        _searchable: true,
        _score: rankingPoint.MIDDLE_SEARCH_RANKING,
    },
    tags: {
        name: {
            _searchable: true,
            _score: rankingPoint.MIDDLE_SEARCH_RANKING,
        },
    },
};

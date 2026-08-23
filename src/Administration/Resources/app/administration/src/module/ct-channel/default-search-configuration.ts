import { searchRankingPoint } from 'src/app/service/search-ranking.service';

/** @private */
export default {
    _searchable: false,
    name: {
        _searchable: true,
        _score: (searchRankingPoint as { HIGH_SEARCH_RANKING: number }).HIGH_SEARCH_RANKING,
    },
};

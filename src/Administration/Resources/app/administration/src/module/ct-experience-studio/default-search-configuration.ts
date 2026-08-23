import { searchRankingPoint } from 'src/app/service/search-ranking.service';

type SearchRankingPoint = {
    HIGH_SEARCH_RANKING: number;
    LOW_SEARCH_RANKING: number;
    MIDDLE_SEARCH_RANKING: number;
};

const defaultSearchConfiguration = {
    _searchable: false,
    name: {
        _searchable: true,
        _score: (searchRankingPoint as SearchRankingPoint).HIGH_SEARCH_RANKING,
    },
};

/**
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default defaultSearchConfiguration;

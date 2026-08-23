import { searchRankingPoint } from 'src/app/service/search-ranking.service';

const rankingPoint = searchRankingPoint as { HIGH_SEARCH_RANKING: number };

const defaultSearchConfiguration = {
    _searchable: false,
    name: { _searchable: true, _score: rankingPoint.HIGH_SEARCH_RANKING },
};

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default defaultSearchConfiguration;

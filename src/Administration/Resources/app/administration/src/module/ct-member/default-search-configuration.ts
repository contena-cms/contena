import { searchRankingPoint } from 'src/app/service/search-ranking.service';

type SearchRankingPoint = {
    HIGH_SEARCH_RANKING: number;
    MIDDLE_SEARCH_RANKING: number;
};

const rankingPoint = searchRankingPoint as SearchRankingPoint;

const defaultSearchConfiguration = {
    _searchable: true,
    memberNumber: { _searchable: true, _score: rankingPoint.HIGH_SEARCH_RANKING },
    name: { _searchable: true, _score: rankingPoint.HIGH_SEARCH_RANKING },
    phoneNumber: { _searchable: true, _score: rankingPoint.MIDDLE_SEARCH_RANKING },
    email: { _searchable: true, _score: rankingPoint.MIDDLE_SEARCH_RANKING },
    addresses: {
        firstName: { _searchable: false, _score: rankingPoint.MIDDLE_SEARCH_RANKING },
        lastName: { _searchable: false, _score: rankingPoint.MIDDLE_SEARCH_RANKING },
        country: {
            name: { _searchable: true, _score: rankingPoint.MIDDLE_SEARCH_RANKING },
        },
        region: {
            name: { _searchable: true, _score: rankingPoint.MIDDLE_SEARCH_RANKING },
        },
        city: { _searchable: false, _score: rankingPoint.MIDDLE_SEARCH_RANKING },
        street: { _searchable: false, _score: rankingPoint.HIGH_SEARCH_RANKING },
    },
    tags: {
        name: { _searchable: true, _score: rankingPoint.HIGH_SEARCH_RANKING },
    },
};

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default defaultSearchConfiguration;

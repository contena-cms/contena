/**
 * @sw-package inventory
 */
import ProductIndexService from '../service/productIndex.api.service';
import LiveSearchApiService from '../service/livesearch.api.service';
import ExcludedSearchTermService from '../../../core/service/api/excludedSearchTerm.api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Contena.Service().register('productIndexService', () => {
    return new ProductIndexService(Contena.Application.getContainer('init').httpClient, Contena.Service('loginService'));
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Contena.Service().register('liveSearchService', () => {
    return new LiveSearchApiService(Contena.Application.getContainer('init').httpClient, Contena.Service('loginService'));
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Contena.Service().register('excludedSearchTermService', () => {
    return new ExcludedSearchTermService(
        Contena.Application.getContainer('init').httpClient,
        Contena.Service('loginService'),
    );
});

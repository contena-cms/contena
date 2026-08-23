import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import type { SubContainer } from 'src/global.types';

import ApiService from '../api.service';

/** @private */
class ExcludedSearchTermApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'reset-excluded-search-term') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'excludedSearchTermService';
    }

    resetExcludedSearchTerm(): Promise<unknown> {
        const headers = {
            ...this.getBasicHeaders(),
            'ct-language-id': Contena.Context.api.languageId,
        };

        return this.httpClient.post('/_admin/reset-excluded-search-term', {}, { headers });
    }
}

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        excludedSearchTermService: ExcludedSearchTermApiService;
    }
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default ExcludedSearchTermApiService;

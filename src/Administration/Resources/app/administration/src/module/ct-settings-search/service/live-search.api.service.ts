import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import type { SubContainer } from 'src/global.types';

import ApiService from 'src/core/service/api.service';

/** @private */
export type LiveSearchRequest = {
    channelId: string;
    search: string;
    order?: string;
};

/** @private */
export type LiveSearchResponse = Record<string, unknown>;

/** @private */
class LiveSearchApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'search') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'liveSearchService';
    }

    search(
        { channelId, search, order }: LiveSearchRequest,
        contextToken: string,
        additionalParams: Record<string, unknown> = {},
        additionalHeaders: Record<string, string> = {},
    ): Promise<AxiosResponse<LiveSearchResponse>> {
        const route = `_proxy/channel-api/${channelId}/search`;
        const payload = { channelId, search, order };
        const headers = this.getBasicHeaders(additionalHeaders);
        if (contextToken) headers['ct-context-token'] = contextToken;

        return this.httpClient.post(route, payload, { params: additionalParams, headers });
    }
}

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        liveSearchService: LiveSearchApiService;
    }
}

Contena.Application.addServiceProvider('liveSearchService', () => {
    const initContainer = Contena.Application.getContainer('init');
    return new LiveSearchApiService(initContainer.httpClient, Contena.Service('loginService'));
});

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default LiveSearchApiService;

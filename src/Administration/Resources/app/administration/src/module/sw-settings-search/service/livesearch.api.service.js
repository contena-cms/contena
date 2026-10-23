/**
 * @sw-package inventory
 */
const ApiService = Contena.Classes.ApiService;

/**
 * Gateway for the API endpoint "proxy search"
 * @class
 * @extends ApiService
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class LiveSearchService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'search') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'liveSearchService';
    }

    search({ channelId, search, order }, contextToken, additionalParams = {}, additionalHeaders = {}) {
        const route = `_proxy/channel-api/${channelId}/search`;
        const payload = {
            channelId,
            search,
            order,
        };
        const headers = {
            ...this.getBasicHeaders(additionalHeaders),
            'sw-context-token': contextToken,
        };
        return this.httpClient.post(route, payload, {
            additionalParams,
            headers,
        });
    }
}

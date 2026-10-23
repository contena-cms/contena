/**
 * @sw-package inventory
 */
const ApiService = Contena.Classes.ApiService;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class BlogIndexService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'blog.indexer') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'liveSearchService';
    }

    index(offset) {
        const route = '/_action/indexing/blog.indexer';
        const headers = this.getHeaders();
        return this.httpClient.post(route, { offset: offset }, { headers });
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json',
        };
    }
}

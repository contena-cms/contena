/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import ApiService from '../api.service';

export default class AppCmsBlocksService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'appCmsBlocks';
    }

    fetchAppBlocks() {
        return this.httpClient
            .get('app-system/cms/blocks', { headers: this.getBasicHeaders() })
            .then(({ data }) => data.blocks);
    }
}

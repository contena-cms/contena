/**
 * @sw-package discovery
 */

import ApiService from '../api.service';

/**
 * Gateway for the API end point "blog-export"
 * @class
 * @extends ApiService
 */
class BlogExportApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'blog-export') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'blogExportService';
    }

    validateProductExportTemplate(blogExport) {
        const apiRoute = `/_action/${this.getApiBasePath()}/validate`;

        return this.httpClient
            .post(apiRoute, blogExport, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    previewProductExport(blogExport) {
        const apiRoute = `/_action/${this.getApiBasePath()}/preview`;

        return this.httpClient
            .post(apiRoute, blogExport, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Get the generated access key and secret access key from the API
     *
     * @param {Object} additionalParams
     * @param {Object} additionalHeaders
     * @returns {Promise<T>}
     */
    generateKey(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_action/access-key/blog-export', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default BlogExportApiService;

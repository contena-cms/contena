import ApiService from '../api.service';

/**
 * Gateway for the API end point "admin_user"
 * @class
 * @extends ApiService
 * @sw-package fundamentals@framework
 */
class AdminUserApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'admin_user') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'admin_userService';
    }

    /**
     * Get information of the logged in admin_user
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    getUser(additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_info/me', {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    saveMcpAllowlist(admin_userId, allowlist, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(`/_action/admin_user/${admin_userId}/mcp-allowlist`, { allowlist }, { headers }).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    /**
     * Update information of the logged in admin_user
     *
     * @param {Object} [additionalParams = {}]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    updateUser(additionalParams = {}, additionalHeaders = {}) {
        const data = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .patch('/_info/me', data, {
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default AdminUserApiService;

import ApiService from '../api.service';

/**
 * @sw-package checkout
 * Gateway for the API end point "user-group-registration"
 * @class
 * @extends ApiService
 */
class UserGroupRegistrationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'user-group-registration') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userGroupRegistrationService';
    }

    accept(userId, additionalParams = {}, additionalHeaders = {}, additionalRequest = {}) {
        const route = `/_action/${this.getApiBasePath()}/accept`;
        return this.httpClient
            .post(
                route,
                {
                    userIds: Array.isArray(userId) ? userId : [userId],
                    ...additionalRequest,
                },
                {
                    params: additionalParams,
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    decline(userId, additionalParams = {}, additionalHeaders = {}, additionalRequest = {}) {
        const route = `/_action/${this.getApiBasePath()}/decline`;
        return this.httpClient
            .post(
                route,
                {
                    userIds: Array.isArray(userId) ? userId : [userId],
                    ...additionalRequest,
                },
                {
                    params: additionalParams,
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default UserGroupRegistrationApiService;

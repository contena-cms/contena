import ApiService from '../api.service';

/**
 * @sw-package checkout
 * Gateway for the API end point "check-email-valid"
 * @class
 * @extends ApiService
 */
class UserValidationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'check-user-email-valid') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userValidationService';
    }

    checkCustomerEmail(payload, additionalParams = {}, additionalHeaders = {}) {
        const params = additionalParams;
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .post(`/_admin/${this.apiEndpoint}`, payload, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            })
            .catch((exception) => {
                throw exception;
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default UserValidationApiService;

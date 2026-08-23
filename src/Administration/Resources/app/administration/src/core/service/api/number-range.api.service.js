import ApiService from '../api.service';

/**
 * Gateway for the API end point "number-range"
 * @class
 * @extends ApiService
 */
class NumberRangeApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'number-range') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'numberRangeService';
    }

    /**
     * reserve a number range value
     *
     * @param {string} typeName
     * @param {boolean} preview [preview=false]
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    reserve(typeName, preview = false, additionalHeaders = {}) {
        const url = `_action/number-range/reserve/${typeName}`;

        const headers = this.getBasicHeaders(additionalHeaders);
        const params = {
            preview: preview,
        };

        return this.httpClient
            .get(url, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * get preview of next persisted number range value
     *
     * @param {string} numberRangeId
     * @param {string} pattern
     * @param {int} start
     * @param {Object} [additionalHeaders = {}]
     * @returns {Promise<T>}
     */
    previewPatternByNumberRangeId(numberRangeId, pattern, start, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);
        const params = {
            pattern: pattern,
            start: start,
        };

        return this.httpClient
            .get(`_action/number-range/${numberRangeId}/preview-pattern`, {
                params,
                headers,
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default NumberRangeApiService;

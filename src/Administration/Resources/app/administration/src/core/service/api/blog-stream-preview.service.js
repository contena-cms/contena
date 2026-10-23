import { deepMergeObject } from 'src/core/service/utils/object.utils';
import ApiService from '../api.service';

/**
 * @private
 * @sw-package inventory
 */
export default class BlogStreamPreviewService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'blogStreamPreviewService';
    }

    /**
     * @param {string} channelId
     * @param {Criteria} criteria
     * @param {Array} filter
     * @param {Object} additionalHeaders
     * @param {boolean} displayAsGroup
     *
     * @returns Object
     */
    preview(channelId, criteria, filter, additionalHeaders = {}, displayAsGroup = true) {
        const body = deepMergeObject(criteria.parse(), {
            filter,
        });

        return this.httpClient
            .post(`_admin/blog-stream-preview/${channelId}`, body, {
                headers: this.getBasicHeaders(additionalHeaders),
                // controller mirrors the frontend's variant grouping when it is enabled.
                params: { displayAsGroup },
            })
            .then((response) => ApiService.handleResponse(response));
    }
}

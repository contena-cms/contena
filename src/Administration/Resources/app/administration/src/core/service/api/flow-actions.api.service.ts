import ApiService from '../api.service';

class FlowActionsApiService extends ApiService {
    public name = 'flowActionService';

    public getFlowActions(additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .get<Record<string, unknown>>('/_info/flow-actions.json', {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => ApiService.handleResponse(response));
    }
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default FlowActionsApiService;

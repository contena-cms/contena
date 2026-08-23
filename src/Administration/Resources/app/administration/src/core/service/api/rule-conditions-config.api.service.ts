import ApiService from '../api.service';

class RuleConditionsConfigApiService extends ApiService {
    public name = 'ruleConditionsConfigApiService';

    public getConfig(additionalParams = {}, additionalHeaders = {}) {
        return this.httpClient
            .get<Record<string, unknown>>('/_info/rule-config', {
                params: additionalParams,
                headers: this.getBasicHeaders(additionalHeaders),
            })
            .then((response) => ApiService.handleResponse(response));
    }
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default RuleConditionsConfigApiService;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import ApiService from '../api.service';
import InvalidActionButtonParameterError from './errors/InvalidActionButtonParameterError';

export default class AppActionButtonService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, null, 'application/json');
        this.name = 'appActionButtonService';
    }

    getBasicHeaders() {
        return {
            ...super.getBasicHeaders(),
            'ct-language-id': Contena.Context.api.languageId,
        };
    }

    getActionButtonsPerView(entity, view) {
        if (!entity) {
            throw new InvalidActionButtonParameterError(`Parameter "entity" must have a valid value. Given: ${entity}`);
        }

        if (!view) {
            throw new InvalidActionButtonParameterError(`Parameter "view" must have a valid value. Given: ${view}`);
        }

        return this.httpClient
            .get(`app-system/action-button/${entity}/${view}`, { headers: this.getBasicHeaders() })
            .then(({ data }) => data.actions);
    }

    runAction(id, params = {}) {
        return this.httpClient.post(`app-system/action-button/run/${id}`, params, {
            headers: this.getBasicHeaders(),
        });
    }
}

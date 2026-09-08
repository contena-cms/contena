/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type { AxiosInstance, AxiosResponse } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

export type AppAction = {
    url: string;
    entity: string;
    action: string;
    appName: string;
};

/** API used by App iframe integrations and action buttons. */
export default class ExtensionSdkService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, 'extension-sdk', 'application/json');
        this.name = 'extensionSdkService';
    }

    runAction(action: AppAction, entityIdList: string[]): Promise<unknown> {
        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/run-action`, { ...action, ids: entityIdList }, {
                params: {},
                headers: this.getBasicHeaders(),
            })
            .then((response: AxiosResponse<unknown>) => ApiService.handleResponse(response));
    }

    signIframeSrc(appName: string, src: string): Promise<unknown> {
        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/sign-uri`, { appName, uri: src }, {
                params: {},
                headers: this.getBasicHeaders(),
            })
            .then((response: AxiosResponse<unknown>) => ApiService.handleResponse(response));
    }
}

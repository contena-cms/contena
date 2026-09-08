/* eslint-disable ct-deprecation-rules/private-feature-declarations */
/**
 * App administration module API.
 *
 * This service intentionally mirrors the upstream app-system contract. App
 * modules are remote UI entry points; their source is signed by the backend
 * before it is loaded by the administration shell.
 */
import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

export type AppModuleDefinition = {
    name: string;
    label: Record<string, string>;
    mainModule?: { source: string };
    modules: Array<{
        name: string;
        label: Record<string, string>;
        position: number;
        source?: string;
        parent?: string;
    }>;
};

export default class AppModulesService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService) {
        super(httpClient, loginService, '', 'application/json');
        this.name = 'appModulesService';
    }

    public async fetchAppModules(): Promise<AppModuleDefinition[]> {
        const { data } = await this.httpClient.get<{ modules: AppModuleDefinition[] }>('app-system/modules', {
            headers: this.getBasicHeaders(),
        });

        return data.modules;
    }
}

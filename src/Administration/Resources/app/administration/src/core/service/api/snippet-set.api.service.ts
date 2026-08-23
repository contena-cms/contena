import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import ApiService from '../api.service';

/** @private */
export type SnippetSetListResponse = {
    total: number;
    data: Record<string, SnippetListItem[]>;
};

/** @private */
export type SnippetListItem = {
    id: string | null;
    setId: string;
    translationKey: string;
    value: string | null;
    origin: string | null;
    resetTo?: string | null;
    author: string;
    hasFileValue?: boolean;
};

/** @private */
export type SnippetBaseFile = {
    name: string;
    iso: string | null;
};

class SnippetSetApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'snippet-set') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'snippetSetService';
    }

    getCustomList(
        page = 1,
        limit = 25,
        filters: Record<string, unknown> = {},
        sort: Record<string, unknown> = {},
    ): Promise<SnippetSetListResponse> {
        const defaultSort = { sortBy: 'id', sortDirection: 'ASC' };

        return this.httpClient
            .post(
                `/_action/${this.getApiBasePath()}`,
                { page, limit, filters, sort: { ...defaultSort, ...sort } },
                { headers: this.getBasicHeaders() },
            )
            .then((response: AxiosResponse<SnippetSetListResponse>) => ApiService.handleResponse(response));
    }

    getBaseFiles(): Promise<{ items: Record<string, SnippetBaseFile>; total: number }> {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/baseFile`, { headers: this.getBasicHeaders() })
            .then((response: AxiosResponse<{ items: Record<string, SnippetBaseFile>; total: number }>) =>
                ApiService.handleResponse(response),
            );
    }

    getAuthors(): Promise<{ data: string[]; total: number }> {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/author`, { headers: this.getBasicHeaders() })
            .then((response: AxiosResponse<{ data: string[]; total: number }>) => ApiService.handleResponse(response));
    }
}

/** @private */
export default SnippetSetApiService;

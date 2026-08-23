import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from 'src/core/service/login.service';
import type { SubContainer } from 'src/global.types';

import ApiService from 'src/core/service/api.service';

/** @private */
export type BlogIndexResponse = {
    finish: boolean;
    offset?: { offset: number } | number;
};

/** @private */
class BlogIndexApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'blog.indexer') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'blogIndexService';
    }

    index(offset: number): Promise<AxiosResponse<BlogIndexResponse>> {
        return this.httpClient.post('/_action/indexing/blog.indexer', { offset }, { headers: this.getBasicHeaders() });
    }
}

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        blogIndexService: BlogIndexApiService;
    }
}

Contena.Application.addServiceProvider('blogIndexService', () => {
    const initContainer = Contena.Application.getContainer('init');
    return new BlogIndexApiService(initContainer.httpClient, Contena.Service('loginService'));
});

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default BlogIndexApiService;

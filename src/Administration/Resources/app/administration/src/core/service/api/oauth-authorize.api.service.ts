import type { AxiosInstance } from 'axios';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export type OAuthAuthorizationParams = {
    response_type: string;
    client_id: string;
    redirect_uri: string;
    code_challenge: string;
    code_challenge_method: string;
    state?: string;
    scope?: string;
};

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export type OAuthAuthorizationInfo = {
    client: { id: string; name: string };
    redirectUri: string;
    scopes: string[];
};

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export type OAuthAuthorizationDecision = { redirectUri: string };

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default class OAuthAuthorizeApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'oauth/authorize') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'oauthAuthorizeApiService';
    }

    getInfo(params: OAuthAuthorizationParams): Promise<OAuthAuthorizationInfo> {
        return this.httpClient
            .get<OAuthAuthorizationInfo>(`${this.getApiBasePath()}/info`, {
                params,
                headers: this.getBasicHeaders(),
            })
            .then((response) => ApiService.handleResponse(response));
    }

    decide(params: OAuthAuthorizationParams, approved: boolean): Promise<OAuthAuthorizationDecision> {
        return this.httpClient
            .post<OAuthAuthorizationDecision>(
                this.getApiBasePath(),
                { ...params, approved },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => ApiService.handleResponse(response));
    }
}

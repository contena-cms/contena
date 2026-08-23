import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

/**
 * Custom gateway for the "user/user-recovery" routes
 * @class
 * @extends ApiService
 */
// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default class UserRecoveryApiService extends ApiService {
    context: unknown;

    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'user') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'userRecoveryService';
        this.context = Contena.Context;
    }

    createRecovery(email: string) {
        const apiRoute = `/_action/${this.getApiBasePath()}/user-recovery`;

        return this.httpClient
            .post(
                apiRoute,
                {
                    email: email,
                },
                {
                    params: {},
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => {
                ApiService.handleResponse(response);
            });
    }

    checkHash(hash: string) {
        const apiRoute = `/_action/${this.getApiBasePath()}/user-recovery/hash`;

        return this.httpClient
            .get(apiRoute, {
                params: { hash: hash },
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                ApiService.handleResponse(response);
            });
    }

    updateUserPassword(hash: string, password: string, passwordConfirm: string) {
        const apiRoute = `/_action/${this.getApiBasePath()}/user-recovery/password`;

        return this.httpClient
            .patch(
                apiRoute,
                {
                    hash: hash,
                    password: password,
                    passwordConfirm: passwordConfirm,
                },
                {
                    params: {},
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => {
                ApiService.handleResponse(response);
            });
    }
}

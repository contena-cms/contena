/**
 * @sw-package discovery
 */

import type { HttpClient } from 'src/core/factory/http-client.types';
import ApiService from '../api.service';
import type { LoginService } from '../login.service';
import type { ContextSwitchParameters } from '../../../module/sw-order/order.types';

/**
 * Gateway for the API end point "sales-channel-context"
 * Uses the _proxy endpoint of the admin api to connect to the channel-api endpoint cart
 * @class
 * @extends ApiService
 */
class StoreContextService extends ApiService {
    constructor(httpClient: HttpClient, loginService: LoginService, apiEndpoint = 'sales-channel-context') {
        super(httpClient, loginService, apiEndpoint, 'application/json');

        this.name = 'contextStoreService';
    }

    updateCustomerContext(
        userId: EntityKey<'user'>,
        channelId: EntityKey<'channel'>,
        contextToken: string,
        additionalParams = {},
        additionalHeaders = {},
        permissions = ['allowProductPriceOverwrites'],
    ) {
        const route = '_proxy/switch-user';
        const headers = this.getBasicHeaders({
            ...additionalHeaders,
            'sw-context-token': contextToken,
        });

        return this.httpClient.patch(
            route,
            {
                userId: userId,
                channelId: channelId,
                permissions: permissions,
            },
            { ...additionalParams, headers },
        );
    }

    updateContext(
        context: ContextSwitchParameters,
        channelId: EntityKey<'channel'>,
        contextToken: string | null,
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_proxy/channel-api/${channelId}/context`;
        const headers = this.getBasicHeaders({
            ...additionalHeaders,
            'sw-context-token': contextToken,
        });

        return this.httpClient.patch(route, context, {
            ...additionalParams,
            headers,
        });
    }

    getSalesChannelContext(
        channelId: EntityKey<'channel'>,
        contextToken: string | null,
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = `_proxy/channel-api/${channelId}/context`;
        const headers = this.getBasicHeaders({
            ...additionalHeaders,
            'sw-context-token': contextToken,
        });

        return this.httpClient.get(route, { ...additionalParams, headers });
    }

    generateImitateCustomerToken(
        userId: EntityKey<'user'>,
        channelId: EntityKey<'channel'>,
        additionalParams = {},
        additionalHeaders = {},
    ) {
        const route = '_proxy/generate-imitate-user-token';
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient.post(
            route,
            {
                userId,
                channelId,
            },
            { ...additionalParams, headers },
        );
    }

    redirectToSalesChannelUrl(
        channelDomainUrl: string,
        token: string,
        userId: EntityKey<'user'>,
        admin_userId: EntityKey<'admin_user'>,
    ) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${channelDomainUrl}/account/login/imitate-user`;
        form.target = '_blank';
        document.body.appendChild(form);

        this.#createHiddenInput(form, 'token', token);
        this.#createHiddenInput(form, 'userId', userId);
        this.#createHiddenInput(form, 'admin_userId', admin_userId);

        form.submit();
        form.remove();
    }

    #createHiddenInput(form: HTMLFormElement, name: string, value: string) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default StoreContextService;

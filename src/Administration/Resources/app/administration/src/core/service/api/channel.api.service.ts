import type { AxiosInstance, AxiosResponse } from 'axios';
import type { LoginService } from 'src/core/service/login.service';

import ApiService from '../api.service';

type ChannelAccessKeyResponse = {
    accessKey: string;
};

/**
 * Gateway for Channel administration actions.
 */
class ChannelApiService extends ApiService {
    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'channel') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'channelService';
    }

    generateKey(
        additionalParams: Record<string, unknown> = {},
        additionalHeaders: Record<string, string> = {},
    ): Promise<ChannelAccessKeyResponse> {
        const headers = this.getBasicHeaders(additionalHeaders);

        return this.httpClient
            .get('/_action/access-key/channel', {
                params: additionalParams,
                headers,
            })
            .then((response: AxiosResponse<ChannelAccessKeyResponse>) => ApiService.handleResponse(response));
    }
}

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export default ChannelApiService;

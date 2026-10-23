/**
 * @sw-package discovery
 *
 * @private
 */

const { Application } = Contena;
const ApiService = Contena.Classes.ApiService;

class ChannelFileApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sales-channel-file') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'channelFileApiService';
    }

    list(fileFamily, channelId) {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/${fileFamily}/${channelId}`, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    detail(fileFamily, channelId, fileName) {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/${fileFamily}/${channelId}/detail`, {
                headers: this.getBasicHeaders(),
                params: {
                    fileName,
                },
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    preview(fileFamily, channelId, fileName, templateOverrides) {
        const data = { fileName };
        if (templateOverrides !== undefined) {
            data.templateOverrides = templateOverrides;
        }

        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/${fileFamily}/${channelId}/preview`, data, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

Application.addServiceProvider('channelFileApiService', () => {
    return new ChannelFileApiService(
        Contena.Application.getContainer('init').httpClient,
        Contena.Service('loginService'),
    );
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default ChannelFileApiService;

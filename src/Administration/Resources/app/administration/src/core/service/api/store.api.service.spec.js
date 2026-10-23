import ChannelApiService from 'src/core/service/api/store.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

function getChannelApiService(client = null, loginService = null) {
    if (client === null) {
        client = createHTTPClient();
    }

    if (loginService === null) {
        loginService = createLoginService(client, Contena.Context.api);
    }

    return new ChannelApiService(client, loginService);
}

/**
 * @sw-package framework
 */
describe('storeService', () => {
    it('is registered correctly', async () => {
        expect(getChannelApiService()).toBeInstanceOf(ChannelApiService);
    });
});

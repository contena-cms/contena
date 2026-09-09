import AppModulesService from 'src/core/service/api/app-modules.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

describe('appModulesService', () => {
    it('fetches generic App modules from the app-system endpoint', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const service = new AppModulesService(client, createLoginService(client, Contena.Context.api));
        const module = {
            name: 'ContenaExampleApp',
            label: { 'en-GB': 'Contena Example App' },
            modules: [{ name: 'blogList', label: { 'en-GB': 'Blog list' }, source: 'https://example.test/blog' }],
        };
        clientMock.onGet('/app-system/modules').reply(200, { modules: [module] });
        expect(await service.fetchAppModules()).toEqual([module]);
    });
});

import MockAdapter from 'axios-mock-adapter';
import createLoginService from 'src/core/service/login.service';
import createHttpClient from 'src/core/factory/http.factory';
import CacheService from 'src/app/service/cache.service';
import AdminUserConfigService from './admin_user-config.api.service';

function newUserConfigService(client) {
    return new AdminUserConfigService(client, createLoginService(client, Contena.Context.api));
}

if (!Contena.Service('cacheService')) {
    Contena.Service().register('cacheService', () => new CacheService());
}

describe('admin_userConfigService', () => {
    beforeEach(() => {
        Contena.Service('cacheService').clear();
    });

    it('has the correct name', async () => {
        const admin_userConfigService = newUserConfigService(createHttpClient());

        expect(admin_userConfigService.name).toBe('admin_userConfigService');
    });

    it('fetches admin_user configs from the API', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const admin_userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.admin_userConfig': ['some-value'],
            },
        });

        const response = await admin_userConfigService.search();

        expect(response).toEqual({
            data: {
                'core.admin_userConfig': ['some-value'],
            },
        });
    });

    it('filters requested keys from the cached response', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const admin_userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.admin_userConfig': ['some-value'],
                'core.otherConfig': ['other-value'],
            },
        });

        const response = await admin_userConfigService.search(['core.otherConfig']);

        expect(response).toEqual({
            data: {
                'core.otherConfig': ['other-value'],
            },
        });
    });

    it('reuses the cached full response until forced to reload', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const admin_userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.admin_userConfig': ['some-value'],
            },
        });

        await admin_userConfigService.search(['core.admin_userConfig']);
        await admin_userConfigService.search(['core.admin_userConfig']);

        expect(mockAdapter.history.get).toHaveLength(1);
    });

    it('returns undefined on error and logs the error', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const admin_userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(503);
        const consoleSpy = jest.spyOn(Contena.Utils.debug, 'error').mockImplementation(() => {});

        const response = await admin_userConfigService.search();

        expect(consoleSpy).toHaveBeenCalled();
        expect(consoleSpy).toHaveBeenCalledWith('AdminUserConfigService', expect.anything());
        expect(response).toBeUndefined();
    });

    it('sends upsert request for admin_user configs', async () => {
        const client = createHttpClient();
        const mockAdapter = new MockAdapter(client);
        const admin_userConfigService = newUserConfigService(client);

        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.admin_userConfig': ['old-value'],
            },
        });
        mockAdapter.onPatch('/api/_info/config-me').replyOnce(204);
        mockAdapter.onGet('/api/_info/config-me').replyOnce(200, {
            data: {
                'core.admin_userConfig': ['new-value'],
            },
        });

        await admin_userConfigService.search(['core.admin_userConfig']);

        await admin_userConfigService.upsert({
            'core.admin_userConfig': ['new-value'],
        });

        const response = await admin_userConfigService.search(['core.admin_userConfig']);

        expect(mockAdapter.history.patch).toHaveLength(1);
        expect(mockAdapter.history.patch[0].data).toEqual(JSON.stringify({ 'core.admin_userConfig': ['new-value'] }));
        expect(mockAdapter.history.get).toHaveLength(2);
        expect(response).toEqual({
            data: {
                'core.admin_userConfig': ['new-value'],
            },
        });
    });
});

/**
 * @sw-package framework
 */
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import InstallationIdChangeService from './shop-id-change.service';

function createInstallationIdChangeService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Contena.Context.api);
    const installationIdChangeService = new InstallationIdChangeService(client, loginService);
    return { installationIdChangeService: installationIdChangeService, clientMock };
}

describe('installationIdChangeService', () => {
    it('is registered correctly', async () => {
        const { installationIdChangeService } = createInstallationIdChangeService();

        expect(installationIdChangeService).toBeInstanceOf(InstallationIdChangeService);
    });

    it('fetches strategies correctly', async () => {
        const { installationIdChangeService, clientMock } = createInstallationIdChangeService();

        clientMock.onGet('/app-system/shop-id/change-strategies').reply(200, {
            first: 'a',
            second: 'b',
            third: 'c',
        });

        const strategies = await installationIdChangeService.getChangeStrategies();

        expect(strategies).toEqual([
            {
                name: 'first',
                description: 'a',
            },
            {
                name: 'second',
                description: 'b',
            },
            {
                name: 'third',
                description: 'c',
            },
        ]);
    });

    it('sends name of selected strategy', async () => {
        const { installationIdChangeService, clientMock } = createInstallationIdChangeService();

        clientMock
            .onPost('app-system/shop-id/change', {
                strategy: 'selectedStrategy',
            })
            .reply(204);

        await installationIdChangeService.changeInstallationId({
            name: 'selectedStrategy',
        });

        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({
            strategy: 'selectedStrategy',
        });
    });

    it('returns shop ID check', async () => {
        const { installationIdChangeService, clientMock } = createInstallationIdChangeService();

        const response = {
            fingerprints: {
                matchingFingerprints: {
                    app_url: {
                        storedStamp: 'https://old.url',
                    },
                },
                mismatchingFingerprints: {
                    installation_path: {
                        storedStamp: '/var/www/old',
                        expectedStamp: '/var/www/new',
                    },
                    channel_domain_urls: {
                        storedStamp: '4f02235821f19784fd6ea6a6df754552',
                        expectedStamp: '14f15f8c18172be22c9135c278358549',
                    },
                },
                score: 225,
                threshold: 75,
            },
            apps: ['Test Foo App', 'Test Bar App', 'Test Baz App'],
        };

        clientMock.onPost('app-system/shop-id/check').reply(200, response);

        const fingerprints = await installationIdChangeService.checkInstallationId();

        expect(fingerprints).toEqual(response);
    });

    it('returns null if shop id fingerprints have not changed', async () => {
        const { installationIdChangeService, clientMock } = createInstallationIdChangeService();

        clientMock.onPost('app-system/shop-id/check').reply(204);

        const urlDiff = await installationIdChangeService.checkInstallationId();

        expect(urlDiff).toBeNull();
    });
});

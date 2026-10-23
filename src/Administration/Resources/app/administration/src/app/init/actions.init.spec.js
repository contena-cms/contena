/**
 * @sw-package framework
 */
import initActions from 'src/app/init/actions.init';
import { actionExecute } from '@contena-ag/meteor-admin-sdk/es/app/action';
import ExtensionSdkService from '../../core/service/api/extension-sdk.service';

describe('src/app/init/actions.init.ts', () => {
    beforeAll(() => {
        Contena.Service().register('extensionSdkService', () => {
            return new ExtensionSdkService();
        });
    });

    beforeEach(() => {
        Contena.Store.get('extensions').extensionsState = {};
    });

    it('should handle actionExecute', async () => {
        const appName = 'jestapp';
        const mock = jest.fn();

        Contena.Store.get('extensions').addExtension({
            name: appName,
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Contena.Service('extensionSdkService').runAction = mock;

        initActions();
        await actionExecute({
            entity: 'user',
            url: 'https://example.com',
            entityIds: ['123'],
        });

        expect(mock).toHaveBeenCalledWith(
            expect.objectContaining({
                url: 'https://example.com',
                entity: 'user',
                action: expect.any(String),
                appName: appName,
            }),
            ['123'],
        );
    });

    it('should not handle actionExecute if extension is not found', async () => {
        const mock = jest.fn();

        Contena.Service('extensionSdkService').runAction = mock;

        initActions();

        await expect(
            actionExecute({
                entity: 'user',
                url: 'https://example.com',
                entityIds: ['123'],
            }),
        ).rejects.toThrow('Could not find an extension with the given event origin ""');

        expect(mock).toHaveBeenCalledTimes(0);
    });
});

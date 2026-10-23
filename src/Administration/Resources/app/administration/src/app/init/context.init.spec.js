/**
 * @sw-package framework
 */
import initContext from 'src/app/init/context.init';
import {
    getCurrency,
    getEnvironment,
    getLocale,
    getContenaVersion,
    getModuleInformation,
    getAppInformation,
    getUserInformation,
    getUserTimezone,
    getInstallationId,
    getTheme,
} from '@contena-ag/meteor-admin-sdk/es/context';
import { isService } from '@contena-ag/meteor-admin-sdk/es/_private/context';
import { getId } from '@contena-ag/meteor-admin-sdk/es/window';
import { publish } from '@contena-ag/meteor-admin-sdk/es/channel';
import { nextTick } from 'vue';
import useTheme from 'src/app/composables/use-theme';

jest.mock('@contena-ag/meteor-admin-sdk/es/channel', () => {
    const actual = jest.requireActual('@contena-ag/meteor-admin-sdk/es/channel');

    return {
        __esModule: true,
        ...actual,
        publish: jest.fn(actual.publish),
    };
});

describe('src/app/init/context.init.ts', () => {
    beforeAll(() => {
        initContext();
    });

    beforeEach(() => {
        Contena.Store.get('extensions').extensionsState = {};
        Contena.Store.get('context').app.windowId = null;
    });

    it('should handle currency', async () => {
        await getCurrency().then((currency) => {
            expect(currency).toEqual(
                expect.objectContaining({
                    systemCurrencyId: expect.any(String),
                    systemCurrencyISOCode: expect.any(String),
                }),
            );
        });
    });

    it('should handle environment', async () => {
        await getEnvironment().then((environment) => {
            expect(environment).toEqual(expect.any(String));
        });
    });

    it('should handle locale', async () => {
        await getLocale().then((locale) => {
            expect(locale).toEqual(
                expect.objectContaining({
                    fallbackLocale: expect.any(String),
                    locale: expect.any(String),
                }),
            );
        });
    });

    it('should handle contena version', async () => {
        await getContenaVersion().then((version) => {
            expect(version).toEqual(expect.any(String));
        });
    });

    it('should handle module information', async () => {
        await getModuleInformation().then((moduleInformation) => {
            expect(moduleInformation).toEqual(
                expect.objectContaining({
                    modules: expect.any(Array),
                }),
            );
        });
    });

    it('should return placeholder app information', async () => {
        await getAppInformation().then((appInformation) => {
            expect(appInformation).toEqual(
                expect.objectContaining({
                    name: 'unknown',
                    version: '0.0.0',
                    type: 'app',
                }),
            );
        });
    });

    it('should return admin_user timezone', async () => {
        Contena.Store.get('session').setCurrentUser({
            timeZone: 'Europe/Berlin',
        });
        await getUserTimezone().then((timezone) => {
            expect(timezone).toBe('Europe/Berlin');
        });

        Contena.Store.get('session').setCurrentUser({
            timeZone: undefined,
        });
        await getUserTimezone().then((timezone) => {
            expect(timezone).toBe('UTC');
        });
    });

    it('should return app information', async () => {
        Contena.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: {
                read: ['blog'],
            },
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        await getAppInformation().then((appInformation) => {
            expect(appInformation).toEqual(
                expect.objectContaining({
                    name: 'jestapp',
                    version: '1.0.0',
                    type: 'app',
                    privileges: {
                        read: ['blog'],
                    },
                }),
            );
        });
    });

    it('should identify a Contena Service through the private SDK API', async () => {
        // The SDK only answers `isService()` from Contena 6.7.14.0 on
        Contena.Store.get('context').app.config.version = '6.7.14.0';
        Contena.Store.get('extensions').addExtension({
            name: 'jestservice',
            baseUrl: window.location.origin,
            permissions: {},
            version: '1.0.0',
            type: 'app',
            sourceType: 'service',
        });

        await expect(isService()).resolves.toBe(true);
    });

    it('should return admin_user information', async () => {
        Contena.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: {
                read: ['admin_user'],
            },
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Contena.Store.get('session').setCurrentUser({
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'admin_user',
            username: 'john.doe',
        });

        await getUserInformation().then((admin_userInformation) => {
            expect(admin_userInformation).toEqual(
                expect.objectContaining({
                    aclRoles: expect.any(Array),
                    active: true,
                    admin: true,
                    email: 'john.doe@test.com',
                    firstName: 'John',
                    id: '123',
                    lastName: 'Doe',
                    localeId: 'lOcAlEiD',
                    title: 'Dr.',
                    type: 'admin_user',
                    username: 'john.doe',
                }),
            );
        });
    });

    it('should not return admin_user information when permissions arent existing', async () => {
        Contena.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });

        Contena.Store.get('session').setCurrentUser({
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'admin_user',
            username: 'john.doe',
        });

        await expect(getUserInformation()).rejects.toThrow('Extension "jestapp" does not have the permission to read users');
    });

    it('should not return admin_user information when extension is not existing', async () => {
        Contena.Store.get('session').setCurrentUser({
            aclRoles: [],
            active: true,
            admin: true,
            email: 'john.doe@test.com',
            firstName: 'John',
            id: '123',
            lastName: 'Doe',
            localeId: 'lOcAlEiD',
            title: 'Dr.',
            type: 'admin_user',
            username: 'john.doe',
        });

        await expect(getUserInformation()).rejects.toThrow('Could not find a extension with the given event origin ""');
    });

    it('returns windowId from store', async () => {
        Contena.Store.get('context').app.windowId = '123';

        const windowId = await getId();

        expect(windowId).toBe('123');
    });

    it('should initialize windowId if not set', async () => {
        expect(Contena.Store.get('context').app.windowId).toBeNull();

        const windowId = await getId();

        expect(Contena.Store.get('context').windowId).not.toBeNull();
        expect(windowId).toBe(Contena.Store.get('context').app.windowId);
    });

    it('should return correct installationId', async () => {
        expect(Contena.Store.get('context').app.config.installationId).toBeNull();

        expect(await getInstallationId()).toBeNull();

        Contena.Store.get('context').app.config.installationId = 'shop-id';

        expect(await getInstallationId()).toBe('shop-id');
    });

    describe('theme', () => {
        afterEach(async () => {
            useTheme().setTheme('system');
            await nextTick();

            localStorage.removeItem('mt-theme');
        });

        it('should handle the resolved theme', async () => {
            useTheme().setTheme('dark');
            await nextTick();

            await expect(getTheme()).resolves.toBe('dark');

            useTheme().setTheme('light');
            await nextTick();

            await expect(getTheme()).resolves.toBe('light');
        });

        it('should resolve the system preference to a concrete theme', async () => {
            await expect(getTheme()).resolves.toBe('light');
        });

        it('should publish the resolved theme on change', async () => {
            publish.mockClear();

            useTheme().setTheme('dark');
            await nextTick();

            expect(publish).toHaveBeenCalledWith('contextTheme', 'dark');
        });

        it('should not publish when the resolved theme stays the same', async () => {
            publish.mockClear();

            useTheme().setTheme('light');
            await nextTick();

            publish.mockClear();

            useTheme().setTheme('system');
            await nextTick();

            expect(publish).not.toHaveBeenCalledWith('contextTheme', expect.anything());
        });
    });
});

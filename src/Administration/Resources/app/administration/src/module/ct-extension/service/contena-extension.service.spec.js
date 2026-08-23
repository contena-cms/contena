import ContenaExtensionService from './contena-extension.service';
import '../store/extensions.store';

describe('src/module/ct-extension/service/contena-extension.service', () => {
    let actionService;
    let service;

    const pluginExtension = {
        id: null,
        localId: 'plugin-id',
        source: 'local',
        name: 'ExamplePlugin',
        label: 'Example plugin',
        description: 'Example description',
        producerName: 'Contena',
        license: 'MIT',
        version: '1.0.0',
        latestVersion: '1.1.0',
        icon: null,
        iconRaw: null,
        active: true,
        type: 'plugin',
        isTheme: false,
        configurable: true,
        installedAt: { date: '2026-07-19T00:00:00.000Z' },
        updatedAt: null,
        allowUpdate: true,
        managedByComposer: false,
    };

    beforeAll(() => {
        Contena.Store.get('extensionEntryRoutes').routes = {
            ExamplePlugin: {
                route: 'test.foo',
                label: 'Open plugin',
            },
        };
    });

    beforeEach(() => {
        actionService = {
            installExtension: jest.fn(),
            updateExtension: jest.fn(),
            uninstallExtension: jest.fn(),
            removeExtension: jest.fn(),
            activateExtension: jest.fn(),
            deactivateExtension: jest.fn(),
            refresh: jest.fn(),
            getMyExtensions: jest.fn().mockResolvedValue([pluginExtension]),
        };
        service = new ContenaExtensionService(actionService);

        Contena.Store.get('context').app.config.settings ??= {};
        Contena.Store.get('context').app.config.settings.disableExtensionManagement = false;
        Contena.Store.get('contenaExtensions').setMyExtensions([]);
    });

    it.each([
        [
            'installExtension',
            ['ExamplePlugin'],
        ],
        [
            'updateExtension',
            ['ExamplePlugin'],
        ],
        [
            'uninstallExtension',
            [
                'ExamplePlugin',
                true,
            ],
        ],
        [
            'removeExtension',
            ['ExamplePlugin'],
        ],
    ])('delegates %s and reloads the plugin list', async (method, parameters) => {
        await service[method](...parameters);

        const expectedParameters = {
            installExtension: [
                'ExamplePlugin',
                'plugin',
            ],
            updateExtension: [
                'ExamplePlugin',
                'plugin',
                false,
            ],
            uninstallExtension: [
                'ExamplePlugin',
                'plugin',
                true,
            ],
            removeExtension: [
                'ExamplePlugin',
                'plugin',
                false,
            ],
        };

        expect(actionService[method]).toHaveBeenCalledWith(...expectedParameters[method]);
        expect(actionService.refresh).toHaveBeenCalledTimes(1);
        expect(actionService.getMyExtensions).toHaveBeenCalledTimes(1);
    });

    it('installs and activates a plugin before reloading the list', async () => {
        await service.installAndActivateExtension('ExamplePlugin');

        expect(actionService.installExtension).toHaveBeenCalledWith('ExamplePlugin', 'plugin');
        expect(actionService.activateExtension).toHaveBeenCalledWith('ExamplePlugin', 'plugin');
        expect(actionService.getMyExtensions).toHaveBeenCalledTimes(1);
    });

    it.each([
        'activateExtension',
        'deactivateExtension',
    ])('delegates %s without reloading the plugin list', async (method) => {
        await service[method]('ExamplePlugin');

        expect(actionService[method]).toHaveBeenCalledWith('ExamplePlugin', 'plugin');
        expect(actionService.getMyExtensions).not.toHaveBeenCalled();
    });

    it('maps DAL plugin entities into Administration plugin cards', async () => {
        await service.updateExtensionData();

        expect(Contena.Store.get('contenaExtensions').myExtensions.data).toEqual([
            expect.objectContaining({
                localId: 'plugin-id',
                name: 'ExamplePlugin',
                label: 'Example plugin',
                producerName: 'Contena',
                version: '1.0.0',
                latestVersion: '1.1.0',
                active: true,
                configurable: true,
                allowUpdate: true,
                managedByComposer: false,
            }),
        ]);
        expect(actionService.getMyExtensions).toHaveBeenCalledTimes(1);
    });

    it('still loads the local plugin list when discovery refresh fails', async () => {
        actionService.refresh.mockRejectedValue(new Error('refresh failed'));

        await service.updateExtensionData();

        expect(actionService.getMyExtensions).toHaveBeenCalledTimes(1);
        expect(Contena.Store.get('contenaExtensions').myExtensions.data).toEqual([pluginExtension]);
        expect(Contena.Store.get('contenaExtensions').myExtensions.loading).toBe(false);
    });

    it('can reload plugins without refreshing plugin discovery', async () => {
        await service.updateExtensionData(false);

        expect(actionService.refresh).not.toHaveBeenCalled();
        expect(actionService.getMyExtensions).toHaveBeenCalledTimes(1);
    });

    it('skips discovery refresh when plugin management is disabled', async () => {
        Contena.Store.get('context').app.config.settings.disableExtensionManagement = true;

        await service.updateExtensionData();

        expect(actionService.refresh).not.toHaveBeenCalled();
        expect(actionService.getMyExtensions).toHaveBeenCalledTimes(1);
    });

    it('returns the registered entry route for an active plugin', () => {
        expect(service.getOpenLink({ name: 'ExamplePlugin', active: true })).toEqual({
            name: 'test.foo',
            label: 'Open plugin',
        });
    });

    it.each([
        { name: 'InactivePlugin', active: false },
        { name: 'MissingPlugin', active: true },
    ])('does not expose an entry route for $name', (extension) => {
        expect(service.getOpenLink(extension)).toBeNull();
    });
});

import { mount } from '@vue/test-utils';

let wrapper;

const defaultExtension = {
    id: 'plugin-id',
    name: 'ExamplePlugin',
    label: 'Example plugin',
    version: '1.0.0',
    latestVersion: null,
    icon: null,
    iconRaw: null,
    active: false,
    configurable: false,
    installedAt: null,
    updatedAt: null,
    allowUpdate: false,
    managedByComposer: false,
};

async function createWrapper(extension = {}, services = {}) {
    const contenaExtensionService = {
        getOpenLink: jest.fn().mockReturnValue(null),
        updateExtension: jest.fn(),
        uninstallExtension: jest.fn(),
        removeExtension: jest.fn(),
        installAndActivateExtension: jest.fn(),
        installExtension: jest.fn(),
        activateExtension: jest.fn(),
        deactivateExtension: jest.fn(),
        ...services,
    };

    wrapper = mount(await wrapTestComponent('ct-extension-card-base', { sync: true }), {
        global: {
            provide: {
                contenaExtensionService,
                cacheApiService: {
                    clear: jest.fn().mockResolvedValue(),
                },
            },
            stubs: {
                'ct-loader': true,
                'ct-extension-icon': true,
                'ct-context-menu-item': {
                    props: ['routerLink'],
                    template: '<div class="ct-context-menu-item"><slot /></div>',
                },
                'ct-context-button': {
                    template: '<div class="ct-context-button"><slot /></div>',
                },
                'ct-extension-uninstall-modal': true,
                'ct-extension-removal-modal': true,
                'ct-meteor-card': {
                    template: '<div><slot /></div>',
                },
                'router-link': true,
                'ct-time-ago': true,
            },
        },
        props: {
            extension: {
                ...defaultExtension,
                ...extension,
            },
        },
    });

    return wrapper;
}

describe('src/module/ct-extension/component/ct-extension-card-base', () => {
    beforeAll(() => {
        if (Contena.Store.get('context')) {
            Contena.Store.unregister('context');
        }

        Contena.Store.register({
            id: 'context',
            state: () => ({
                app: {
                    config: {
                        settings: {
                            disableExtensionManagement: false,
                        },
                    },
                },
                api: {
                    assetPath: 'http://localhost:8000/bundles/administration/',
                },
            }),
        });
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        Contena.Store.get('context').app.config.settings.disableExtensionManagement = false;
        jest.restoreAllMocks();
    });

    it.each([
        [
            { icon: 'plugin-icon' },
            'plugin-icon',
        ],
        [
            { iconRaw: 'encoded-icon' },
            'data:image/png;base64, encoded-icon',
        ],
        [
            {},
            'administration/administration/static/img/services/extension-icon-placeholder.svg',
        ],
    ])('resolves the plugin image', async (extension, expectedImage) => {
        await createWrapper(extension);

        expect(wrapper.vm.image).toBe(expectedImage);
    });

    it('allows removing an uninstalled local plugin', async () => {
        await createWrapper();

        expect(wrapper.vm.isRemovable).toBe(true);
        expect(wrapper.text()).toContain('global.default.remove');
    });

    it('does not allow removing a Composer-managed plugin', async () => {
        await createWrapper({ managedByComposer: true });

        expect(wrapper.vm.isRemovable).toBe(false);
        expect(wrapper.text()).not.toContain('global.default.remove');
    });

    it('allows uninstalling an installed plugin', async () => {
        await createWrapper({ installedAt: { date: '2026-07-19T00:00:00.000Z' } });

        expect(wrapper.vm.isUninstallable).toBe(true);
        expect(wrapper.text()).toContain('ct-extension.component.ct-extension-card-base.contextMenu.uninstallLabel');
    });

    it('normalizes a UTC installation date before user timezone formatting', async () => {
        await createWrapper({
            installedAt: {
                date: '2026-07-19 08:30:00.000000',
                timezone: 'UTC',
            },
        });

        expect(wrapper.vm.installedAtDate).toBe('2026-07-19T08:30:00.000Z');
    });

    it('updates an installed plugin through the native plugin service', async () => {
        const updateExtension = jest.fn();
        await createWrapper(
            {
                installedAt: { date: '2026-07-19T00:00:00.000Z' },
                latestVersion: '1.1.0',
                allowUpdate: true,
            },
            { updateExtension },
        );
        wrapper.vm.reloadPage = jest.fn();

        await wrapper.vm.updateExtension();

        expect(updateExtension).toHaveBeenCalledWith('ExamplePlugin');
        expect(wrapper.vm.reloadPage).toHaveBeenCalledTimes(1);
    });

    it('installs and activates a discovered plugin through the native plugin service', async () => {
        const installAndActivateExtension = jest.fn();
        await createWrapper({}, { installAndActivateExtension });
        wrapper.vm.reloadPage = jest.fn();

        await wrapper.vm.installAndActivateExtension();

        expect(installAndActivateExtension).toHaveBeenCalledWith('ExamplePlugin');
        expect(wrapper.vm.reloadPage).toHaveBeenCalledTimes(1);
    });

    it('installs a discovered plugin through the native plugin service', async () => {
        const installExtension = jest.fn();
        await createWrapper({}, { installExtension });
        wrapper.vm.reloadPage = jest.fn();

        await wrapper.vm.installExtension();

        expect(installExtension).toHaveBeenCalledWith('ExamplePlugin');
        expect(wrapper.vm.reloadPage).toHaveBeenCalledTimes(1);
    });

    it('activates an installed plugin through the native plugin service', async () => {
        const activateExtension = jest.fn();
        await createWrapper({ installedAt: { date: '2026-07-19T00:00:00.000Z' } }, { activateExtension });
        wrapper.vm.reloadPage = jest.fn();

        await wrapper.vm.activateExtension();

        expect(activateExtension).toHaveBeenCalledWith('ExamplePlugin');
        expect(wrapper.vm.extension.active).toBe(true);
        expect(wrapper.vm.reloadPage).toHaveBeenCalledTimes(1);
    });

    it('deactivates an installed plugin through the native plugin service', async () => {
        const deactivateExtension = jest.fn();
        await createWrapper({ installedAt: { date: '2026-07-19T00:00:00.000Z' }, active: true }, { deactivateExtension });
        wrapper.vm.reloadPage = jest.fn();

        await wrapper.vm.deactivateExtension();

        expect(deactivateExtension).toHaveBeenCalledWith('ExamplePlugin');
        expect(wrapper.vm.extension.active).toBe(false);
        expect(wrapper.vm.reloadPage).toHaveBeenCalledTimes(1);
    });

    it('passes the data-removal choice when uninstalling a plugin', async () => {
        const uninstallExtension = jest.fn();
        await createWrapper({ installedAt: { date: '2026-07-19T00:00:00.000Z' } }, { uninstallExtension });
        wrapper.vm.reloadPage = jest.fn();

        await wrapper.vm.closeModalAndUninstallExtension(true);

        expect(uninstallExtension).toHaveBeenCalledWith('ExamplePlugin', true);
    });

    it('uses the registered plugin entry route', async () => {
        await createWrapper(
            {
                installedAt: { date: '2026-07-19T00:00:00.000Z' },
                active: true,
            },
            {
                getOpenLink: jest.fn().mockReturnValue({ name: 'plugin.example.index' }),
            },
        );
        await flushPromises();

        expect(wrapper.vm.link).toEqual({ name: 'plugin.example.index' });
        expect(wrapper.text()).toContain('ct-extension.component.ct-extension-card-base.contextMenu.openExtension');
    });

    it('hides lifecycle actions when extension management is disabled', async () => {
        Contena.Store.get('context').app.config.settings.disableExtensionManagement = true;
        await createWrapper();

        expect(wrapper.find('.ct-context-button').exists()).toBe(false);
    });
});

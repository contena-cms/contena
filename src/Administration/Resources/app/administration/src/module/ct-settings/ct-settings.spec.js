const { Module } = Contena;
const ModuleFactory = Module;
const register = ModuleFactory.register;
const { hasOwnProperty } = Contena.Utils.object;

describe('src/module/ct-settings', () => {
    let settingsIndex;

    beforeEach(async () => {
        const modules = ModuleFactory.getModuleRegistry();
        modules.clear();

        Contena.Store.get('settingsItems').settingsGroups = {};

        settingsIndex = {
            type: 'core',
            name: 'settings',

            routes: {
                index: {
                    component: 'ct-settings-index',
                    path: 'index',
                    icon: 'default-action-settings',
                },
            },
        };
    });

    it('should not contain any registered settings items', async () => {
        register('ct-settings-foo', settingsIndex);

        const settingsGroups = Contena.Store.get('settingsItems').settingsGroups;

        expect(settingsGroups).toEqual({});
    });

    it('should contain registered settings items group', async () => {
        settingsIndex.settingsItem = [
            {
                group: 'general',
                to: 'ct.settings.address.index',
                icon: 'default-object-address',
            },
        ];
        register('ct-settings-foo', settingsIndex);

        const settingsGroups = Contena.Store.get('settingsItems').settingsGroups;

        expect(hasOwnProperty(settingsGroups, 'general')).toBe(true);
    });

    it('should register a specific key for the defined group property in the settings items', async () => {
        settingsIndex.settingsItem = [
            {
                group: 'general',
                to: 'ct.settings.address.index',
                icon: 'default-object-address',
                name: 'ct-settings-address-foo',
            },
            {
                group: 'general',
                to: 'ct.settings.tax.index',
                icon: 'default-chart-pie',
                name: 'ct-settings-tax-foo',
            },
            {
                group: 'system',
                to: 'ct.settings.store.index',
                icon: 'default-device-laptop',
                name: 'ct-settings-store-foo',
            },
            {
                group: 'plugins',
                to: 'ct.paypal.index',
                icon: 'paypal-default',
                name: 'CtPayPal',
            },
        ];
        register('ct-settings-foo', settingsIndex);

        const settingsGroups = Contena.Store.get('settingsItems').settingsGroups;

        expect(settingsGroups.general).toHaveLength(2);
        expect(settingsGroups.system).toHaveLength(1);
        expect(settingsGroups.plugins).toHaveLength(1);
    });

    it('should only allow unique settings items name per group', async () => {
        settingsIndex.settingsItem = [
            {
                group: 'general',
                to: 'ct.settings.address.index',
                icon: 'default-object-address',
                name: 'foo',
            },
            {
                group: 'general',
                to: 'ct.settings.address.index',
                icon: 'default-object-address',
                name: 'foo',
            },
        ];
        register('ct-settings-foo', settingsIndex);

        const settingsGroups = Contena.Store.get('settingsItems').settingsGroups;

        expect(settingsGroups.general).toHaveLength(1);
    });

    it('should allow to add settings items with duplicate name in different groups', async () => {
        settingsIndex.settingsItem = [
            {
                group: 'general',
                to: 'ct.settings.address.index',
                icon: 'default-object-address',
                name: 'foo',
            },
            {
                group: 'system',
                to: 'ct.settings.address.index',
                icon: 'default-object-address',
                name: 'foo',
            },
        ];
        register('ct-settings-foo', settingsIndex);

        const settingsGroups = Contena.Store.get('settingsItems').settingsGroups;

        expect(settingsGroups.general).toHaveLength(1);
        expect(settingsGroups.general[0].name).toBe('foo');
        expect(settingsGroups.system).toHaveLength(1);
        expect(settingsGroups.system[0].name).toBe('foo');
    });
});

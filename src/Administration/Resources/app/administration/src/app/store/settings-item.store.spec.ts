import type { SettingsItem } from './settings-item.store';

describe('settings-item.store', () => {
    const store = Contena.Store.get('settingsItems');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.settingsGroups).toStrictEqual({
            account: [],
            automation: [],
            content: [],
            general: [],
            localization: [],
            plugins: [],
            system: [],
        });
    });

    it('should add item', () => {
        Contena.Store.get('settingsItems').addItem({
            group: 'advanced',
            name: 'item1',
            icon: 'icon',
            label: 'Item Example',
            to: {
                name: 'ct.settings.index',
            },
        });

        expect(store.settingsGroups.advanced).toStrictEqual([
            {
                group: 'advanced',
                name: 'item1',
                icon: 'icon',
                label: 'Item Example',
                to: {
                    name: 'ct.settings.index',
                },
            },
        ]);

        Contena.Store.get('settingsItems').addItem({
            group: 'system',
            name: 'item2',
            icon: 'icon',
            label: 'Item Example',
            to: {
                name: 'ct.settings.index',
            },
        });

        expect(store.settingsGroups.system).toStrictEqual([
            {
                group: 'system',
                name: 'item2',
                icon: 'icon',
                label: 'Item Example',
                to: {
                    name: 'ct.settings.index',
                },
            },
        ]);

        Contena.Store.get('settingsItems').addItem({
            group: 'plugins',
            name: 'item3',
            icon: 'icon',
            label: 'Item Example',
            to: {
                name: 'ct.settings.index',
            },
        });

        expect(store.settingsGroups.plugins).toStrictEqual([
            {
                group: 'plugins',
                name: 'item3',
                icon: 'icon',
                label: 'Item Example',
                to: {
                    name: 'ct.settings.index',
                },
            },
        ]);
    });

    it('adds a new item to the specified group', () => {
        const settingsItem = {
            group: 'general',
            name: 'newSetting',
        };

        store.addItem(settingsItem);

        expect(store.settingsGroups.general).toHaveLength(1);
        expect(store.settingsGroups.general[0]).toEqual(settingsItem);
    });

    it('does not add a duplicate item to the specified group', () => {
        const settingsItem = {
            group: 'general',
            name: 'newSetting',
        };

        store.addItem(settingsItem);
        store.addItem(settingsItem);

        expect(store.settingsGroups.general).toHaveLength(1);
    });

    it('creates a new group dynamically if the group does not exist', () => {
        const settingsItem = {
            group: 'customGroup',
            name: 'customSetting',
        };

        store.addItem(settingsItem);

        expect(store.settingsGroups[settingsItem.group]).toBeDefined();
        expect(store.settingsGroups[settingsItem.group]).toHaveLength(1);
        expect(store.settingsGroups[settingsItem.group][0]).toEqual(settingsItem);
    });

    it('handles group as a function', () => {
        const settingsItem = {
            group: () => 'dynamicGroup',
            name: 'dynamicSetting',
        };

        store.addItem(settingsItem);

        expect(store.settingsGroups[settingsItem.group()]).toBeDefined();
        expect(store.settingsGroups[settingsItem.group()]).toHaveLength(1);
        expect(store.settingsGroups[settingsItem.group()][0]).toEqual({
            group: settingsItem.group,
            name: 'dynamicSetting',
        });
    });

    it('throws an error if the group is undefined', () => {
        const settingsItem = {
            group: undefined,
            name: 'orphanSetting',
        } as unknown as SettingsItem;

        expect(() => store.addItem(settingsItem)).toThrow('Group is undefined or invalid');
    });
});

import { mount } from '@vue/test-utils';

async function createWrapper(
    privileges = [
        'plugin.viewer',
        'user.viewer',
        'media.viewer',
        'country.viewer',
        'custom_field.viewer',
        'state_machine.viewer',
    ],
) {
    const settingsItemsMock = [
        {
            group: 'system',
            to: 'ct.settings.country.index',
            icon: 'regular-globe',
            id: 'ct-settings-country',
            name: 'settings-country',
            label: 'Countries',
            privilege: 'country.viewer',
        },
        {
            group: 'system',
            to: 'ct.settings.user.list',
            icon: 'regular-user',
            id: 'ct-settings-user',
            name: 'settings-user',
            label: 'Users & Permissions',
            privilege: 'user.viewer',
        },
        {
            group: 'system',
            to: 'ct.settings.media.index',
            icon: 'regular-image',
            id: 'ct-settings-media',
            name: 'settings-media',
            label: 'User media settings',
            privilege: 'media.viewer',
        },
        {
            group: 'system',
            to: 'ct.settings.custom.field.index',
            icon: 'regular-sliders',
            id: 'ct-settings-custom-field',
            name: 'settings-custom-field',
            label: 'Custom fields',
            privilege: 'custom_field.viewer',
        },
        {
            group: 'plugins',
            to: {
                name: 'ct.extension.config',
                params: {
                    namespace: 'ExamplePlugin',
                },
            },
            icon: 'regular-books',
            id: 'ct-extension-books',
            name: 'settings-plugin-book',
            label: {
                translated: true,
                label: 'plugin-settings',
            },
        },
        {
            group: 'plugins',
            to: {
                name: 'ct.extension.config',
                params: {
                    namespace: 'AnotherPlugin',
                },
            },
            icon: 'regular-books',
            id: 'ct-extension-briefcase',
            name: 'settings-plugin-briefcase',
            label: {
                translated: false,
                label: 'general.no',
            },
        },
    ];

    settingsItemsMock.forEach((settingsItem) => {
        Contena.Store.get('settingsItems').addItem(settingsItem);
    });

    return mount(
        await wrapTestComponent('ct-settings-index', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $t: (path) => {
                        if (typeof path !== 'string') {
                            return `${path}`;
                        }
                        return path;
                    },
                },
                stubs: {
                    'ct-page': {
                        template: '<div><slot name="content"></slot></div>',
                    },
                    'ct-card-view': {
                        template: '<div class="ct-card-view"><slot></slot></div>',
                    },
                    'ct-settings-item': await wrapTestComponent('ct-settings-item'),
                    'mt-search': {
                        template: '<div class="mt-search"><slot></slot></div>',
                    },
                    'ct-highlight-text': await wrapTestComponent('ct-highlight-text'),
                    'router-link': {
                        template: '<a><slot></slot></a>',
                    },
                    'ct-extension-component-section': true,
                },
                provide: {
                    acl: {
                        can: (key) => {
                            if (!key) return true;

                            return privileges.includes(key);
                        },
                    },
                },
            },
        },
    );
}

describe('module/ct-settings/page/ct-settings-index', () => {
    beforeEach(async () => {
        Contena.Store.get('settingsItems').settingsGroups = {};
    });

    it('should contain any settings items', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm.settingsGroups).not.toEqual({});
    });

    it('should return settings items alphabetically sorted', async () => {
        const wrapper = await createWrapper();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(
            ([
                ,
                settingsItems,
            ]) => {
                settingsItems.forEach((settingsItem, index) => {
                    let elementsSorted = true;

                    if (index < settingsItems.length - 1 && typeof settingsItems[index].label === 'string') {
                        elementsSorted = settingsItems[index].label.localeCompare(settingsItems[index + 1].label) === -1;
                    }

                    expect(elementsSorted).toBe(true);
                });
            },
        );
    });

    it('should render settings items in alphabetical order', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(
            ([
                settingsGroup,
                settingsItems,
            ]) => {
                const settingsGroupWrapper = wrapper.find(`#ct-settings__content-group-${settingsGroup}`);
                const settingsItemsWrappers = settingsGroupWrapper.findAll('.ct-settings-item');

                // check, that all settings items were rendered
                expect(settingsItemsWrappers).toHaveLength(settingsItems.length);

                // check, that settings items were rendered in alphabetical order
                settingsItemsWrappers.forEach((settingsItemsWrapper, index) => {
                    expect(settingsItemsWrapper.text()).toContain(wrapper.vm.getLabel(settingsItems[index]));
                });
            },
        );
    });

    it('should render settings items in alphabetical order with updated items', async () => {
        const settingsItemToAdd = {
            group: 'advanced',
            to: 'ct.bar.index',
            icon: 'regular-cog',
            id: 'ct-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Contena.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();
        await flushPromises();
        const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

        settingsGroups.forEach(
            ([
                settingsGroup,
                settingsItems,
            ]) => {
                const settingsGroupWrapper = wrapper.find(`#ct-settings__content-group-${settingsGroup}`);
                const settingsItemsWrappers = settingsGroupWrapper.findAll('.ct-settings-item');

                expect(settingsItemsWrappers).toHaveLength(settingsItems.length);

                settingsItemsWrappers.forEach((settingsItemsWrapper, index) => {
                    expect(settingsItemsWrapper.text()).toContain(wrapper.vm.getLabel(settingsItems[index]));
                });
            },
        );
    });

    it('should add the setting to the settingsGroups in store', async () => {
        const settingsItemToAdd = {
            group: 'advanced',
            to: 'ct.bar.index',
            icon: 'regular-cog',
            id: 'ct-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Contena.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();

        const barSetting = wrapper.vm.settingsGroups.advanced?.find((setting) => setting.id === 'ct-settings-bar');

        expect(barSetting).toBeDefined();
    });

    it('should show the setting with the privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'advanced',
            to: 'ct.bar.index',
            icon: 'regular-cog',
            id: 'ct-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Contena.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper('system.foo_bar');

        const settingsGroups = wrapper.vm.settingsGroups.advanced;
        const barSetting = settingsGroups.find((setting) => setting.id === 'ct-settings-bar');

        expect(barSetting).toBeDefined();
    });

    it('should not show the setting with the privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'advanced',
            to: 'ct.bar.index',
            icon: 'regular-cog',
            id: 'ct-settings-bar',
            name: 'settings-bar',
            label: 'b',
        };

        Contena.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();

        const barSetting = wrapper.vm.settingsGroups.advanced?.find((setting) => setting.id === 'ct-settings-bar');

        expect(barSetting).toBeUndefined();
    });

    it('should correctly resolve dynamic group functions and add the item', async () => {
        const settingsItemToAdd = {
            group: () => 'dynamicGroup',
            to: 'ct.dynamic.index',
            icon: 'regular-cog',
            id: 'ct-dynamic-setting',
            name: 'settings-dynamic',
            label: 'Dynamic Setting',
        };

        Contena.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper();
        await flushPromises();

        const dynamicGroup = wrapper.vm.settingsGroups.dynamicGroup;
        expect(dynamicGroup).toBeDefined();
        expect(dynamicGroup).toHaveLength(1);
        expect(dynamicGroup[0]).toEqual(settingsItemToAdd);
    });

    it('should display settings items based on user privileges', async () => {
        const settingsItemToAdd = {
            privilege: 'system.foo_bar',
            group: 'advanced',
            to: 'ct.bar.index',
            icon: 'regular-cog',
            id: 'ct-settings-bar',
            name: 'settings-bar',
            label: 'Bar Setting',
        };

        Contena.Store.get('settingsItems').addItem(settingsItemToAdd);

        const wrapper = await createWrapper(['system.foo_bar']);
        const advancedGroup = wrapper.vm.settingsGroups.advanced;

        const barSetting = advancedGroup.find((setting) => setting.id === 'ct-settings-bar');
        expect(barSetting).toBeDefined();
    });

    describe('search functionality', () => {
        it('should filter items based on search term (term is part of label, case insensitive, white space around)', async () => {
            const wrapper = await createWrapper();
            wrapper.vm.searchQuery = '  uSeR  ';
            await wrapper.vm.$nextTick();

            const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

            expect(settingsGroups).toHaveLength(1);
            const [
                groupName,
                settingsItems,
            ] = settingsGroups[0];
            expect(groupName).toBe('system');
            expect(settingsItems).toStrictEqual([
                {
                    group: 'system',
                    to: 'ct.settings.media.index',
                    icon: 'regular-image',
                    id: 'ct-settings-media',
                    name: 'settings-media',
                    label: 'User media settings',
                    privilege: 'media.viewer',
                },
                {
                    group: 'system',
                    to: 'ct.settings.user.list',
                    icon: 'regular-user',
                    id: 'ct-settings-user',
                    name: 'settings-user',
                    label: 'Users & Permissions',
                    privilege: 'user.viewer',
                },
            ]);
        });

        it('should filter items based on search term (label is part of term)', async () => {
            // Item 'Countries' is expected to be found with search term 'Countries and more'
            const wrapper = await createWrapper();
            wrapper.vm.searchQuery = 'Countries and more';
            await wrapper.vm.$nextTick();

            const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

            expect(settingsGroups).toHaveLength(1);
            const [
                groupName,
                settingsItems,
            ] = settingsGroups[0];
            expect(groupName).toBe('system');
            expect(settingsItems).toStrictEqual([
                {
                    group: 'system',
                    to: 'ct.settings.country.index',
                    icon: 'regular-globe',
                    id: 'ct-settings-country',
                    name: 'settings-country',
                    label: 'Countries',
                    privilege: 'country.viewer',
                },
            ]);
        });

        it('should show empty state when no settings items are available due to search filtering', async () => {
            const wrapper = await createWrapper();
            wrapper.vm.searchQuery = 'non-existing';
            await wrapper.vm.$nextTick();

            const settingsGroups = Object.entries(wrapper.vm.settingsGroups);

            expect(settingsGroups).toHaveLength(0);

            const emptyState = wrapper.findComponent({ name: 'mt-empty-state' });
            expect(emptyState.exists()).toBe(true);
        });
    });
});

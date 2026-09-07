import { shallowMount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';

async function createWrapper(
    entry = {},
    props = {},
    can = () => true,
    route = { name: '', matched: [], meta: {}, params: {}, query: {} },
) {
    entry = {
        id: 'entry',
        label: 'entry.label',
        icon: 'regular-dashboard',
        children: [],
        ...entry,
    };

    return shallowMount(await wrapTestComponent('ct-admin-menu-item', { sync: true }), {
        props: { entry, ...props },
        global: {
            provide: {
                acl: { can, hasActiveSettingModules: () => true },
                [routeLocationKey]: route,
                [routerKey]: {
                    resolve: () => ({ meta: {} }),
                    getRoutes: () => [],
                },
            },
            stubs: {
                'ct-block': {
                    props: [
                        'name',
                        'data',
                    ],
                    template: '<div><slot /></div>',
                },
                'mt-tooltip': {
                    template: '<div><slot v-bind="{ onMouseover: () => {}, onMouseleave: () => {} }" /></div>',
                },
            },
        },
    });
}

describe('src/app/component/structure/ct-admin-menu-item', () => {
    beforeEach(() => {
        Contena.Store.get('adminMenu').adminModuleNavigation = [];
    });

    it('uses a solid icon for an active navigation entry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.getIconName('regular-dashboard', true)).toBe('solid-dashboard');
        expect(wrapper.vm.getIconName('icon/regular/settings', true)).toBe('icon/solid/settings');
    });

    it('keeps the regular icon when the active state is disabled', async () => {
        const wrapper = await createWrapper({}, { showActiveState: false });

        expect(wrapper.vm.navigationIconName).toBe('regular-dashboard');
    });

    it('filters children by ACL privilege', async () => {
        const wrapper = await createWrapper(
            {
                id: 'root',
                children: [
                    { id: 'public' },
                    { id: 'allowed', privilege: 'media.viewer' },
                    { id: 'denied', privilege: 'user.viewer' },
                ],
            },
            {},
            (privilege) => privilege === 'media.viewer',
        );

        expect(wrapper.vm.children.map(({ id }) => id)).toEqual([
            'public',
            'allowed',
        ]);
    });

    it('renders grouping entries when the current module has no navigation entries', async () => {
        const wrapper = await createWrapper(
            {
                id: 'ct-content',
                children: [{ id: 'ct-blog', path: 'ct.blog.index' }],
            },
            {},
            () => true,
            {
                name: 'ct.channel.detail',
                matched: [{ name: 'ct.channel.detail' }],
                meta: { $module: { navigation: [] } },
                params: { id: 'channel-id' },
                query: {},
            },
        );

        expect(wrapper.vm.showMenuItem).toBe(true);
        expect(wrapper.find('.ct-admin-menu__navigation-list-item').exists()).toBe(true);
    });

    it('emits branch changes for expanded top-level entries', async () => {
        const entry = { id: 'ct-content', children: [] };
        const wrapper = await createWrapper(entry);

        wrapper.vm.onCollapsibleOpenUpdate(true);

        expect(wrapper.emitted('branch-toggle')).toEqual([[{ entry: wrapper.props('entry'), open: true }]]);
    });

    it('keeps nested branch state locally', async () => {
        const wrapper = await createWrapper({ id: 'ct-media', children: [] }, { menuDepth: 2 });

        wrapper.vm.onCollapsibleOpenUpdate(true);

        expect(wrapper.vm.manualNestedOpen).toBe(true);
        expect(wrapper.emitted('branch-toggle')).toBeUndefined();
    });

    describe('module icon colors', () => {
        const blogEntry = {
            id: 'ct-blog',
            label: 'ct-blog.general.mainMenuItemGeneral',
            color: 'var(--color-module-green-default)',
            path: 'ct.blog.index',
            icon: 'regular-file-text',
            position: 10,
            level: 1,
            moduleType: 'core',
            children: [],
        };

        afterEach(() => {
            useModuleIconColors().enabled.value = false;
        });

        it('should leave the icon color to the stylesheet by default', async () => {
            const wrapper = await createWrapper(blogEntry);

            expect(wrapper.vm.navigationIconColor).toBeUndefined();
            expect(wrapper.find('.ct-admin-menu__navigation-link-icon').attributes('style')).not.toContain('color');
        });

        it('should paint the icon in the module color when the preference is enabled', async () => {
            useModuleIconColors().enabled.value = true;

            const wrapper = await createWrapper(blogEntry);

            expect(wrapper.vm.navigationIconColor).toBe('var(--color-module-green-default)');
            expect(wrapper.find('.ct-admin-menu__navigation-link-icon').attributes('style')).toContain(
                'color: var(--color-module-green-default)',
            );
        });

        it('should not mark the row as module colored by default', async () => {
            const wrapper = await createWrapper(blogEntry);

            expect(wrapper.find('.ct-admin-menu__navigation-list-item').classes()).not.toContain('is--module-colored');
        });

        it('should mark the row as module colored so the active state drops the brand tint', async () => {
            useModuleIconColors().enabled.value = true;

            const wrapper = await createWrapper(blogEntry);

            expect(wrapper.find('.ct-admin-menu__navigation-list-item').classes()).toContain('is--module-colored');
        });

        it('should expose the module color to sub items as a custom property', async () => {
            useModuleIconColors().enabled.value = true;

            const wrapper = await createWrapper({
                ...blogEntry,
                children: [{ id: 'ct-blog-list', path: 'ct.blog.index' }],
            });
            await flushPromises();

            expect(wrapper.find('.ct-admin-menu__navigation-list-item').attributes('style')).toContain(
                '--ct-admin-menu-module-color: var(--color-module-green-default)',
            );
        });

        it('should not expose a module color while the preference is off', async () => {
            const wrapper = await createWrapper({
                ...blogEntry,
                children: [{ id: 'ct-blog-list', path: 'ct.blog.index' }],
            });
            await flushPromises();

            expect(wrapper.find('.ct-admin-menu__navigation-list-item').attributes('style')).toBeUndefined();
        });

        it('should not mark rows without a module color', async () => {
            useModuleIconColors().enabled.value = true;

            const wrapper = await createWrapper({ ...blogEntry, color: undefined });

            expect(wrapper.find('.ct-admin-menu__navigation-list-item').classes()).not.toContain('is--module-colored');
        });
    });
});

import { shallowMount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

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
});

import { shallowMount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

async function createWrapper(
    navigation = [],
    route = { name: '', path: '/', fullPath: '/', matched: [], meta: {} },
    {
        can = () => true,
        userConfigService = {
            search: jest.fn().mockResolvedValue({ data: {} }),
            upsert: jest.fn().mockResolvedValue(undefined),
        },
        systemConfigApiService = {
            getValues: jest.fn().mockResolvedValue({}),
        },
        mountOptions = {},
    } = {},
) {
    const adminMenuStore = Contena.Store.get('adminMenu');
    adminMenuStore.isExpanded = true;
    adminMenuStore.expandedEntries = [];
    adminMenuStore.adminModuleNavigation = navigation;

    return shallowMount(await wrapTestComponent('ct-admin-menu', { sync: true }), {
        ...mountOptions,
        global: {
            provide: {
                menuService: { getNavigationFromAdminModules: () => navigation },
                loginService: { notifyOnLoginListener: jest.fn(), getToken: jest.fn(), logout: jest.fn() },
                userService: { getUser: jest.fn().mockResolvedValue({ data: {} }) },
                systemConfigApiService,
                userConfigService,
                acl: { can },
                [routeLocationKey]: route,
                [routerKey]: {
                    resolve: () => ({ meta: {} }),
                    getRoutes: () =>
                        navigation.filter(({ path }) => path).map(({ path, meta = {} }) => ({ name: path, meta })),
                },
            },
            stubs: {
                'mt-avatar': true,
                'mt-badge': {
                    template: '<span class="mt-badge"><slot /></span>',
                },
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

describe('src/app/component/structure/ct-admin-menu', () => {
    afterEach(() => {
        jest.useRealTimers();
        document.body.innerHTML = '';
    });

    it('uses the versioned Contena logo asset', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.ct-admin-menu__header-logo').attributes('src')).toContain('contena-logo-v4.svg');
    });

    it('shows the configured project name', async () => {
        const wrapper = await createWrapper([], undefined, {
            systemConfigApiService: {
                getValues: jest.fn().mockResolvedValue({ 'core.basicInformation.siteName': 'My Project' }),
            },
        });

        await flushPromises();

        expect(wrapper.get('.ct-admin-menu__shop-name').text()).toBe('My Project');
    });

    it('falls back to Contena when the project name cannot be loaded', async () => {
        const wrapper = await createWrapper([], undefined, {
            systemConfigApiService: {
                getValues: jest.fn().mockRejectedValue(new Error('forbidden')),
            },
        });

        await flushPromises();

        expect(wrapper.get('.ct-admin-menu__shop-name').text()).toBe('Contena');
    });

    it('uses only local Administration module navigation', async () => {
        const navigation = [{ id: 'ct-dashboard', children: [], position: 1 }];
        const wrapper = await createWrapper(navigation);

        expect(wrapper.vm.navigationEntries).toEqual(navigation);
    });

    it('closes an inactive branch when another branch is opened', async () => {
        const branchA = { id: 'ct-content', level: 1, position: 1, children: [] };
        const branchB = { id: 'ct-member', level: 1, position: 2, children: [] };
        const wrapper = await createWrapper([
            branchA,
            branchB,
        ]);
        const store = Contena.Store.get('adminMenu');
        store.expandedEntries = [branchA];
        const collapseMenuEntry = jest.spyOn(store, 'collapseMenuEntry');
        const expandMenuEntry = jest.spyOn(store, 'expandMenuEntry');

        wrapper.vm.onMenuBranchToggle({ entry: branchB, open: true });

        expect(collapseMenuEntry).toHaveBeenCalledWith(branchA);
        expect(expandMenuEntry).toHaveBeenCalledWith(branchB);
    });

    it('collapses a closed top-level branch', async () => {
        const wrapper = await createWrapper();
        const collapseMenuEntry = jest.spyOn(Contena.Store.get('adminMenu'), 'collapseMenuEntry');
        const entry = { id: 'ct-content', level: 1, children: [] };

        wrapper.vm.onMenuBranchToggle({ entry, open: false });

        expect(collapseMenuEntry).toHaveBeenCalledWith(entry);
    });

    it('shows tooltips only for collapsed leaf entries', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.viewportWidth = 1441;
        Contena.Store.get('adminMenu').isExpanded = false;

        expect(wrapper.vm.getSingleChildTooltipConfig({ id: 'ct-dashboard', label: 'Dashboard', children: [] })).toEqual({
            message: 'Dashboard',
            disabled: false,
        });
    });

    it('suppresses sidebar transitions while the viewport is resizing', async () => {
        jest.useFakeTimers();
        const wrapper = await createWrapper();

        wrapper.vm.onViewportResize();
        await wrapper.vm.$nextTick();
        expect(wrapper.get('aside.ct-admin-menu').classes()).toContain('is--viewport-resizing');

        jest.advanceTimersByTime(200);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('aside.ct-admin-menu').classes()).not.toContain('is--viewport-resizing');
    });

    it('keeps a closed mobile menu out of the tab order', async () => {
        const wrapper = await createWrapper();
        const menu = wrapper.get('aside.ct-admin-menu');

        wrapper.vm.viewportWidth = 1280;
        await wrapper.vm.$nextTick();
        expect(menu.attributes('inert')).toBe('true');

        wrapper.vm.onToggleCanvas(true);
        await wrapper.vm.$nextTick();
        expect(menu.attributes('inert')).toBe('false');
    });

    it('closes the off-canvas menu when Escape deactivates its focus trap', async () => {
        const wrapper = await createWrapper([], undefined, {
            mountOptions: { attachTo: document.body },
        });
        wrapper.vm.viewportWidth = 1280;
        wrapper.vm.onToggleCanvas(true);
        await flushPromises();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await flushPromises();

        expect(wrapper.vm.isOffCanvasShown).toBe(false);
        wrapper.unmount();
    });

    it('closes mobile navigation overlays after navigation', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.viewportWidth = 375;
        wrapper.vm.onToggleCanvas(true);
        await wrapper.vm.$nextTick();

        wrapper.vm.onNavigationLinkClicked();

        expect(wrapper.vm.isOffCanvasShown).toBe(false);
    });

    it('keeps flyout focusout open while branch navigation is pinned', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.onFlyoutNavigate({ disclosesChildren: true });

        expect(wrapper.vm.isSuppressedFlyoutFocusOut({ type: 'focusout' })).toBe(true);
        expect(wrapper.vm.isSuppressedFlyoutFocusOut({ type: 'mouseleave' })).toBe(false);

        wrapper.vm.onFlyoutNavigate({ disclosesChildren: false });
        expect(wrapper.vm.isSuppressedFlyoutFocusOut({ type: 'focusout' })).toBe(false);
    });

    it('moves focus through visible navigation links with keyboard controls', async () => {
        const wrapper = await createWrapper();
        const container = document.createElement('div');
        container.innerHTML = `
            <a class="ct-admin-menu__navigation-link" href="#first">First</a>
            <div hidden><a class="ct-admin-menu__navigation-link" href="#hidden">Hidden</a></div>
            <a class="ct-admin-menu__navigation-link" href="#last">Last</a>
        `;
        document.body.appendChild(container);
        const links = wrapper.vm.getNavigationLinks(container);
        links[0].focus();

        const event = { key: 'ArrowDown', preventDefault: jest.fn() };
        wrapper.vm.moveListFocus(links, event);

        expect(document.activeElement).toBe(links[1]);
        expect(event.preventDefault).toHaveBeenCalled();
    });

    it('provides the current user identity as the user menu accessible name', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        Contena.Store.get('session').setCurrentUser({ firstName: 'Max', lastName: 'Mustermann', admin: true });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.userActionsAriaLabel).toBe('Max Mustermann, global.ct-admin-menu.administrator');
    });

    it('expands the active route top-level ancestor', async () => {
        const root = { id: 'ct-content', level: 1, position: 1, children: [] };
        const leaf = { id: 'ct-media', parent: 'ct-content', path: 'ct.media.index', level: 2, position: 1, children: [] };
        const wrapper = await createWrapper(
            [
                root,
                leaf,
            ],
            {
                name: 'ct.media.index',
                path: '/sw/media/index',
                fullPath: '/sw/media/index',
                matched: [{ name: 'ct.media.index' }],
                meta: {},
            },
        );
        const store = Contena.Store.get('adminMenu');
        const expandMenuEntry = jest.spyOn(store, 'expandMenuEntry');
        store.expandedEntries = [];

        wrapper.vm.expandAncestorBranchesForCurrentRoute();

        expect(expandMenuEntry).toHaveBeenCalledWith(expect.objectContaining({ id: 'ct-content' }));
    });

    it('does not expose navigation layout controls', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-admin-menu__layout-label').exists()).toBe(false);
        expect(wrapper.vm.onSelectLayout).toBeUndefined();
    });

    it('keeps the product identity free of edition labels', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-admin-menu__edition').exists()).toBe(false);
        expect(wrapper.find('.ct-admin-menu__shop-name').exists()).toBe(true);
    });
});

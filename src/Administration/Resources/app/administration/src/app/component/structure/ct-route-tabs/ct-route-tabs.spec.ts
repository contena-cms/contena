import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import component from './ct-route-tabs.vue';
import 'src/app/store/route-tabs.store';

const routes = [
    {
        name: 'ct.dashboard.index',
        path: '/dashboard',
        component: { template: '<div />' },
        meta: { $module: { title: 'dashboard.title' } },
    },
    {
        name: 'ct.users.index',
        path: '/users',
        component: { template: '<div />' },
        meta: { $current: { label: 'users.title' } },
    },
    {
        name: 'ct.users.user.create',
        path: '/users/create',
        component: { template: '<div />' },
        meta: { parentPath: 'ct.users.index', $current: { label: 'users.title' } },
    },
];

async function createWrapper(initialRoute = '/users') {
    setActivePinia(createPinia());
    const routeTabs = Contena.Store.get('routeTabs');
    routeTabs.tabs = [];
    const router = createRouter({ history: createMemoryHistory(), routes });
    await router.push(initialRoute);
    await router.isReady();

    const wrapper = mount(component, {
        global: {
            plugins: [router],
            stubs: {
                'ct-block': { template: '<div><slot /></div>' },
                'a-tabs': { template: '<div><slot /><slot name="rightExtra" /></div>' },
                'a-tab-pane': { template: '<div><slot name="tab" /></div>' },
                'a-dropdown': { template: '<div><slot /><slot name="overlay" /></div>' },
                'a-button': { template: '<button><slot name="icon" /><slot /></button>' },
                'a-menu': { template: '<div><slot /></div>' },
                'a-menu-item': { template: '<div><slot /></div>' },
                'a-menu-divider': true,
                'ct-icon': true,
            },
        },
    });
    await flushPromises();

    return { wrapper, router, routeTabs };
}

describe('app/component/structure/ct-route-tabs', () => {
    it('retains the dashboard and adds the current real route', async () => {
        const { routeTabs } = await createWrapper();

        expect(routeTabs.tabs).toEqual([
            expect.objectContaining({ key: '/dashboard', pinned: true }),
            expect.objectContaining({ key: '/users', routeName: 'ct.users.index', title: 'users.title' }),
        ]);
    });

    it('uses an action suffix for create and detail routes', async () => {
        const { routeTabs } = await createWrapper('/users/create');

        expect(routeTabs.tabs[1].title).toBe('users.title - global.default.add');
    });

    it('switches tabs through the router', async () => {
        const { wrapper, router } = await createWrapper();

        wrapper.vm.onTabChange('/dashboard');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('ct.dashboard.index');
    });

    it('refreshes and closes the active tab through Ant menu actions', async () => {
        const { wrapper, router, routeTabs } = await createWrapper();

        wrapper.vm.onMenuClick('refresh');
        expect(routeTabs.tabs.find(({ key }) => key === '/users')?.refreshKey).toBe(1);

        wrapper.vm.onMenuClick('current');
        await flushPromises();

        expect(router.currentRoute.value.name).toBe('ct.dashboard.index');
        expect(routeTabs.tabs.map(({ key }) => key)).toEqual(['/dashboard']);
    });
});

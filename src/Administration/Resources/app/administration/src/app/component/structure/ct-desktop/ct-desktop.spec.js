import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory, routeLocationKey, routerKey } from 'vue-router';

const routes = [
    {
        name: 'ct.dashboard.index',
        path: '/sw/dashboard/index',
        component: {
            template: '<div></div>',
        },
        meta: {
            $module: {
                name: 'dashboard',
            },
        },
    },
    {
        name: 'ct.product.index',
        path: '/sw/product/index',
        component: {
            template: '<div></div>',
        },
        meta: {
            $module: {
                entity: 'product',
                icon: 'default-symbol-products',
                color: '#57D9A3',
                title: 'ct-product.general.mainMenuItemGeneral',
                name: 'product',
                routes: { index: { name: 'ct.product.index' } },
            },
        },
    },
    {
        name: 'ct.product.create.base',
        path: '/sw/product/create/base',
        component: {
            template: '<div></div>',
        },
        meta: {
            $module: {
                entity: 'product',
                icon: 'default-symbol-products',
                color: '#57D9A3',
                title: 'ct-product.general.mainMenuItemGeneral',
                name: 'product',
                routes: {
                    index: { name: 'ct.product.index' },
                    create: {
                        children: [
                            {
                                name: 'ct.product.create.base',
                            },
                        ],
                        name: 'ct.product.create',
                    },
                    detail: {
                        name: 'ct.product.detail',
                        children: [
                            {
                                name: 'ct.product.detail.base',
                            },
                        ],
                    },
                },
            },
        },
    },
    {
        name: 'ct.product.detail.base',
        path: '/sw/product/detail/:id/base',
        component: {
            template: '<div></div>',
        },
        meta: {
            $module: {
                entity: 'product',
                icon: 'default-symbol-products',
                color: '#57D9A3',
                title: 'ct-product.general.mainMenuItemGeneral',
                name: 'product',
                routes: {
                    index: { name: 'ct.product.index' },
                    create: {
                        children: [
                            {
                                name: 'ct.product.create.base',
                            },
                        ],
                        name: 'ct.product.create',
                    },
                    detail: {
                        name: 'ct.product.detail',
                        children: [
                            {
                                name: 'ct.product.detail.base',
                            },
                        ],
                    },
                },
            },
        },
    },
];

const router = createRouter({
    routes,
    history: createWebHashHistory(),
});

async function createWrapper() {
    // delete global $router and $routes mocks
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const defaultRouter = config.global.provide[routerKey];
    const defaultRoute = config.global.provide[routeLocationKey];
    delete config.global.provide[routerKey];
    delete config.global.provide[routeLocationKey];

    await router.push({ name: 'ct.dashboard.index' });

    const wrapper = mount(await wrapTestComponent('ct-desktop', { sync: true }), {
        global: {
            plugins: [
                router,
            ],
            stubs: {
                'ct-admin-menu': true,
                'router-view': true,
                'ct-error-boundary': true,
            },
            provide: {
                userActivityApiService: {
                    increment: jest.fn(() => Promise.resolve()),
                },
            },
        },
    });

    config.global.provide[routerKey] = defaultRouter;
    config.global.provide[routeLocationKey] = defaultRoute;

    return wrapper;
}

describe('src/app/component/structure/ct-desktop', () => {
    beforeEach(async () => {
        Contena.Store.get('session').setCurrentUser({
            id: 'id',
        });

        Contena.Store.get('context').app.config.settings = {
            enableStagingMode: false,
        };
    });

    it('should be update userConfig when at index route', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$router.push({ name: 'ct.product.index' });
        await flushPromises();

        expect(wrapper.vm.userActivityApiService.increment).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.getModuleMetadata()).toEqual({
            color: '#57D9A3',
            entity: 'product',
            icon: 'default-symbol-products',
            name: 'product',
            route: { name: 'ct.product.index' },
            title: 'ct-product.general.mainMenuItemGeneral',
        });
    });

    it('should be update userConfig when at create route', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$router.push({ name: 'ct.product.create.base' });
        await flushPromises();

        expect(wrapper.vm.userActivityApiService.increment).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.getModuleMetadata()).toEqual({
            name: 'product',
            icon: 'default-symbol-products',
            color: '#57D9A3',
            entity: 'product',
            privilege: undefined,
            route: {
                name: 'ct.product.create',
                children: [{ name: 'ct.product.create.base' }],
            },
            action: true,
        });
    });

    it('should be cannot update userConfig when at detail route', async () => {
        const wrapper = await createWrapper();

        await router.push({
            name: 'ct.product.detail.base',
            params: { id: 'a34943fe8fe040cd9ce25742a7cf77b2' },
        });
        await flushPromises();

        expect(wrapper.vm.userActivityApiService.increment).not.toHaveBeenCalled();
        expect(wrapper.vm.getModuleMetadata()).toBe(false);
    });

    it('should show the staging bar, when enabled', async () => {
        Contena.Store.get('context').app.config.settings.enableStagingMode = true;

        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.find('.ct-staging-bar').exists()).toBeTruthy();
    });

    it('should not show the staging bar, when disabled', async () => {
        Contena.Store.get('context').app.config.settings.enableStagingMode = false;

        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
        expect(wrapper.find('.ct-staging-bar').exists()).toBeFalsy();
    });
});

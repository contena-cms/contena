import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory, routeLocationKey, routerKey } from 'vue-router';

const route = {
    name: 'ct.extension.my-extensions.listing',
    path: '/sw/extension/my-extensions/listing',
    component: {},
};

async function createWrapper(query = {}) {
    const router = createRouter({
        routes: [route],
        history: createWebHashHistory(),
    });
    await router.push({ ...route, query: { term: '', limit: 5, ...query } });
    await router.isReady();
    jest.spyOn(router, 'push');

    const defaultRouter = config.global.provide[routerKey];
    const defaultRoute = config.global.provide[routeLocationKey];
    const defaultRouterMock = config.global.mocks.$router;
    const defaultRouteMock = config.global.mocks.$route;
    delete config.global.provide[routerKey];
    delete config.global.provide[routeLocationKey];
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const wrapper = mount(
        await wrapTestComponent('ct-extension-my-extensions-index', {
            sync: true,
        }),
        {
            global: {
                plugins: [router],
                stubs: {
                    'ct-meteor-page': await wrapTestComponent('ct-meteor-page', { sync: true }),
                    'ct-search-bar': true,
                    'ct-extension-file-upload': {
                        template: '<div class="ct-extension-file-upload"></div>',
                    },
                    'router-view': true,
                    'ct-notification-center': true,
                    'ct-help-center-v2': true,
                    'ct-meteor-navigation': true,
                    'ct-app-topbar-button': true,
                    'ct-app-topbar-sidebar': true,
                },
            },
            attachTo: document.body,
        },
    );

    config.global.provide[routerKey] = defaultRouter;
    config.global.provide[routeLocationKey] = defaultRoute;
    config.global.mocks.$router = defaultRouterMock;
    config.global.mocks.$route = defaultRouteMock;

    await flushPromises();

    return wrapper;
}

describe('module/ct-extension/page/ct-extension-my-extensions-index', () => {
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
                    authToken: {
                        token: 'testToken',
                    },
                },
            }),
        });
    });

    afterEach(() => {
        Contena.Store.get('context').app.config.settings.disableExtensionManagement = false;
        global.activeAclRoles = [];
    });

    it('upload button should be there when allowed runtime extension management', async () => {
        global.activeAclRoles = ['system.plugin_upload'];
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-extension-file-upload').exists()).toBe(true);
    });

    it('upload button should be not there when disabling runtime extension management', async () => {
        global.activeAclRoles = ['system.plugin_upload'];
        Contena.Store.get('context').app.config.settings.disableExtensionManagement = true;
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-extension-file-upload').exists()).toBe(false);
    });

    it('upload button should be not there when missing plugin_upload acl', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-extension-file-upload').exists()).toBe(false);
    });

    it('should preserve sorting when searching', async () => {
        const wrapper = await createWrapper({ sorting: 'name-asc' });

        wrapper.vm.onSearch('extension');

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'ct.extension.my-extensions.listing',
            params: {},
            query: {
                term: 'extension',
                limit: '5',
                page: 1,
                sorting: 'name-asc',
            },
        });
    });

    it('should preserve sorting in listing query parameters', async () => {
        const wrapper = await createWrapper({ sorting: 'name-asc' });

        expect(wrapper.vm.queryParams).toEqual({
            term: undefined,
            limit: '5',
            page: 1,
            sorting: 'name-asc',
        });
    });
});

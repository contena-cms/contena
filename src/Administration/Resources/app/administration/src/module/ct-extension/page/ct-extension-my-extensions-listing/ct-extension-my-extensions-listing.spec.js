import { config, mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory, routeLocationKey, routerKey } from 'vue-router';
import selectMtSelectOptionByText from '../../../../../test/_helper_/select-mt-select-by-text';

const route = {
    name: 'ct.extension.my-extensions.listing',
    path: '/sw/extension/my-extensions/listing',
    component: {},
};

const pluginService = {
    updateExtensionData: jest.fn(),
};

async function createWrapper(query = {}) {
    const router = createRouter({
        routes: [route],
        history: createWebHashHistory(),
    });
    await router.push({ ...route, query });
    await router.isReady();

    const defaultRouter = config.global.provide[routerKey];
    const defaultRoute = config.global.provide[routeLocationKey];
    const defaultRouterMock = config.global.mocks.$router;
    const defaultRouteMock = config.global.mocks.$route;
    delete config.global.provide[routerKey];
    delete config.global.provide[routeLocationKey];
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const wrapper = mount(await wrapTestComponent('ct-extension-my-extensions-listing', { sync: true }), {
        global: {
            plugins: [router],
            stubs: {
                'ct-extension-card-base': {
                    template: '<div class="ct-extension-card-base">{{ extension.label }}</div>',
                    props: ['extension'],
                },
                'mt-empty-state': true,
                'ct-pagination': await wrapTestComponent('ct-pagination', { sync: true }),
                'ct-extension-my-extensions-listing-controls': await wrapTestComponent(
                    'ct-extension-my-extensions-listing-controls',
                    { sync: true },
                ),
                'ct-skeleton': true,
                'mt-banner': true,
            },
            provide: {
                contenaExtensionService: pluginService,
            },
        },
        attachTo: document.body,
    });

    config.global.provide[routerKey] = defaultRouter;
    config.global.provide[routeLocationKey] = defaultRoute;
    config.global.mocks.$router = defaultRouterMock;
    config.global.mocks.$route = defaultRouteMock;

    return wrapper;
}

function plugin(index = 0, overrides = {}) {
    return {
        name: `Plugin${index}`,
        label: `Plugin ${index}`,
        active: true,
        installedAt: `installed-${index}`,
        updatedAt: null,
        ...overrides,
    };
}

describe('src/module/ct-extension/page/ct-extension-my-extensions-listing', () => {
    beforeEach(() => {
        pluginService.updateExtensionData.mockClear();
        Contena.Store.get('context').app.config.settings ??= {};
        Contena.Store.get('context').app.config.settings.disableExtensionManagement = false;
        Contena.Store.get('contenaExtensions').setMyExtensions([plugin()]);
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('loads native plugins', async () => {
        const wrapper = await createWrapper();

        expect(pluginService.updateExtensionData).toHaveBeenCalledTimes(1);
        expect(wrapper.findAll('.ct-extension-card-base')).toHaveLength(1);
    });

    it('shows the runtime-management warning when plugin changes are disabled', async () => {
        Contena.Store.get('context').app.config.settings.disableExtensionManagement = true;

        const wrapper = await createWrapper();

        expect(wrapper.find('.ct-extension-my-extensions-listing__runtime-extension-warning').exists()).toBe(true);
    });

    it('paginates plugins', async () => {
        Contena.Store.get('contenaExtensions').setMyExtensions(Array.from({ length: 40 }, (_, index) => plugin(index)));
        const wrapper = await createWrapper();

        expect(wrapper.findAll('.ct-extension-card-base')).toHaveLength(25);

        await wrapper.vm.$router.push({ name: route.name, query: { page: 2, limit: 25 } });
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.ct-extension-card-base')).toHaveLength(15);
    });

    it('searches plugins by label or technical name', async () => {
        Contena.Store.get('contenaExtensions').setMyExtensions([
            plugin(1, { label: 'Audit Trail' }),
            plugin(2, { name: 'ContenaMediaTools' }),
        ]);
        const wrapper = await createWrapper();

        await wrapper.vm.$router.push({ name: route.name, query: { term: 'media' } });
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.ct-extension-card-base')).toHaveLength(1);
        expect(wrapper.text()).toContain('Plugin 2');
    });

    it('filters inactive plugins', async () => {
        Contena.Store.get('contenaExtensions').setMyExtensions([
            plugin(1),
            plugin(2, { active: false }),
        ]);
        const wrapper = await createWrapper();

        wrapper.vm.changeActiveState(true);
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.ct-extension-card-base')).toHaveLength(1);
        expect(wrapper.text()).toContain('Plugin 1');
    });

    it('should update the route with the default sorting option', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.$route.query).toMatchObject({
            limit: '25',
            page: '1',
            sorting: 'updated-at',
        });
    });

    it('should persist the selected sorting option in the route', async () => {
        const wrapper = await createWrapper();

        await selectMtSelectOptionByText(
            wrapper,
            'ct-extension.my-extensions.listing.controls.filterOptions.name-asc',
            '.mt-select__selection',
        );

        expect(wrapper.vm.$route.query.sorting).toBe('name-asc');
    });

    it('should apply the sorting option from the route after loading', async () => {
        const wrapper = await createWrapper({ sorting: 'name-asc' });
        Contena.Store.get('contenaExtensions').setMyExtensions([
            plugin(1, { label: 'Zeta' }),
            plugin(2, { label: 'Alpha' }),
        ]);

        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.ct-extension-card-base').map((card) => card.text())).toEqual([
            'Alpha',
            'Zeta',
        ]);
    });

    it('should sort plugins by name in descending order', async () => {
        const wrapper = await createWrapper();
        const extensions = [
            plugin(1, { label: 'Alpha' }),
            plugin(2, { label: 'Zeta' }),
            plugin(3, { label: 'Beta' }),
        ];

        expect(wrapper.vm.sortExtensions(extensions, 'name-desc').map(({ label }) => label)).toEqual([
            'Zeta',
            'Beta',
            'Alpha',
        ]);
    });
});

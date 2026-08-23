import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHistory, routeLocationKey, routerKey } from 'vue-router';

const routes = [
    {
        name: 'index',
        path: '/',
        component: {},
    },
    {
        name: 'ct.settings.country.detail',
        path: '/sw/settings/country/detail/the-id',
        children: [
            {
                name: 'ct.settings.country.detail.general',
                path: '/sw/settings/country/detail/the-id/general',
            },
        ],
    },
];

async function createWrapper(privileges = []) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const defaultRouter = config.global.provide[routerKey];
    const defaultRoute = config.global.provide[routeLocationKey];
    delete config.global.provide[routerKey];
    delete config.global.provide[routeLocationKey];

    const router = createRouter({
        history: createWebHistory(),
        routes: routes,
    });

    const wrapper = mount(
        await wrapTestComponent('ct-settings-country-detail', {
            sync: true,
        }),
        {
            global: {
                plugins: [
                    router,
                ],

                directives: {
                    tooltip: {},
                },

                mocks: {
                    $t: (key) => key,
                    $device: {
                        removeResizeListener: () => {},
                        getSystemKey: () => {},
                        onResize: () => {},
                    },
                },

                provide: {
                    repositoryFactory: {
                        create: (entity) => ({
                            get: () => {
                                if (entity === 'country') {
                                    return Promise.resolve({
                                        isNew: () => false,
                                        active: true,
                                        apiAlias: null,
                                        createdAt: '2020-08-12T02:49:39.974+00:00',
                                        customFields: null,
                                        customerAddresses: [],
                                        displayStateInRegistration: false,
                                        forceStateInRegistration: false,
                                        id: '44de136acf314e7184401d36406c1e90',
                                        iso: 'AL',
                                        iso3: 'ALB',
                                        name: 'Albania',
                                        orderAddresses: [],
                                        position: 10,
                                        channelDefaultAssignments: [],
                                        channels: [],
                                        shippingAvailable: true,
                                        regions: [],
                                        taxFree: false,
                                        taxRules: [],
                                        translated: {},
                                        translations: [],
                                        updatedAt: '2020-08-16T06:57:40.559+00:00',
                                        vatIdRequired: false,
                                    });
                                }

                                return Promise.resolve({
                                    systemCurrency: {
                                        symbol: '€',
                                    },
                                });
                            },
                            search: () => {
                                return Promise.resolve({
                                    first: () => ({}),
                                    length: 0,
                                });
                            },
                            create: () => {
                                return {};
                            },
                        }),
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },

                stubs: {
                    'ct-page': {
                        template: `
                    <div class="ct-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `,
                    },
                    'ct-card-view': await wrapTestComponent('ct-card-view'),
                    'ct-container': await wrapTestComponent('ct-container'),
                    'ct-language-switch': true,
                    'ct-language-info': true,
                    'ct-button-process': true,
                    'ct-field': true,
                    'ct-simple-search-field': true,
                    'ct-context-menu-item': true,
                    'mt-number-field': true,
                    'ct-one-to-many-grid': true,
                    'router-link': true,
                    'router-view': true,
                    'ct-skeleton': true,
                    'ct-settings-country-sidebar': true,
                    'ct-error-summary': true,
                    'ct-custom-field-set-renderer': true,
                    'mt-tabs': {
                        props: ['items'],
                        template: '<div class="mt-tabs">{{ items.map((item) => item.label).join(\', \') }}</div>',
                    },
                    'ct-extension-component-section': true,
                },
            },
        },
    );

    config.global.provide[routerKey] = defaultRouter;
    config.global.provide[routeLocationKey] = defaultRoute;

    return wrapper;
}

describe('module/ct-settings-country/page/ct-settings-country-detail', () => {
    beforeAll(() => {
        Contena.Store.get('session').setCurrentUser({});
    });

    it('should render the country tabs', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);

        await wrapper.vm.$nextTick();
        expect(wrapper.vm.placeholder).toBeInstanceOf(Function);
        expect(wrapper.find('.mt-tabs').text()).toContain('ct-settings-country.page.generalTab');
        expect(wrapper.find('.mt-tabs').text()).not.toContain('ct-settings-region');
    });

    it('should be able to save the country', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.ct-settings-country-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to save the country', async () => {
        const wrapper = await createWrapper([]);
        await wrapper.vm.$nextTick();

        const saveButton = wrapper.find('.ct-settings-country-detail__save-action');

        expect(saveButton.attributes().disabled).toBeTruthy();
    });
});

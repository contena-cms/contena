import { mount } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('ct-settings-country-list', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,

                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                },

                provide: {
                    [routeLocationKey]: {
                        name: 'country-list',
                        query: {
                            page: 1,
                            limit: 25,
                        },
                        params: {},
                    },
                    [routerKey]: {
                        push: jest.fn(),
                        replace: jest.fn(),
                    },
                    repositoryFactory: {
                        create: () => ({
                            search: () => {
                                return Promise.resolve([
                                    {
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
                                    },
                                ]);
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
                    feature: {
                        isActive: () => true,
                    },
                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
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
                    'ct-card-view': {
                        template: `
                    <div class="ct-card-view">
                        <slot></slot>
                    </div>
                `,
                    },
                    'mt-data-table': {
                        name: 'MtDataTable',
                        props: [
                            'dataSource',
                            'disableDelete',
                            'additionalContextButtons',
                        ],
                        template: '<div class="country-table"><slot name="empty-state" /></div>',
                    },
                    'ct-language-switch': true,
                    'ct-search-bar': true,
                    'mt-modal-root': true,
                    'mt-modal': true,
                },
            },
        },
    );
}

describe('module/ct-settings-country/page/ct-settings-country-list', () => {
    it('should be able to view a country', async () => {
        const wrapper = await createWrapper([
            'country.viewer',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'MtDataTable' }).props('additionalContextButtons')).toEqual([
            { key: 'edit', label: 'global.default.view' },
        ]);
    });

    it('should be able to create a new country', async () => {
        const wrapper = await createWrapper([
            'country.creator',
        ]);
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.ct-settings-country-list__button-create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a new country', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const createButton = wrapper.find('.ct-settings-country-list__button-create');

        expect(createButton.attributes('disabled')).toBeDefined();
    });

    it('should be able to edit a country', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'MtDataTable' }).props('additionalContextButtons')).toEqual([
            { key: 'edit', label: 'global.default.edit' },
        ]);
    });

    it('should not be able to edit a country', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'MtDataTable' }).props('additionalContextButtons')).toEqual([]);
    });

    it('should be able to inline edit a country', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'MtDataTable' }).exists()).toBeTruthy();
    });

    it('should not be able to inline edit a country', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'MtDataTable' }).exists()).toBeTruthy();
    });

    it('should be able to delete a country', async () => {
        const wrapper = await createWrapper([
            'country.deleter',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'MtDataTable' }).props('disableDelete')).toBe(false);
    });

    it('should not be able to delete a country', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'MtDataTable' }).props('disableDelete')).toBe(true);
    });

    it('should be able to delete mutilple country', async () => {
        const wrapper = await createWrapper([
            'country.deleter',
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.findComponent({ name: 'MtDataTable' }).exists()).toBeTruthy();
    });
});

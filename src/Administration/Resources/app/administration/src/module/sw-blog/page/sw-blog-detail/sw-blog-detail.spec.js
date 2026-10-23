/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package inventory
 */

import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

const advancedModeSettings = {
    value: {
        advancedMode: {
            label: 'sw-blog.general.textAdvancedMode',
            enabled: true,
        },
        settings: [
            {
                key: 'general_information',
                label: 'sw-blog.detailBase.cardTitleProductInfo',
                enabled: true,
                name: 'general',
            },
            {
                key: 'prices',
                label: 'sw-blog.detailBase.cardTitlePrices',
                enabled: true,
                name: 'general',
            },
            {
                key: 'deliverability',
                label: 'sw-blog.detailBase.cardTitleDeliverabilityInfo',
                enabled: true,
                name: 'general',
            },
            {
                key: 'visibility_structure',
                label: 'sw-blog.detailBase.cardTitleVisibilityStructure',
                enabled: true,
                name: 'general',
            },
            {
                key: 'labelling',
                label: 'sw-blog.detailBase.cardTitleSettings',
                enabled: true,
                name: 'general',
            },
        ],
    },
};

const defaultSalesChannelData = {
    'core.defaultSalesChannel.active': false,
    'core.defaultSalesChannel.channel': ['98432def39fc4624b33213a56b8c944d'],
    'core.defaultSalesChannel.visibility': {
        '98432def39fc4624b33213a56b8c944d': 10,
    },
};

describe('module/sw-blog/page/sw-blog-detail', () => {
    async function createWrapper(
        searchFunction = () => Promise.resolve([]),
        getFunction = () => {
            return Promise.resolve({ variation: [] });
        },
        blogId = '1234',
        { featureActive = false, routeName = 'sw.blog.detail.base', routerPush = jest.fn() } = {},
    ) {
        return mount(await wrapTestComponent('sw-blog-detail', { sync: true }), {
            global: {
                mocks: {
                    $route: {
                        name: routeName,
                        params: {
                            id: blogId,
                        },
                    },
                    $router: {
                        push: routerPush,
                    },
                },
                provide: {
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                    },
                    numberRangeService: {
                        reserve: () => Promise.resolve({ number: 1 }),
                    },
                    seoUrlService: {},
                    mediaService: {},
                    repositoryFactory: {
                        create: (entity) => ({
                            create: () => {
                                if (entity === 'blog') {
                                    return {
                                        id: '1',
                                        parentId: '1',
                                        properties: [],
                                        visibilities: [],
                                        isNew: () => true,
                                    };
                                }

                                return {};
                            },
                            search: searchFunction,
                            searchIds: () => Promise.resolve({ data: [] }),
                            get: async (...args) => {
                                const blog = await getFunction(...args);

                                if (blog && !blog._origin) {
                                    blog._origin = {};
                                }

                                if (blog && !blog.isNew) {
                                    blog.isNew = () => false;
                                }

                                return blog;
                            },
                            hasChanges: () => true,
                            save: () => Promise.resolve({}),
                        }),
                    },
                    systemConfigApiService: {
                        getConfig: () =>
                            Promise.resolve({
                                'core.tax.defaultTaxRate': '',
                            }),
                        getValues: () => Promise.resolve(defaultSalesChannelData),
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                    entityValidationService: {
                        validate: (entity, customValidator) => {
                            let errors = [];
                            if (customValidator) {
                                errors = customValidator(errors, entity);
                            }

                            return errors.length < 1;
                        },
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `<div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content">
                                <div class="sw-tabs"></div>
                            </slot>
                            <slot></slot>
                        </div>`,
                    },
                    'sw-blog-variant-info': true,
                    'sw-button-group': true,
                    'sw-button-process': true,
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-language-switch': true,
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'sw-language-info': true,
                    'router-view': true,

                    'sw-context-menu-divider': true,
                    'sw-checkbox-field': true,
                    'sw-blog-settings-mode': await wrapTestComponent('sw-blog-settings-mode', { sync: true }),
                    'sw-loader': true,
                    'sw-tabs': {
                        name: 'sw-tabs',
                        props: {
                            positionIdentifier: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                        },
                        template: '<div class="sw-tabs"><slot /></div>',
                    },
                    'sw-tabs-item': {
                        name: 'sw-tabs-item',
                        template: '<div class="sw-tabs-item"><slot /></div>',
                        props: ['route', 'title', 'hasError'],
                    },
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                        template: '<div class="mt-tabs"></div>',
                    },
                    'sw-inheritance-warning': true,
                    'router-link': true,
                    'sw-blog-detail': await wrapTestComponent('sw-blog-detail'),
                    'sw-extension-component-section': true,
                    'sw-blog-clone-modal': true,
                },
            },
            props: {
                blogId,
            },
        });
    }

    let wrapper;

    beforeAll(() => {
        Contena.Store.unregister('cmsPage');
        Contena.Store.register({
            id: 'cmsPage',
            actions: {
                resetCmsPageState: () => {},
            },
        });
    });

    beforeEach(async () => {
        jest.restoreAllMocks();
        jest.spyOn(Contena.Service('admin_userConfigService'), 'search').mockResolvedValue({ data: {} });
        jest.spyOn(Contena.Service('admin_userConfigService'), 'upsert').mockResolvedValue();

        wrapper = await createWrapper();

        Contena.Store.get('swProductDetail').setLengthUnit = jest.fn();
        Contena.Store.get('swProductDetail').setWeightUnit = jest.fn();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should show item tabs', async () => {
        await wrapper.setProps({
            blogId: '1234',
        });
        const tabItemClassName = [
            '.sw-blog-detail__tab-advanced-prices',
            '.sw-blog-detail__tab-variants',
            '.sw-blog-detail__tab-layout',
            '.sw-blog-detail__tab-seo',
            '.sw-blog-detail__tab-cross-selling',
            '.sw-blog-detail__tab-reviews',
        ];

        await nextTick();

        tabItemClassName.forEach((item) => {
            expect(wrapper.find(item).exists()).toBe(true);
        });
    });

    it('should allow saving and duplicating a blog that has not been saved yet', async () => {
        await wrapper.setProps({
            blogId: null,
        });

        wrapper.vm.onDuplicate();

        expect(wrapper.vm.cloning).toBe(true);
    });

    it('requires a download for digital blogs when external download management is off', async () => {
        Contena.Store.get('swProductDetail').blog = { type: 'digital' };
        Contena.Store.get('swProductDetail').parentProduct = {};
        Contena.Store.get('swProductDetail').isDownloadExternallyManaged = false;

        const errors = wrapper.vm.customValidate([], { downloads: [] });

        expect(errors).toHaveLength(1);
        expect(errors[0]).toMatchObject({
            code: 'SW_BLOG_DOWNLOADS_EXTERNAL_MANAGEMENT_REQUIRED',
            source: { pointer: '/0/downloads' },
            detail: 'sw-blog.detailBase.downloadsExternalManagementRequired',
        });
    });

    it('allows a digital blog without downloads when external download management is on', async () => {
        Contena.Store.get('swProductDetail').blog = { type: 'digital' };
        Contena.Store.get('swProductDetail').parentProduct = {};
        Contena.Store.get('swProductDetail').isDownloadExternallyManaged = true;

        const errors = wrapper.vm.customValidate([], { downloads: [] });

        expect(errors).toHaveLength(0);
    });

    it('resets external download management when creating a blog', async () => {
        const store = Contena.Store.get('swProductDetail');
        store.isDownloadExternallyManaged = true;

        wrapper.unmount();
        wrapper = await createWrapper(undefined, undefined, null, { routeName: 'sw.blog.create.base' });
        await flushPromises();

        expect(store.isDownloadExternallyManaged).toBe(false);
    });

    it.each([
        { downloads: [], expected: true },
        { downloads: [{ id: 'download-id' }], expected: false },
    ])('sets external assignment to $expected when loading an existing blog', async ({ downloads, expected }) => {
        const store = Contena.Store.get('swProductDetail');
        store.isDownloadExternallyManaged = !expected;

        wrapper.unmount();
        wrapper = await createWrapper(undefined, () =>
            Promise.resolve({
                id: 'blog-id',
                type: 'digital',
                downloads,
                purchasePrices: [{ currencyId: 'currency-id', gross: 1, net: 1 }],
                variation: [],
            }),
        );
        await flushPromises();

        expect(store.isDownloadExternallyManaged).toBe(expected);
    });

    it('should flag the blog as loading before awaiting the measurement units', async () => {
        await wrapper.unmount();
        wrapper = null;

        let resolveSearch;
        Contena.Service('admin_userConfigService').search.mockReturnValue(
            new Promise((resolve) => {
                resolveSearch = resolve;
            }),
        );

        Contena.Store.get('swProductDetail').$reset();
        expect(Contena.Store.get('swProductDetail').loading.blog).toBe(false);

        wrapper = await createWrapper();

        expect(Contena.Store.get('swProductDetail').loading.blog).toBe(true);

        resolveSearch({ data: {} });
        await flushPromises();
    });

    it('should not keep the blog loading when the measurement units cannot be loaded', async () => {
        await wrapper.unmount();
        wrapper = null;

        Contena.Service('admin_userConfigService').search.mockImplementation((keys) => {
            if (keys.includes('measurement.preferenceUnits')) {
                return Promise.reject(new Error('Request failed'));
            }

            return Promise.resolve({ data: {} });
        });

        Contena.Store.get('swProductDetail').$reset();

        wrapper = await createWrapper();
        await flushPromises();

        expect(Contena.Store.get('swProductDetail').loading.blog).toBe(false);
    });

    it('should redirect to blog listing when blog no longer exists', async () => {
        await wrapper.unmount();

        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () => Promise.resolve(null),
        );

        await wrapper.setProps({
            blogId: 'missing-blog-id',
        });
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.$router.push.mockClear();

        Contena.Store.get('swProductDetail').blog = {
            id: 'stale-blog-id',
            parentId: 'stale-parent-id',
        };
        Contena.Store.get('swProductDetail').parentProduct = {
            id: 'stale-parent-id',
        };

        await wrapper.vm.loadProduct();
        await nextTick();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-blog.detail.messageProductNotFound',
        });
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.blog.index',
        });
        expect(Contena.Store.get('swProductDetail').blog).toEqual({});
        expect(Contena.Store.get('swProductDetail').parentProduct).toEqual({});
        expect(wrapper.find('.sw-card-view').exists()).toBe(false);
    });

    it('should render the fallback tabs branch while the major feature flag is inactive', () => {
        const tabs = wrapper.getComponent({ name: 'sw-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-blog-detail');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor route tabs when the major feature flag is active', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(undefined, undefined, '1234', { featureActive: true });
        await flushPromises();

        Contena.Store.get('swProductDetail').blog = { parentId: null };
        Contena.Store.get('swProductDetail').advancedModeSetting = advancedModeSettings;

        await nextTick();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const items = tabs.props('items');

        expect(tabs.props('positionIdentifier')).toBe('sw-blog-detail');
        expect(tabs.props('defaultItem')).toBe('sw.blog.detail.base');
        expect(items.map(({ label, name }) => ({ label, name }))).toEqual([
            {
                label: 'sw-blog.detail.tabGeneral',
                name: 'sw.blog.detail.base',
            },
            {
                label: 'sw-blog.detail.tabSpecifications',
                name: 'sw.blog.detail.specifications',
            },
            {
                label: 'sw-blog.detail.tabAdvancedPrices',
                name: 'sw.blog.detail.prices',
            },
            {
                label: 'sw-blog.detail.tabVariation',
                name: 'sw.blog.detail.variants',
            },
            {
                label: 'sw-blog.detail.tabLayout',
                name: 'sw.blog.detail.layout',
            },
            {
                label: 'sw-blog.detail.tabSeo',
                name: 'sw.blog.detail.seo',
            },
            {
                label: 'sw-blog.detail.tabCrossSelling',
                name: 'sw.blog.detail.crossSelling',
            },
            {
                label: 'sw-blog.detail.tabReviews',
                name: 'sw.blog.detail.reviews',
            },
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(wrapper.find('.sw-blog-detail__tab-general').exists()).toBe(false);
    });

    it('should show the layout meteor tab for variant blogs', async () => {
        wrapper.unmount();
        wrapper = await createWrapper(undefined, undefined, '1234', { featureActive: true });
        await flushPromises();

        Contena.Store.get('swProductDetail').blog = { parentId: 'parent-id' };
        Contena.Store.get('swProductDetail').advancedModeSetting = advancedModeSettings;

        await nextTick();

        const tabNames = wrapper
            .getComponent({ name: 'mt-tabs' })
            .props('items')
            .map((item) => item.name);

        expect(tabNames).toContain('sw.blog.detail.prices');
        expect(tabNames).toContain('sw.blog.detail.layout');
        expect(tabNames).toContain('sw.blog.detail.seo');
        expect(tabNames).not.toContain('sw.blog.detail.variants');
    });

    it('should navigate when a meteor route tab is selected', async () => {
        const routerPush = jest.fn();

        wrapper.unmount();
        wrapper = await createWrapper(undefined, undefined, '1234', {
            featureActive: true,
            routerPush,
        });
        await flushPromises();

        Contena.Store.get('swProductDetail').blog = { parentId: null };
        Contena.Store.get('swProductDetail').advancedModeSetting = advancedModeSettings;

        await nextTick();

        const pricesTab = wrapper
            .getComponent({ name: 'mt-tabs' })
            .props('items')
            .find((item) => item.name === 'sw.blog.detail.prices');

        pricesTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.blog.detail.prices',
            params: { id: '1234' },
        });
    });

    it('should show item tabs when advanced mode deactivate', async () => {
        Contena.Store.get('swProductDetail').blog = { parentId: '' };
        await wrapper.setProps({
            blogId: '1234',
        });

        Contena.Store.get('swProductDetail').advancedModeSetting = {
            value: {
                ...advancedModeSettings.value,
                advancedMode: {
                    enabled: false,
                },
            },
        };

        const tabItemClassName = [
            '.sw-blog-detail__tab-variants',
            '.sw-blog-detail__tab-layout',
            '.sw-blog-detail__tab-seo',
            '.sw-blog-detail__tab-cross-selling',
            '.sw-blog-detail__tab-reviews',
        ];

        await nextTick();

        tabItemClassName.forEach((item) => {
            expect(wrapper.find(item).attributes().style).toBe('display: none;');
        });
    });

    it('should show Advance mode setting on the variant blog page', async () => {
        await flushPromises();
        Contena.Store.get('swProductDetail').blog = {
            ...wrapper.vm.blog,
            parentId: 'parent-id',
        };

        await wrapper.setProps({
            blogId: '1234',
        });

        const contextButton = wrapper.find('.sw-blog-settings-mode');
        expect(contextButton.exists()).toBeFalsy();

        const visibleTabItem = [
            '.sw-blog-detail__tab-layout',
            '.sw-blog-detail__tab-seo',
            '.sw-blog-detail__tab-cross-selling',
            '.sw-blog-detail__tab-reviews',
        ];

        const invisibleTabItem = ['.sw-blog-detail__tab-variants'];

        visibleTabItem.forEach((item) => {
            expect(wrapper.find(item).attributes().style).toBeFalsy();
        });

        invisibleTabItem.forEach((item) => {
            expect(wrapper.find(item).attributes().style).toBe('display: none;');
        });
    });

    it('should always show the correct menu, even with the defaults not matching the admin_userConfig', async () => {
        const keys = ['general_information', 'prices', 'deliverability'];
        const mockKey = 'mock_key_without_result';
        const settings = [...keys].map((key) => {
            return {
                enabled: false,
                key,
                label: key,
                name: 'general',
            };
        });
        await wrapper.vm.$nextTick();

        settings.forEach((entry) => {
            expect(entry.enabled).toBe(!keys.includes(entry.key));
        });

        keys.forEach((key) => {
            expect(settings.some((entry) => entry.key === key)).toBe(true);
        });

        expect(settings.some((entry) => entry.key === mockKey)).toBeFalsy();
    });

    it('should set purchasePrices to default value when given purchasePrices are empty', async () => {
        await wrapper.vm.$nextTick();
        wrapper.unmount();
        wrapper = await createWrapper(() =>
            Promise.resolve([
                {
                    id: '123',
                    name: 'EUR',
                },
            ]),
        );

        await flushPromises();
        await nextTick();

        expect(wrapper.vm.blog.purchasePrices).toStrictEqual([
            {
                currencyId: undefined,
                gross: 0,
                net: 0,
                linked: true,
            },
        ]);
    });

    it('should validate and clear listPrices/regulationPrices on save', async () => {
        wrapper.vm.getCmsPageOverrides = jest.fn(() => {
            return null;
        });
        wrapper.vm.blog.isNew = jest.fn(() => {
            return false;
        });
        wrapper.vm.blog.prices = [];
        wrapper.vm.blog.price = [
            {
                currencyId: undefined,
                linked: true,
                gross: 100,
                net: 84.034,
                listPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
                regulationPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
            },
        ];

        wrapper.vm.onSave();

        expect(wrapper.vm.blog.price).toStrictEqual([
            {
                currencyId: undefined,
                gross: 100,
                net: 84.034,
                linked: true,
                listPrice: null,
                regulationPrice: null,
            },
        ]);
        await flushPromises();
    });

    it('should show correct config when there is system config data', async () => {
        await flushPromises();
        await wrapper.unmount();

        wrapper = await createWrapper(
            () => {
                return Promise.resolve([
                    {
                        id: '98432def39fc4624b33213a56b8c944d',
                        name: 'Headless',
                    },
                ]);
            },
            undefined,
            null,
        );

        await flushPromises();
        await flushPromises();
        expect(wrapper.vm.blog.visibilities).toHaveLength(1);
    });

    it('should run custom validation service and handle errors', async () => {
        wrapper.vm.getCmsPageOverrides = jest.fn(() => {
            return null;
        });
        Contena.Store.get('swProductDetail').blog = {
            isNew: jest.fn(() => true),
            prices: [],
            price: [
                {
                    currencyId: undefined,
                    linked: true,
                    gross: 100,
                    net: 84.034,
                    listPrice: {
                        currencyId: undefined,
                        linked: true,
                        gross: 0,
                        net: 0,
                    },
                    regulationPrice: {
                        currencyId: undefined,
                        linked: true,
                        gross: 0,
                        net: 0,
                    },
                },
            ],
        };

        // make it a download blog which requires downloads
        if (!Contena.Feature.isActive('v6.8.0.0')) {
            Contena.Store.get('swProductDetail').creationStates = 'is-download';
        }

        Contena.Store.get('swProductDetail').creationType = 'digital';
        Contena.Store.get('swProductDetail').isDownloadExternallyManaged = false;

        wrapper.vm.saveProduct = jest.fn(() => {
            return Promise.resolve();
        });
        wrapper.vm.onSave();

        // save shouldn't finish successfully (nothing should be sent to the server - no saveProduct call)
        expect(wrapper.vm.saveProduct.mock.calls).toHaveLength(0);
        await flushPromises();
    });

    it('saves a new digital blog without downloads when external download management is on', async () => {
        const store = Contena.Store.get('swProductDetail');
        store.blog = {
            isNew: () => true,
            downloads: [],
            prices: [],
            price: [],
        };
        store.creationType = 'digital';
        store.isDownloadExternallyManaged = true;
        wrapper.vm.saveProduct = jest.fn().mockResolvedValue('success');

        await wrapper.vm.onSave();

        expect(wrapper.vm.saveProduct).toHaveBeenCalledTimes(1);
    });

    it('should initialize with default units when no preferences exist', async () => {
        await wrapper.vm.initProductMeasurementUnits();

        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
        expect(Contena.Store.get('swProductDetail').setLengthUnit).toHaveBeenCalledWith('mm');
        expect(Contena.Store.get('swProductDetail').setWeightUnit).toHaveBeenCalledWith('kg');
    });

    it('should initialize with preferred units when they exist', async () => {
        const preferredUnits = {
            length: 'cm',
            weight: 'g',
        };

        Contena.Service('admin_userConfigService').search.mockResolvedValue({
            data: {
                'measurement.preferenceUnits': preferredUnits,
            },
        });

        await wrapper.vm.initProductMeasurementUnits();

        expect(wrapper.vm.previousLengthUnit).toBe('cm');
        expect(wrapper.vm.previousWeightUnit).toBe('g');
        expect(Contena.Store.get('swProductDetail').setLengthUnit).toHaveBeenCalledWith('cm');
        expect(Contena.Store.get('swProductDetail').setWeightUnit).toHaveBeenCalledWith('g');
    });

    it('should initialize with default units when the preferences cannot be loaded', async () => {
        Contena.Service('admin_userConfigService').search.mockRejectedValue(new Error('Request failed'));

        await wrapper.vm.initProductMeasurementUnits();

        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
        expect(Contena.Store.get('swProductDetail').setLengthUnit).toHaveBeenCalledWith('mm');
        expect(Contena.Store.get('swProductDetail').setWeightUnit).toHaveBeenCalledWith('kg');
    });

    it('should save preferences only when units have changed', async () => {
        await wrapper.setData({
            previousLengthUnit: 'cm',
            previousWeightUnit: 'kg',
        });

        await wrapper.vm.saveProduct();

        expect(Contena.Service('admin_userConfigService').upsert).toHaveBeenCalled();
        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
    });

    it('should not save preferences when units have not changed', async () => {
        await wrapper.setData({
            previousLengthUnit: 'mm',
            previousWeightUnit: 'kg',
        });

        await wrapper.vm.saveProduct();

        expect(Contena.Service('admin_userConfigService').upsert).not.toHaveBeenCalled();
        expect(wrapper.vm.previousLengthUnit).toBe('mm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
    });

    it('should handle errors when saving preferences', async () => {
        await wrapper.setData({
            previousLengthUnit: 'cm',
            previousWeightUnit: 'kg',
        });

        Contena.Service('admin_userConfigService').upsert.mockRejectedValue(new Error('Save failed'));

        await wrapper.vm.saveProduct();

        expect(Contena.Service('admin_userConfigService').upsert).toHaveBeenCalled();
        // Previous units should not be updated on error
        expect(wrapper.vm.previousLengthUnit).toBe('cm');
        expect(wrapper.vm.previousWeightUnit).toBe('kg');
    });

    it('should set isSaveSuccessful to true when no SEO promises exist', () => {
        wrapper.vm.loadProduct = jest.fn();

        wrapper.vm.updateSeoPromises = [];

        wrapper.vm.onSaveFinished('success');

        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(wrapper.vm.loadProduct).not.toHaveBeenCalled();
    });

    it.each(['success', 'empty'])('should announce a save that finished with "%s"', async (response) => {
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.updateSeoPromises = [];

        Contena.Utils.EventBus.emit = jest.fn();

        wrapper.vm.onSaveFinished(response);
        await flushPromises();

        expect(Contena.Utils.EventBus.emit).toHaveBeenCalledWith('sw-blog-detail-save-success');
    });

    it('should not announce a save that failed', async () => {
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.updateSeoPromises = [];

        Contena.Utils.EventBus.emit = jest.fn();

        wrapper.vm.onSaveFinished({ response: { data: { errors: [{ detail: 'nope' }] } } });
        await flushPromises();

        expect(Contena.Utils.EventBus.emit).not.toHaveBeenCalledWith('sw-blog-detail-save-success');
    });

    it('should handle success response correctly', async () => {
        wrapper.vm.updateSeoPromises = [Promise.resolve()];
        Contena.Store.get('swProductDetail').setLoading = jest.fn();
        jest.spyOn(Contena.Store.get('error'), 'resetApiErrors');
        wrapper.vm.loadProduct = jest.fn();

        Contena.Utils.EventBus.emit = jest.fn();

        wrapper.vm.onSaveFinished('success');

        expect(Contena.Store.get('swProductDetail').setLoading).toHaveBeenCalledWith(['blog', true]);

        await flushPromises();

        expect(Contena.Utils.EventBus.emit).toHaveBeenCalledWith('sw-blog-detail-save-finish');
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
        expect(Contena.Store.get('error').resetApiErrors).toHaveBeenCalled();
        expect(Contena.Store.get('swProductDetail').setLoading).toHaveBeenCalledWith(['blog', false]);
        expect(wrapper.vm.loadProduct).toHaveBeenCalled();
    });

    it.each(['success', 'empty'])(
        'should discard the api errors of the previous save when the save finished with "%s"',
        async (response) => {
            wrapper.vm.loadProduct = jest.fn();
            wrapper.vm.updateSeoPromises = [];

            Contena.Store.get('error').addApiError({
                expression: 'blog.1234.guaranteeMonths',
                error: {
                    code: 'INVALID_GARAN_GUARANTEE_MONTHS',
                    detail: 'The GARAN guarantee duration must be empty or a half-year value between 30 and 600 months.',
                },
            });

            wrapper.vm.onSaveFinished(response);
            await flushPromises();

            expect(wrapper.vm.isSaveSuccessful).toBe(true);
            expect(Contena.Store.get('error').api).toEqual({});
        },
    );

    it('should keep the api errors when the save failed', async () => {
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.updateSeoPromises = [];

        Contena.Store.get('error').addApiError({
            expression: 'blog.1234.guaranteeMonths',
            error: {
                code: 'INVALID_GARAN_GUARANTEE_MONTHS',
                detail: 'The GARAN guarantee duration must be empty or a half-year value between 30 and 600 months.',
            },
        });

        wrapper.vm.onSaveFinished({ response: { data: { errors: [{ detail: 'nope' }] } } });
        await flushPromises();

        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(Contena.Store.get('error').api.blog['1234'].guaranteeMonths).toBeDefined();
    });

    it('should handle duplicate blog number error correctly', async () => {
        wrapper.vm.updateSeoPromises = [Promise.resolve()];
        wrapper.vm.isSaveSuccessful = false;
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();

        Contena.Utils.EventBus.emit = jest.fn();

        const duplicateErrorResponse = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'CONTENT__DUPLICATE_BLOG_NUMBER',
                            meta: {
                                parameters: {
                                    number: 'SW-123',
                                },
                            },
                        },
                    ],
                },
            },
        };

        wrapper.vm.onSaveFinished(duplicateErrorResponse);

        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'sw-blog.notification.notificationSaveErrorProductNoAlreadyExists',
        });
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.loadProduct).not.toHaveBeenCalled();
    });

    it('should handle duplicate blog number error when seo promises are empty', async () => {
        wrapper.vm.updateSeoPromises = [];
        wrapper.vm.isSaveSuccessful = false;
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.createNotificationError = jest.fn();

        const duplicateErrorResponse = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'CONTENT__DUPLICATE_BLOG_NUMBER',
                            meta: {
                                parameters: {
                                    number: 'SW-123',
                                },
                            },
                        },
                    ],
                },
            },
        };

        wrapper.vm.onSaveFinished(duplicateErrorResponse);

        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'sw-blog.notification.notificationSaveErrorProductNoAlreadyExists',
        });
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.loadProduct).not.toHaveBeenCalled();
    });

    it('should handle generic error with detail correctly', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.loadProduct = jest.fn();
        wrapper.vm.updateSeoPromises = [Promise.resolve()];
        const errorResponse = {
            response: {
                data: {
                    errors: [
                        {
                            detail: 'Custom error message',
                        },
                    ],
                },
            },
        };

        wrapper.vm.onSaveFinished(errorResponse);

        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'Custom error message',
        });
        expect(wrapper.vm.isSaveSuccessful).toBe(false);
        expect(wrapper.vm.loadProduct).not.toHaveBeenCalled();
    });

    it('should handle SEO promise rejection correctly', async () => {
        const rejectedPromise = Promise.reject(new Error('SEO error'));
        wrapper.vm.updateSeoPromises = [rejectedPromise];
        Contena.Store.get('swProductDetail').setLoading = jest.fn();
        wrapper.vm.loadProduct = jest.fn();

        wrapper.vm.onSaveFinished('success');

        await flushPromises();

        expect(Contena.Store.get('swProductDetail').setLoading).toHaveBeenCalledWith(['blog', false]);
        expect(wrapper.vm.loadProduct).toHaveBeenCalled();
    });

    it('should not validate fields when language is inherited', async () => {
        const spyValidationService = jest.spyOn(wrapper.vm.entityValidationService, 'validate');

        wrapper.vm.getCmsPageOverrides = jest.fn(() => {
            return null;
        });
        wrapper.vm.blog.isNew = jest.fn(() => {
            return false;
        });
        wrapper.vm.blog.prices = [];
        wrapper.vm.blog.price = [
            {
                currencyId: undefined,
                linked: true,
                gross: 100,
                net: 84.034,
                listPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
                regulationPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
            },
        ];

        wrapper.vm.saveProduct = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.blog.getEntityName = () => 'blog';
        Contena.EntityDefinition.get = () => ({
            properties: {
                name: {
                    type: 'string',
                    flags: {
                        required: true,
                    },
                },
            },
        });

        Contena.Store.get('context').api.language = {
            id: '1a2b3c',
            parentId: 'd4e5f6',
        };

        wrapper.vm.blog.name = null;

        await wrapper.vm.onSave();

        expect(wrapper.vm.ignoreFieldsValidation).toContain('name');
        expect(spyValidationService).toHaveBeenCalledWith(
            wrapper.vm.blog,
            expect.anything(),
            expect.arrayContaining(['name']),
        );
    });

    it('should validate fields when language is not inherited', async () => {
        const spyValidationService = jest.spyOn(wrapper.vm.entityValidationService, 'validate');

        wrapper.vm.getCmsPageOverrides = jest.fn(() => {
            return null;
        });
        wrapper.vm.blog.isNew = jest.fn(() => {
            return false;
        });
        wrapper.vm.blog.prices = [];
        wrapper.vm.blog.price = [
            {
                currencyId: undefined,
                linked: true,
                gross: 100,
                net: 84.034,
                listPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
                regulationPrice: {
                    currencyId: undefined,
                    linked: true,
                    gross: 0,
                    net: 0,
                },
            },
        ];

        wrapper.vm.saveProduct = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.blog.getEntityName = () => 'blog';
        Contena.EntityDefinition.get = () => ({
            properties: {
                name: {
                    type: 'string',
                    flags: {
                        required: true,
                    },
                },
            },
        });

        Contena.Store.get('context').api.language = {
            id: '1a2b3c',
            parentId: null,
        };

        wrapper.vm.blog.name = null;

        await wrapper.vm.onSave();

        expect(wrapper.vm.ignoreFieldsValidation).not.toContain('name');
        expect(spyValidationService).toHaveBeenCalledWith(wrapper.vm.blog, expect.anything(), []);
    });

    it('should handle the purchase price if its not set', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                }),
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.blog.id).toBe('test');
        expect(wrapper.vm.blog.purchasePrices).toEqual([{ currencyId: undefined, net: 0, linked: true, gross: 0 }]);
        expect(wrapper.vm.blog._origin.purchasePrices).toEqual(wrapper.vm.blog.purchasePrices);
    });

    it('should handle the purchase price if its null', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    purchasePrices: null,
                }),
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.blog.id).toBe('test');
        expect(wrapper.vm.blog.purchasePrices).toEqual([{ currencyId: undefined, net: 0, linked: true, gross: 0 }]);
    });

    it('should handle the purchase price if its undefined', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    purchasePrices: undefined,
                }),
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.blog.id).toBe('test');
        expect(wrapper.vm.blog.purchasePrices).toEqual([{ currencyId: undefined, net: 0, linked: true, gross: 0 }]);
    });

    it('should synchronize the default purchase price with the parent blog origin', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            (id) => {
                if (id === 'parent-id') {
                    return Promise.resolve({
                        id: 'parent-id',
                        purchasePrices: undefined,
                    });
                }

                return Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: [{ currencyId: undefined, net: 84, gross: 100, linked: true }],
                    purchasePrices: [{ currencyId: undefined, net: 42, gross: 50, linked: true }],
                });
            },
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.parentProduct.purchasePrices).toEqual([{ currencyId: undefined, gross: 0, net: 0, linked: true }]);
        expect(wrapper.vm.parentProduct._origin.purchasePrices).toEqual(wrapper.vm.parentProduct.purchasePrices);
    });

    it('should not overwrite purchase price for variant blogs with parentId when null', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: null,
                    purchasePrices: null,
                }),
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.blog.id).toBe('test');
        expect(wrapper.vm.blog.purchasePrices).toBeNull();
    });

    it('should not overwrite purchase price for variant blogs with parentId when undefined', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: null,
                    purchasePrices: undefined,
                }),
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.blog.id).toBe('test');
        expect(wrapper.vm.blog.purchasePrices).toBeNull();
    });

    it('should keep existing purchase price for variant blogs with their own values', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: [
                        {
                            currencyId: undefined,
                            net: 10,
                            gross: 12,
                            linked: true,
                        },
                    ],
                    purchasePrices: [
                        {
                            currencyId: undefined,
                            net: 5,
                            gross: 6,
                            linked: true,
                        },
                    ],
                }),
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.blog.id).toBe('test');
        expect(wrapper.vm.blog.purchasePrices).toEqual([{ currencyId: undefined, net: 5, linked: true, gross: 6 }]);
    });

    it('should sync purchasePrices to null when price is inherited but purchasePrices is not', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    parentId: 'parent-id',
                    price: null,
                    purchasePrices: [
                        {
                            currencyId: undefined,
                            net: 5,
                            gross: 6,
                            linked: true,
                        },
                    ],
                }),
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.blog.id).toBe('test');
        expect(wrapper.vm.blog.purchasePrices).toBeNull();
    });

    it('should sync purchasePrices from parent when price is not inherited but purchasePrices is', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            (id) => {
                if (id === 'parent-id') {
                    return Promise.resolve({
                        id: 'parent-id',
                        price: [{ currencyId: undefined, net: 84, gross: 100, linked: true }],
                        purchasePrices: [{ currencyId: undefined, net: 42, gross: 50, linked: true }],
                    });
                }

                return Promise.resolve({
                    id: 'variant-id',
                    parentId: 'parent-id',
                    price: [{ currencyId: undefined, net: 84, gross: 100, linked: true }],
                    purchasePrices: null,
                });
            },
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.blog.purchasePrices).toEqual([{ currencyId: undefined, gross: 50, net: 42, linked: true }]);
    });

    it('should ignore purchase price if its set', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () =>
                Promise.resolve({
                    id: 'test',
                    purchasePrices: [
                        {
                            currencyId: undefined,
                            net: 10,
                            gross: 19,
                            linked: false,
                        },
                    ],
                }),
        );

        await wrapper.setProps({
            blogId: '1234',
        });

        await wrapper.vm.loadProduct();
        await flushPromises();

        expect(wrapper.vm.blog.id).toBe('test');
        expect(wrapper.vm.blog.purchasePrices).toEqual([{ currencyId: undefined, net: 10, linked: false, gross: 19 }]);
    });

    it('should reset mode settings to default when creating a new blog', async () => {
        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () => Promise.resolve({}),
            null,
        );

        await flushPromises();

        expect(wrapper.vm.modeSettings).toEqual([
            'general_information',
            'prices',
            'deliverability',
            'visibility_structure',
            'media',
            'labelling',
            'measurement',
            'selling_packaging',
            'properties',
            'essential_characteristics',
            'custom_fields',
        ]);
    });

    it('should load mode settings from cached admin_user config when editing existing blog', async () => {
        const mockSettings = {
            advancedMode: {
                label: 'sw-blog.general.textAdvancedMode',
                enabled: true,
            },
            settings: [
                {
                    key: 'prices',
                    label: 'sw-blog.detailBase.cardTitlePrices',
                    enabled: false,
                    name: 'general',
                },
            ],
        };

        Contena.Service('admin_userConfigService').search.mockImplementation((keys) => {
            if (keys.includes('mode.setting.advancedModeSettings')) {
                return Promise.resolve({
                    data: {
                        'mode.setting.advancedModeSettings': mockSettings,
                    },
                });
            }

            return Promise.resolve({ data: {} });
        });

        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () => Promise.resolve({}),
            null,
        );

        await flushPromises();

        await wrapper.setProps({ blogId: '1234' });
        await flushPromises();

        // 'prices' should be missing from modeSettings
        expect(wrapper.vm.modeSettings).toEqual([
            'general_information',
            'deliverability',
            'visibility_structure',
            'media',
            'labelling',
            'measurement',
            'selling_packaging',
            'properties',
            'essential_characteristics',
            'custom_fields',
        ]);
        expect(Contena.Service('admin_userConfigService').search).toHaveBeenCalledWith(['mode.setting.advancedModeSettings']);
    });

    it('should clear stale variant data when opening create page after viewing a variant blog', async () => {
        await wrapper.unmount();

        const store = Contena.Store.get('swProductDetail');
        store.blog = {
            id: 'variant-123',
            parentId: 'parent-456',
            variation: [],
        };
        store.parentProduct = { id: 'parent-456', name: 'Parent Blog' };

        wrapper = await createWrapper(
            () => Promise.resolve([]),
            () => Promise.resolve({ variation: [] }),
            null,
        );

        await flushPromises();
        expect(store.parentProduct).toEqual({});
    });
});

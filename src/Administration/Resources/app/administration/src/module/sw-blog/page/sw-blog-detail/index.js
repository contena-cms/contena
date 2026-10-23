/*
 * @sw-package inventory
 */

import EntityValidationService from 'src/app/service/entity-validation.service';
import template from './sw-blog-detail.html.twig';
import errorConfiguration from './error.cfg.json';
import './sw-blog-detail.scss';
import '../../page/sw-blog-detail/store';

const { Context, Mixin, EntityDefinition } = Contena;
const { Criteria, ChangesetGenerator } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;
const { mapPageErrors } = Contena.Component.getComponentHelper();
const type = Contena.Utils.types;

const ADVANCED_MODE_SETTINGS_KEY = 'mode.setting.advancedModeSettings';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'feature',
        'mediaService',
        'repositoryFactory',
        'numberRangeService',
        'seoUrlService',
        'acl',
        'systemConfigApiService',
        'customFieldDataProviderService',
        'entityValidationService',
    ],

    provide() {
        return {
            swProductDetailLoadAll: this.loadAll,
        };
    },

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('blog.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    props: {
        blogId: {
            type: String,
            required: false,
            default: null,
        },
        /**
         * @deprecated tag:v6.8.0 - will be removed, please use `creationType` instead
         */
        creationStates: {
            type: Array,
            required: false,
            default: null,
        },
        /* Blog "type" provided by the split button for creating a new blog through a router parameter */
        creationType: {
            type: String,
            required: false,
            default: 'physical',
        },
    },

    data() {
        return {
            blogNumberPreview: '',
            isSaveSuccessful: false,
            cloning: false,
            defaultSalesChannelVisibility: 30,
            previousLengthUnit: null,
            previousWeightUnit: null,
            updateSeoPromises: [],
            isProductNotFound: false,
            lastViewedProductId: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        localMode() {
            return Contena.Store.get('swProductDetail').localMode;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        advancedModeSetting() {
            return Contena.Store.get('swProductDetail').advancedModeSetting;
        },

        modeSettings() {
            return Contena.Store.get('swProductDetail').modeSettings;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        isChild() {
            return Contena.Store.get('swProductDetail').isChild;
        },

        defaultCurrency() {
            return Contena.Store.get('swProductDetail').defaultCurrency;
        },

        getDefaultFeatureSet() {
            return Contena.Store.get('swProductDetail').getDefaultFeatureSet;
        },

        showModeSetting() {
            return Contena.Store.get('swProductDetail').showModeSetting;
        },

        advanceModeEnabled() {
            return Contena.Store.get('swProductDetail').advanceModeEnabled;
        },

        blogDetailTabs() {
            const createRouteTab = (label, routeName, additionalProperties = {}) => {
                const route = {
                    name: routeName,
                    params: { id: this.$route.params.id },
                };

                return {
                    label: this.$t(label),
                    name: route.name,
                    onClick: () => {
                        void this.$router.push(route);
                    },
                    ...additionalProperties,
                };
            };

            const tabs = [
                createRouteTab('sw-blog.detail.tabGeneral', 'sw.blog.detail.base', {
                    hasError: this.swProductDetailBaseError,
                }),
                createRouteTab('sw-blog.detail.tabSpecifications', 'sw.blog.detail.specifications'),
            ];

            if (this.showModeSetting) {
                tabs.push(createRouteTab('sw-blog.detail.tabAdvancedPrices', 'sw.blog.detail.prices'));
            }

            if (!this.isChild && this.showModeSetting) {
                tabs.push(createRouteTab('sw-blog.detail.tabVariation', 'sw.blog.detail.variants'));
            }

            if (this.showModeSetting) {
                tabs.push(
                    createRouteTab('sw-blog.detail.tabLayout', 'sw.blog.detail.layout'),
                    createRouteTab('sw-blog.detail.tabSeo', 'sw.blog.detail.seo'),
                    createRouteTab('sw-blog.detail.tabCrossSelling', 'sw.blog.detail.crossSelling', {
                        hasError: this.swProductDetailCrossSellingError,
                    }),
                    createRouteTab('sw-blog.detail.tabReviews', 'sw.blog.detail.reviews'),
                );
            }

            return tabs;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, please use `blogType` instead
         */
        blogStates() {
            return Contena.Store.get('swProductDetail').blogStates;
        },

        blogType() {
            return Contena.Store.get('swProductDetail').blogType;
        },

        isUploadedFileRequired() {
            return this.blogType === 'digital' && !Contena.Store.get('swProductDetail').isDownloadExternallyManaged;
        },

        ...mapPageErrors(errorConfiguration),

        identifier() {
            return this.blogTitle;
        },

        blogTitle() {
            // when blog is variant
            if (this.isChild && this.blog) {
                return this.getInheritTitle();
            }

            if (!this.$i18n) {
                return '';
            }

            // return name
            return this.placeholder(this.blog, 'name', this.$t('sw-blog.detail.textHeadline'));
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        propertyRepository() {
            return this.repositoryFactory.create('property_group_option');
        },

        syncRepository() {
            return this.repositoryFactory.create('blog', null, {
                useSync: true,
            });
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        blogVisibilityRepository() {
            return this.repositoryFactory.create('blog_visibility');
        },

        mediaRepository() {
            if (this.blog && this.blog.media) {
                return this.repositoryFactory.create(this.blog.media.entity, this.blog.media.source);
            }
            return null;
        },

        featureSetRepository() {
            return this.repositoryFactory.create('blog_feature_set');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        taxRepository() {
            return this.repositoryFactory.create('tax');
        },

        currentUser() {
            return Contena.Store.get('session').currentUser;
        },

        blogCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.setTotalCountMode(0);

            criteria.getAssociation('media').addSorting(Criteria.sort('position', 'ASC'));
            criteria.addAssociation('media.media');

            criteria.getAssociation('prices').addSorting(Criteria.sort('quantityStart', 'ASC', true));

            criteria.getAssociation('tags').addSorting(Criteria.sort('name', 'ASC'));

            criteria.getAssociation('seoUrls').addFilter(Criteria.equals('isCanonical', true));

            criteria
                .getAssociation('crossSellings')
                .addSorting(Criteria.sort('position', 'ASC'))
                .getAssociation('assignedProducts')
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('blog')
                .getAssociation('blog')
                .addAssociation('options.group');

            criteria
                .addAssociation('cover.media')
                .addAssociation('openGraphMedia')
                .addAssociation('categories')
                .addAssociation('visibilities.channel')
                .addAssociation('options')
                .addAssociation('configuratorSettings.option')
                .addAssociation('unit')
                .addAssociation('blogReviews')
                .addAssociation('seoUrls')
                .addAssociation('mainCategories')
                .addAssociation('options.group')
                .addAssociation('customFieldSets')
                .addAssociation('featureSet')
                .addAssociation('cmsPage')
                .addAssociation('translations')
                .addAssociation('downloads.media');

            criteria.getAssociation('manufacturer').addAssociation('media');

            return criteria;
        },

        defaultFeatureSetCriteria() {
            const criteria = new Criteria(1, 1);

            criteria
                .addSorting(Criteria.sort('createdAt', 'ASC'))
                .addFilter(Criteria.equalsAny('name', ['Default', 'Standard']));

            return criteria;
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        getModeSettingGeneralTab() {
            return [
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
                    label: 'sw-blog.detailBase.cardTitleAssignment',
                    enabled: true,
                    name: 'general',
                },
                {
                    key: 'media',
                    label: 'sw-blog.detailBase.cardTitleMedia',
                    enabled: true,
                    name: 'general',
                },
                {
                    key: 'labelling',
                    label: 'sw-blog.detailBase.cardTitleSettings',
                    enabled: true,
                    name: 'general',
                },
            ];
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        getModeSettingSpecificationsTab() {
            return [
                {
                    key: 'measurement',
                    label: 'sw-blog.specifications.cardTitleMeasurement',
                    enabled: true,
                    name: 'specifications',
                },
                {
                    key: 'selling_packaging',
                    label: 'sw-blog.specifications.cardTitleSellingPackaging',
                    enabled: true,
                    name: 'specifications',
                },
                {
                    key: 'properties',
                    label: 'sw-blog.specifications.cardTitleProperties',
                    enabled: true,
                    name: 'specifications',
                },
                {
                    key: 'essential_characteristics',
                    label: 'sw-blog.specifications.cardTitleEssentialCharacteristics',
                    enabled: true,
                    name: 'specifications',
                },
                {
                    key: 'custom_fields',
                    label: 'sw-blog.specifications.cardTitleCustomFields',
                    enabled: true,
                    name: 'specifications',
                },
            ];
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        showAdvanceModeSetting() {
            if (this.isChild) {
                return false;
            }

            const routes = ['sw.blog.detail.base', 'sw.blog.detail.specifications'];

            return routes.includes(this.$route.name);
        },

        cmsPageState() {
            return Contena.Store.get('cmsPage');
        },

        currentPage() {
            return Contena.Store.get('cmsPage').currentPage;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        language() {
            return Contena.Store.get('context').api.language;
        },

        translateFields() {
            if (!this.blog) {
                return null;
            }

            return Object.keys(EntityDefinition.getTranslatedFields(this.blog.getEntityName()));
        },

        ignoreFieldsValidation() {
            if (!this.language?.parentId) {
                return [];
            }

            const blogData = { ...this.blog };

            // This filter identifies fields in a child language that are null, undefined, or empty.
            // These specific fields might be inheriting their values from the parent language,
            // so they are intentionally ignored during validation.
            return (this.translateFields || []).filter((field) => {
                const value = blogData[field];

                return value === null || value === undefined || value === '';
            });
        },

        blogApiContext() {
            return {
                ...Contena.Context.api,
                measurementWeightUnit: this.weightUnit,
                measurementLengthUnit: this.lengthUnit,
            };
        },

        lengthUnit() {
            return Contena.Store.get('swProductDetail').lengthUnit;
        },

        weightUnit() {
            return Contena.Store.get('swProductDetail').weightUnit;
        },

        measurementUnitsChanged() {
            return this.previousWeightUnit !== this.weightUnit || this.previousLengthUnit !== this.lengthUnit;
        },
    },

    watch: {
        blogId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    beforeRouteLeave() {
        Contena.Store.get('contenaApps').selectedIds = [];
    },

    methods: {
        async createdComponent() {
            this.isProductNotFound = false;

            Contena.ExtensionAPI.publishData({
                id: 'sw-blog-detail__product',
                path: 'blog',
                scope: this,
            });

            Contena.ExtensionAPI.publishData({
                id: 'sw-blog-detail__cmsPage',
                path: 'currentPage',
                scope: this,
            });

            this.cmsPageState.resetCmsPageState();

            // when create
            if (!this.blogId) {
                this.lastViewedProductId = null;
                // Immediately reset store to prevent
                // stale data from a previous variant/child blog from rendering
                // before initState() creates a new blog entity
                Contena.Store.get('swProductDetail').$reset();

                // set language to system language
                if (!Contena.Store.get('context').isSystemDefaultLanguage) {
                    Contena.Store.get('context').resetLanguageToDefault();
                }
            }

            Contena.Store.get('swProductDetail').setLoading(['blog', true]);

            await this.initProductMeasurementUnits();

            // initialize default state
            this.initState();

            this.initAdvancedModeSettings();
        },

        initState() {
            Contena.Store.get('swProductDetail').apiContext = Contena.Context.api;

            // when blog exists
            if (this.blogId) {
                return this.loadState();
            }

            // When no blog id exists init state and new blog with the repositoryFactory
            return this.createState().then(() => {
                // create new blog number
                this.numberRangeService.reserve('blog', '', true).then((response) => {
                    this.blogNumberPreview = response.number;
                    this.blog.blogNumber = response.number;
                });
            });
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        initAdvancedModeSettings() {
            Contena.Store.get('swProductDetail').advancedModeSetting = this.getAdvancedModeDefaultSetting();

            // Only load settings when editing existing blog
            if (this.blogId) {
                this.getAdvancedModeSetting();
            } else {
                // Reset modeSettings to default when creating a new blog
                Contena.Store.get('swProductDetail').modeSettings = this.changeModeSettings();
            }
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        createUserModeSetting() {
            return {
                key: ADVANCED_MODE_SETTINGS_KEY,
                admin_userId: this.currentUser && this.currentUser.id,
            };
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        getAdvancedModeDefaultSetting() {
            const defaultSettings = this.createUserModeSetting();
            defaultSettings.value = {
                advancedMode: {
                    label: 'sw-blog.general.textAdvancedMode',
                    enabled: true,
                },
                settings: [...this.getModeSettingGeneralTab, ...this.getModeSettingSpecificationsTab],
            };
            return defaultSettings;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        async getAdvancedModeSetting() {
            const modeSettingsValue = (await Contena.Service('admin_userConfigService').search([ADVANCED_MODE_SETTINGS_KEY]))
                ?.data?.[ADVANCED_MODE_SETTINGS_KEY];

            if (!modeSettingsValue) {
                return;
            }

            const modeSettings = {
                ...this.createUserModeSetting(),
                value: cloneDeep(modeSettingsValue),
            };
            const defaultSettings = this.getAdvancedModeDefaultSetting().value.settings;

            modeSettings.value.settings = defaultSettings.reduce((accumulator, defaultEntry) => {
                const foundEntry = modeSettings.value.settings.find((dbEntry) => dbEntry.key === defaultEntry.key);
                accumulator.push(foundEntry || defaultEntry);

                return accumulator;
            }, []);

            Contena.Store.get('swProductDetail').advancedModeSetting = modeSettings;
            Contena.Store.get('swProductDetail').modeSettings = this.changeModeSettings();

            await this.$nextTick();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        saveAdvancedMode() {
            Contena.Store.get('swProductDetail').setLoading(['advancedMode', true]);

            return Contena.Service('admin_userConfigService')
                .upsert({
                    [ADVANCED_MODE_SETTINGS_KEY]: this.advancedModeSetting.value,
                })
                .then(async () => {
                    await this.getAdvancedModeSetting();
                    Contena.Store.get('swProductDetail').setLoading(['advancedMode', false]);
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('global.notification.unspecifiedSaveErrorMessage'),
                    });
                });
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        onChangeSetting() {
            Contena.Store.get('swProductDetail').advancedModeSetting = this.advancedModeSetting;
            this.saveAdvancedMode();
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        changeModeSettings() {
            const enabledModeItems = this.advancedModeSetting.value.settings.filter((item) => item.enabled);
            if (!enabledModeItems.length) {
                return [];
            }

            return enabledModeItems.map((item) => item.key);
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        onChangeSettingItem() {
            Contena.Store.get('swProductDetail').modeSettings = this.changeModeSettings();
            this.saveAdvancedMode();
        },

        loadState() {
            Contena.Store.get('swProductDetail').localMode = false;
            Contena.Store.get('contenaApps').selectedIds = [this.blogId];

            return this.loadAll();
        },

        loadAll() {
            return Promise.all([
                this.loadProduct(),
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadAttributeSet(),
            ]);
        },

        createState() {
            // set local mode
            Contena.Store.get('swProductDetail').localMode = true;
            Contena.Store.get('contenaApps').selectedIds = [];

            Contena.Store.get('swProductDetail').setLoading(['blog', true]);

            // set blog "type"
            if (!Contena.Feature.isActive('v6.8.0.0')) {
                Contena.Store.get('swProductDetail').creationStates = this.creationStates;
            }

            Contena.Store.get('swProductDetail').creationType = this.creationType;

            // create empty blog
            Contena.Store.get('swProductDetail').blog = this.blogRepository.create();

            // fill empty data
            this.blog.active = true;
            this.blog.taxId = null;

            this.blog.metaTitle = '';
            this.blog.additionalText = '';
            this.blog.variantListingConfig = {};

            if (this.creationType) {
                this.adjustProductAccordingToType();
            }

            return Promise.all([
                this.loadCurrencies(),
                this.loadTaxes(),
                this.loadAttributeSet(),
                this.loadDefaultFeatureSet(),
            ]).then(() => {
                // set default blog price and empty purchase price
                this.blog.price = [
                    {
                        currencyId: this.defaultCurrency.id,
                        net: null,
                        linked: true,
                        gross: null,
                    },
                ];

                this.blog.purchasePrices = this.getDefaultPurchasePrices();

                // Set default tax rate / sales channels on creation
                if (this.blog.isNew) {
                    this.getDefaultTaxRate().then((result) => {
                        this.blog.taxId = result;
                    });

                    this.getDefaultSalesChannels().then((result) => {
                        if (type.isEmpty(result)) {
                            return;
                        }

                        this.blog.active = result.defaultActive;

                        if (!result.defaultSalesChannelIds || result.defaultSalesChannelIds.length <= 0) {
                            return;
                        }

                        this.fetchSalesChannelByIds(result.defaultSalesChannelIds).then((channels) => {
                            if (!channels.length) {
                                return;
                            }

                            channels.forEach((channel) => {
                                const visibilities = this.createProductVisibilityEntity(
                                    result.defaultVisibilities,
                                    channel,
                                );
                                this.blog.visibilities.push(visibilities);
                            });
                        });
                    });
                }

                if (this.getDefaultFeatureSet?.length) {
                    this.blog.featureSetId = this.getDefaultFeatureSet?.[0].id;
                }

                Contena.Store.get('swProductDetail').setLoading(['blog', false]);
            });
        },

        adjustProductAccordingToType() {
            if (this.creationType === 'digital') {
                this.blog.maxPurchase = 1;
            }

            this.blog.type = this.creationType;
        },

        loadProduct() {
            Contena.Store.get('swProductDetail').setLoading(['blog', true]);

            return this.blogRepository
                .get(this.blogId || this.blog.id, this.blogApiContext, this.blogCriteria)
                .then(async (blog) => {
                    if (!blog) {
                        await this.onProductNotFound();

                        return;
                    }

                    if (!blog.parentId && (!blog.purchasePrices || blog.purchasePrices.length === 0)) {
                        if (!this.defaultCurrency?.id) {
                            await this.loadCurrencies();
                        }

                        blog.purchasePrices = this.getDefaultPurchasePrices();
                        blog._origin.purchasePrices = cloneDeep(blog.purchasePrices);
                    }

                    if (blog.propertyIds?.length > 0) {
                        const propertyCriteria = new Criteria(1, null);
                        propertyCriteria.addSorting(Criteria.sort('name', 'ASC', true));
                        propertyCriteria.setIds(blog.propertyIds);
                        propertyCriteria.addFilter(Criteria.equals('blogProperties.id', blog.id));

                        const result = await this.propertyRepository.search(propertyCriteria);
                        result.source = blog.properties.source;

                        blog._origin.properties = cloneDeep(result);
                        blog.properties = result;
                    }

                    Contena.Store.get('swProductDetail').isDownloadExternallyManaged = !blog.downloads?.length;
                    Contena.Store.get('swProductDetail').blog = blog;

                    if (this.blog.parentId) {
                        await this.loadParentProduct();
                        this.syncVariantPriceInheritance();
                    } else {
                        Contena.Store.get('swProductDetail').parentProduct = {};
                    }

                    if (this.blogId === blog.id && this.lastViewedProductId !== blog.id) {
                        this.lastViewedProductId = blog.id;
                        Contena.Telemetry.track({
                            eventName: 'sw_product.detail.blog_viewed',
                            blog_type: blog.type,
                        });
                    }
                })
                .finally(() => {
                    Contena.Store.get('swProductDetail').setLoading(['blog', false]);
                });
        },

        async onProductNotFound() {
            this.isProductNotFound = true;
            Contena.Store.get('contenaApps').selectedIds = [];

            this.createNotificationError({
                message: this.$t('sw-blog.detail.messageProductNotFound'),
            });

            try {
                await this.$router.push({
                    name: 'sw.blog.index',
                });
            } catch {
                // Ignore navigation failures. The missing blog state still prevents the detail view from rendering.
            } finally {
                const blogDetailStore = Contena.Store.get('swProductDetail');
                blogDetailStore.blog = {};
                blogDetailStore.parentProduct = {};
            }
        },

        syncVariantPriceInheritance() {
            const priceInherited = this.blog.price === null;
            const purchasePricesInherited = this.blog.purchasePrices === null;

            // Price is inherited — purchasePrices must also inherit
            if (priceInherited) {
                this.blog.purchasePrices = null;
                return;
            }

            // Price is overridden but purchasePrices still inherited — copy from parent
            if (purchasePricesInherited) {
                this.blog.purchasePrices = cloneDeep(this.parentProduct.purchasePrices);
            }
        },

        getDefaultPurchasePrices() {
            return [
                {
                    currencyId: this.defaultCurrency.id,
                    net: 0,
                    linked: true,
                    gross: 0,
                },
            ];
        },

        loadParentProduct() {
            Contena.Store.get('swProductDetail').setLoading(['parentProduct', true]);

            return this.blogRepository
                .get(this.blog.parentId, Contena.Context.api, this.blogCriteria)
                .then(async (parent) => {
                    if (!parent.purchasePrices || parent.purchasePrices.length === 0) {
                        if (!this.defaultCurrency?.id) {
                            await this.loadCurrencies();
                        }

                        parent.purchasePrices = this.getDefaultPurchasePrices();
                        parent._origin.purchasePrices = cloneDeep(parent.purchasePrices);
                    }

                    if (parent.propertyIds?.length > 0) {
                        const propertyCriteria = new Criteria(1, null);
                        propertyCriteria.addSorting(Criteria.sort('name', 'ASC', true));
                        propertyCriteria.setIds(parent.propertyIds);

                        const result = await this.propertyRepository.search(propertyCriteria);
                        result.source = parent.properties.source;

                        parent._origin.properties = cloneDeep(result);
                        parent.properties = result;
                    }

                    Contena.Store.get('swProductDetail').parentProduct = parent;
                })
                .then(() => {
                    Contena.Store.get('swProductDetail').setLoading(['parentProduct', false]);
                });
        },

        loadCurrencies() {
            Contena.Store.get('swProductDetail').setLoading(['currencies', true]);

            const criteria = new Criteria(1, 500);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return this.currencyRepository
                .search(criteria, Contena.Context.api, {
                    cacheKey: ['shared-data', 'currencies', Contena.Context.api.languageId ?? 'default'],
                    ttl: 5 * 60 * 1000,
                })
                .then((res) => {
                    Contena.Store.get('swProductDetail').currencies = [...res];
                })
                .finally(() => {
                    Contena.Store.get('swProductDetail').setLoading(['currencies', false]);
                });
        },

        loadTaxes() {
            Contena.Store.get('swProductDetail').setLoading(['taxes', true]);

            const criteria = new Criteria(1, 500);
            criteria.addSorting(Criteria.sort('position'));

            return this.taxRepository
                .search(criteria, Contena.Context.api, {
                    cacheKey: ['shared-data', 'taxes', Contena.Context.api.languageId ?? 'default'],
                    ttl: 5 * 60 * 1000,
                })
                .then((res) => {
                    Contena.Store.get('swProductDetail').setTaxes(res);
                })
                .finally(() => {
                    Contena.Store.get('swProductDetail').setLoading(['taxes', false]);
                });
        },

        getDefaultTaxRate() {
            return Contena.Service('cacheService').query({
                key: ['shared-data', 'default-tax-rate-id'],
                ttl: 5 * 60 * 1000,
                fn: () =>
                    this.systemConfigApiService.getValues('core.tax').then((response) => {
                        const defaultTaxRateId = response['core.tax.defaultTaxRate'];

                        return typeof defaultTaxRateId === 'string' ? defaultTaxRateId : null;
                    }),
            });
        },

        loadAttributeSet() {
            Contena.Store.get('swProductDetail').setLoading(['customFieldSets', true]);

            const customFieldDataProviderService =
                this.customFieldDataProviderService ?? Contena.Service('customFieldDataProviderService');

            return customFieldDataProviderService
                .getCustomFieldSets('blog')
                .then((res) => {
                    Contena.Store.get('swProductDetail').customFieldSets = res;
                })
                .finally(() => {
                    Contena.Store.get('swProductDetail').setLoading(['customFieldSets', false]);
                });
        },

        loadDefaultFeatureSet() {
            Contena.Store.get('swProductDetail').setLoading(['defaultFeatureSet', true]);

            return this.featureSetRepository
                .search(this.defaultFeatureSetCriteria)
                .then((res) => {
                    Contena.Store.get('swProductDetail').setDefaultFeatureSet(res);
                })
                .finally(() => {
                    Contena.Store.get('swProductDetail').setLoading(['defaultFeatureSet', false]);
                });
        },

        getDefaultSalesChannels() {
            return this.systemConfigApiService.getValues('core.defaultSalesChannel').then((response) => {
                if (type.isEmpty(response)) {
                    return {};
                }

                return {
                    defaultSalesChannelIds: response?.['core.defaultSalesChannel.channel'],
                    defaultVisibilities: response?.['core.defaultSalesChannel.visibility'],
                    defaultActive: !!response?.['core.defaultSalesChannel.active'],
                };
            });
        },

        fetchSalesChannelByIds(ids) {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equalsAny('id', ids));

            return this.channelRepository.search(criteria);
        },

        createProductVisibilityEntity(visibility, channel) {
            const visibilities = this.blogVisibilityRepository.create(Context.api);

            Object.assign(visibilities, {
                visibility: visibility[channel.id] || this.defaultSalesChannelVisibility,
                blogId: this.blog.id,
                channelId: channel.id,
                channel: channel,
            });

            return visibilities;
        },

        abortOnLanguageChange() {
            return this.blogRepository.hasChanges(this.blog);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            Contena.Store.get('context').setApiLanguageId(languageId);
            this.loadLanguage(languageId);

            this.initState();
        },

        saveFinish() {
            this.isSaveSuccessful = false;

            if (!this.blogId) {
                this.$router.push({
                    name: 'sw.blog.detail',
                    params: { id: this.blog.id },
                });
            }
        },

        onSave() {
            if (!this.validateProductPurchase()) {
                this.createNotificationError({
                    message: this.$t('sw-blog.detail.errorMinMaxPurchase'),
                });

                return new Promise((resolve) => {
                    resolve();
                });
            }

            this.validateProductPrices();

            if (!this.blogId) {
                if (this.blogNumberPreview === this.blog.blogNumber) {
                    this.numberRangeService.reserve('blog').then((response) => {
                        this.blogNumberPreview = 'reserved';
                        this.blog.blogNumber = response.number;
                    });
                }
            }

            this.isSaveSuccessful = false;

            if (type.isEmpty(this.blog.slotConfig)) {
                this.blog.slotConfig = null;
            }

            if (!this.entityValidationService.validate(this.blog, this.customValidate, this.ignoreFieldsValidation)) {
                const titleSaveError = this.$t('global.default.error');
                const messageSaveError = this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid');

                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError,
                });
                return Promise.resolve();
            }

            return this.saveProduct().then(this.onSaveFinished);
        },

        customValidate(errors, blog) {
            if (this.isUploadedFileRequired && (blog.downloads === undefined || blog.downloads.length < 1)) {
                errors.push({
                    ...EntityValidationService.createRequiredError('/0/downloads'),
                    code: 'SW_BLOG_DOWNLOADS_EXTERNAL_MANAGEMENT_REQUIRED',
                    detail: this.$t('sw-blog.detailBase.downloadsExternalManagementRequired'),
                });
            }

            return errors;
        },

        validateProductPrices() {
            this.blog.prices.forEach((advancedPrice) => {
                this.validatePrices('listPrice', advancedPrice.price);
            });
            this.validatePrices('listPrice', this.blog.price);

            this.blog.prices.forEach((advancedPrice) => {
                this.validatePrices('regulationPrice', advancedPrice.price);
            });
            this.validatePrices('regulationPrice', this.blog.price);
        },

        validatePrices(priceLabel, prices) {
            if (!prices) {
                return;
            }

            prices.forEach((price) => {
                if (!price[priceLabel]) {
                    return;
                }

                if (!price[priceLabel].gross && !price[priceLabel].net) {
                    price[priceLabel] = null;
                    return;
                }

                if (!price[priceLabel].gross) {
                    price[priceLabel].gross = 0;
                    return;
                }

                if (!price[priceLabel].net) {
                    price[priceLabel].net = 0;
                }
            });
        },

        onSaveFinished(response) {
            if (response !== 'success' && response !== 'empty') {
                const errorCode = response?.response?.data?.errors?.[0]?.code;

                if (errorCode === 'CONTENT__DUPLICATE_BLOG_NUMBER') {
                    const titleSaveError = this.$t('global.default.error');
                    const messageSaveError = this.$t('sw-blog.notification.notificationSaveErrorProductNoAlreadyExists', {
                        blogNo: response.response.data.errors[0].meta.parameters.number,
                    });

                    this.createNotificationError({
                        title: titleSaveError,
                        message: messageSaveError,
                    });
                    return;
                }

                const errorDetail = response?.response?.data?.errors?.[0]?.detail;
                const titleSaveError = this.$t('global.default.error');
                const messageSaveError =
                    errorDetail ?? this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid');

                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError,
                });
                return;
            }

            Contena.Store.get('error').resetApiErrors();

            Contena.Utils.EventBus.emit('sw-blog-detail-save-success');

            if (this.updateSeoPromises.length === 0) {
                this.isSaveSuccessful = true;

                return;
            }

            Contena.Store.get('swProductDetail').setLoading(['blog', true]);

            Promise.all(this.updateSeoPromises)
                .then(() => {
                    Contena.Utils.EventBus.emit('sw-blog-detail-save-finish');
                })
                .then(() => {
                    this.isSaveSuccessful = true;
                })
                .catch(() => Promise.resolve())
                .finally(() => {
                    Contena.Store.get('swProductDetail').setLoading(['blog', false]);

                    this.loadProduct();
                });
        },

        onCancel() {
            this.$router.push({ name: 'sw.blog.index' });
        },

        saveProduct() {
            Contena.Store.get('swProductDetail').setLoading(['blog', true]);

            this.updateSeoPromises = [];

            if (Contena.Store.list().includes('swSeoUrl')) {
                const seoUrls = Contena.Store.get('swSeoUrl').newOrModifiedUrls;
                const defaultSeoUrl = Contena.Store.get('swSeoUrl').defaultSeoUrl;

                if (seoUrls) {
                    seoUrls.forEach((seoUrl) => {
                        if (!seoUrl.seoPathInfo) {
                            seoUrl.seoPathInfo = defaultSeoUrl.seoPathInfo;
                            seoUrl.isModified = false;
                        } else {
                            seoUrl.isModified = true;
                        }

                        this.updateSeoPromises.push(
                            this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId).catch((error) => {
                                if (error.response?.data?.errors) {
                                    error.response.data.errors.forEach((apiError) => {
                                        const messageKey = `global.error-codes.${apiError.detail}`;
                                        const params = apiError.meta?.parameters || {};
                                        const translated = this.$t(messageKey, params);

                                        const message =
                                            translated !== messageKey
                                                ? translated
                                                : apiError.detail ||
                                                  apiError.title ||
                                                  this.$t('global.notification.unspecifiedSaveErrorMessage');

                                        this.createNotificationError({ message });
                                    });
                                } else {
                                    const message =
                                        error.message || this.$t('global.notification.unspecifiedSaveErrorMessage');
                                    this.createNotificationError({ message });
                                }

                                return Promise.reject(error);
                            }),
                        );
                    });
                }
            }

            return new Promise((resolve) => {
                // check if blog exists
                if (!this.blogRepository.hasChanges(this.blog)) {
                    Contena.Store.get('swProductDetail').setLoading(['blog', false]);
                    resolve('empty');
                    Contena.Store.get('swProductDetail').setLoading(['blog', false]);
                    return;
                }

                const blogType = this.blogType;
                const blogIsNew = this.blog.isNew();

                // save blog
                this.syncRepository
                    .save(this.blog, this.blogApiContext)
                    .then(() => {
                        Contena.Telemetry.track({
                            eventName: 'sw_product.detail.blog_saved',
                            blog_type: blogType,
                            blog_is_new: blogIsNew,
                        });

                        this.savePreferenceUnits()
                            .then(() => {
                                this.previousLengthUnit = this.lengthUnit;
                                this.previousWeightUnit = this.weightUnit;
                            })
                            .catch((response) => {
                                resolve(response);
                            });

                        this.loadAll().then(() => {
                            Contena.Store.get('swProductDetail').setLoading(['blog', false]);

                            resolve('success');
                        });
                    })
                    .catch((response) => {
                        Contena.Store.get('swProductDetail').setLoading(['blog', false]);
                        resolve(response);
                    });
            });
        },

        removeMediaItem(state, mediaId) {
            const media = this.blog.media.find((mediaItem) => mediaItem.mediaId === mediaId);

            // remove cover id if mediaId matches
            if (this.blog.coverId === media.id) {
                this.blog.coverId = null;
            }

            this.blog.media.remove(mediaId);
        },

        onCoverChange(mediaId) {
            if (!mediaId || mediaId.length < 0) {
                return;
            }

            const media = this.blog.media.find((mediaItem) => mediaItem.mediaId === mediaId);

            if (media) {
                this.blog.coverId = media.id;
            }
        },

        getInheritTitle() {
            if (
                this.blog.hasOwnProperty('translated') &&
                this.blog.translated.hasOwnProperty('name') &&
                this.blog.translated.name !== null
            ) {
                return this.blog.translated.name;
            }
            if (this.blog.name !== null) {
                return this.blog.name;
            }
            if (this.parentProduct && this.parentProduct.hasOwnProperty('translated')) {
                const pProduct = this.parentProduct;
                return pProduct.translated.hasOwnProperty('name') ? pProduct.translated.name : pProduct.name;
            }
            return '';
        },

        onDuplicate() {
            this.cloning = true;
        },

        onDuplicateFinish(duplicate) {
            this.cloning = false;
            this.$router.push({
                name: 'sw.blog.detail',
                params: { id: duplicate.id },
            });
        },

        validateProductPurchase() {
            if (this.blog.maxPurchase && this.blog.minPurchase > this.blog.maxPurchase) {
                return false;
            }

            return true;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        getCmsPageOverrides() {
            if (this.currentPage === null) {
                return null;
            }

            this.deleteSpecifcKeys(this.currentPage.sections);

            const changesetGenerator = new ChangesetGenerator();
            const { changes } = changesetGenerator.generate(this.currentPage);

            const slotOverrides = {};
            if (changes === null) {
                return slotOverrides;
            }

            if (type.isArray(changes.sections)) {
                changes.sections.forEach((section) => {
                    if (type.isArray(section.blocks)) {
                        section.blocks.forEach((block) => {
                            if (type.isArray(block.slots)) {
                                block.slots.forEach((slot) => {
                                    slotOverrides[slot.id] = slot.config;
                                });
                            }
                        });
                    }
                });
            }

            return slotOverrides;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        deleteSpecifcKeys(sections) {
            if (!sections) {
                return;
            }

            sections.forEach((section) => {
                if (!section.blocks) {
                    return;
                }

                section.blocks.forEach((block) => {
                    if (!block.slots) {
                        return;
                    }

                    block.slots.forEach((slot) => {
                        if (!slot.config) {
                            return;
                        }

                        Object.values(slot.config).forEach((configField) => {
                            if (configField.entity) {
                                delete configField.entity;
                            }
                            if (configField.hasOwnProperty('required')) {
                                delete configField.required;
                            }
                            if (configField.type) {
                                delete configField.type;
                            }
                        });
                    });
                });
            });
        },

        async loadLanguage(newLanguageId) {
            Contena.Store.get('context').api.language = await this.languageRepository.get(newLanguageId, {
                ...Contena.Context.api,
                inheritance: true,
            });
        },

        async initProductMeasurementUnits() {
            const preferenceUnits = await this.getPreferredMeasurementUnits();
            const store = Contena.Store.get('swProductDetail');

            const defaultUnits = {
                length: store.lengthUnit,
                weight: store.weightUnit,
            };

            const units = preferenceUnits || defaultUnits;

            store.setLengthUnit(units.length);
            store.setWeightUnit(units.weight);

            this.previousLengthUnit = units.length;
            this.previousWeightUnit = units.weight;
        },

        async getPreferredMeasurementUnits() {
            try {
                return (await Contena.Service('admin_userConfigService').search(['measurement.preferenceUnits']))?.data?.[
                    'measurement.preferenceUnits'
                ];
            } catch {
                // the blog must not stay in its loading state when the preferences cannot be read,
                // initProductMeasurementUnits() falls back to the default units instead
                return null;
            }
        },

        savePreferenceUnits() {
            if (!this.measurementUnitsChanged) {
                return Promise.resolve();
            }

            return Contena.Service('admin_userConfigService').upsert({
                'measurement.preferenceUnits': {
                    length: this.lengthUnit,
                    weight: this.weightUnit,
                },
            });
        },
    },
};

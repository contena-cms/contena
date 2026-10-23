/**
 * @sw-package discovery
 */

import EntityValidationService from 'src/app/service/entity-validation.service';
import template from './sw-sales-channel-detail.html.twig';
import './sw-sales-channel-detail.scss';

const { Mixin, Context, Defaults } = Contena;
const { Criteria } = Contena.Data;
const objectHelper = Contena.Utils.object;
const ContenaError = Contena.Classes.ContenaError;

const REQUIRED_BASE_FIELDS = [
    'name',
    'userGroupId',
    'currencyId',
    'languageId',
    'paymentMethodId',
    'shippingMethodId',
    'countryId',
    'navigationCategoryId',
];

const REQUIRED_BLOG_EXPORT_FIELDS = ['name'];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'exportTemplateService',
        'systemConfigApiService',
        'acl',
        'feature',
        'customFieldDataProviderService',
    ],

    provide() {
        return {
            /** @deprecated tag:v6.8.0 - Will be removed */
            swSalesChannelDetailGetAgenticCommerceExportConfig: () => this.agenticCommerceExportConfig,
        };
    },

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
    },

    data() {
        return {
            channel: null,
            isLoading: false,
            customFieldSets: [],
            isSaveSuccessful: false,
            blogComparison: {
                newProductExport: null,
                blogComparisonAccessUrl: null,
                invalidFileName: false,
                templateOptions: [],
                templates: null,
                templateName: null,
                previousTemplateName: null,
                showTemplateModal: false,
                selectedTemplate: null,
            },
            /** @deprecated tag:v6.8.0 - Will be removed */
            agenticCommerceExportConfig: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.channel, 'name');
        },

        blogExport() {
            if (this.channel && this.channel.blogExports.first()) {
                return this.channel.blogExports.first();
            }

            if (this.blogComparison.newProductExport) {
                return this.blogComparison.newProductExport;
            }

            this.blogComparison.newProductExport = this.blogExportRepository.create();
            this.blogComparison.newProductExport.interval = 0;
            this.blogComparison.newProductExport.generateByCronjob = false;

            return this.blogComparison.newProductExport;
        },

        isFrontend() {
            if (!this.channel) {
                return this.$route.params.typeId === Defaults.frontendSalesChannelTypeId;
            }

            return this.channel.typeId === Defaults.frontendSalesChannelTypeId;
        },

        isProductComparison() {
            if (!this.channel) {
                return this.$route.params.typeId === Defaults.blogComparisonTypeId;
            }

            return this.channel.typeId === Defaults.blogComparisonTypeId;
        },

        isHeadless() {
            if (!this.channel) {
                return this.$route.params.typeId === Defaults.apiSalesChannelTypeId;
            }

            return this.channel.typeId === Defaults.apiSalesChannelTypeId;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        isAgenticCommerce() {
            if (!this.channel) {
                return this.$route.params.typeId === Defaults.agenticCommerceTypeId;
            }

            return this.channel.typeId === Defaults.agenticCommerceTypeId;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        hasSwagAgenticCommercePlugin() {
            return !!Contena.Context.app.config.bundles?.SwagAgenticCommerce;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        showAgenticCommerceDeprecationBanner() {
            return this.isAgenticCommerce && !this.hasSwagAgenticCommercePlugin;
        },

        isProductExportChannel() {
            return this.isProductComparison || this.isAgenticCommerce;
        },

        channelDetailTabs() {
            const createRouteTab = (label, routeName, additionalProperties = {}) => {
                const route = {
                    name: routeName,
                    params: { id: this.$route.params.id },
                };

                return {
                    label: this.$t(label),
                    name: route.name,
                    ...additionalProperties,
                    onClick: () => {
                        void this.$router.push(route);
                    },
                };
            };

            const tabs = [createRouteTab('sw-sales-channel.detail.tabBase', 'sw.sales.channel.detail.base')];

            if (this.isAgenticCommerce && !this.isLoading) {
                tabs.push(
                    createRouteTab(
                        'sw-sales-channel.detail.blogExport.tabInsights',
                        'sw.sales.channel.detail.blogExportInsights',
                    ),
                );
            }

            if (this.isHeadless || this.isFrontend) {
                tabs.push(createRouteTab('sw-sales-channel.detail.tabProducts', 'sw.sales.channel.detail.blogs'));
            }

            if (!this.isProductExportChannel) {
                tabs.push(
                    createRouteTab('sw-sales-channel.detail.tabTheme', 'sw.sales.channel.detail.theme', {
                        disabled: this.isLoading,
                    }),
                );
            }

            if (this.isAgenticCommerce && !this.isLoading) {
                tabs.push(
                    createRouteTab(
                        'sw-sales-channel.detail.agenticCommerce.tabIntegration',
                        'sw.sales.channel.detail.agenticCommerceIntegration',
                    ),
                );
            }

            if (this.isProductExportChannel && !this.isLoading) {
                tabs.push(
                    createRouteTab(
                        'sw-sales-channel.detail.tabProductComparison',
                        'sw.sales.channel.detail.blogComparison',
                    ),
                );
            }

            if (this.isFrontend) {
                tabs.push(createRouteTab('sw-sales-channel.detail.tabAnalytics', 'sw.sales.channel.detail.analytics'));
            }

            if (this.isHeadless || this.isFrontend) {
                tabs.push(createRouteTab('sw-sales-channel.detail.tabAgenticFiles', 'sw.sales.channel.detail.agenticFiles'));
            }

            return tabs;
        },

        channelRepository() {
            // Sync keeps removed language mappings and the new languageId in one write, so the
            // default language validation sees the post-write state instead of rejecting the
            // removal of the previous default.
            return this.repositoryFactory.create('channel', null, { useSync: true });
        },

        channelAnalyticsRepository() {
            return this.repositoryFactory.create('channel_analytics');
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        blogExportRepository() {
            return this.repositoryFactory.create('blog_export');
        },

        frontendSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            return criteria.addFilter(
                Criteria.equalsAny('typeId', [Defaults.frontendSalesChannelTypeId, Defaults.apiSalesChannelTypeId]),
            );
        },

        tooltipSave() {
            if (!this.allowSaving) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.allowSaving,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        allowSaving() {
            return this.acl.can('channel.editor');
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        defaultAgenticCommerceExportConfig() {
            return [
                {
                    provider: 'open-ai',
                    systemConfigDomain: 'core.openAiProductExport',
                    titleSnippet: 'sw-sales-channel.detail.agenticCommerce.openAiSettingsTitle',
                    positionIdentifier: 'sw-sales-channel-detail-base-agentic-commerce-export-config-provider',
                },
                {
                    provider: 'google',
                    systemConfigDomain: 'core.googleProductExport',
                    titleSnippet: 'sw-sales-channel.detail.agenticCommerce.googleSettingsTitle',
                    positionIdentifier: 'sw-sales-channel-detail-base-agentic-commerce-export-config-provider',
                },
            ];
        },
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        },

        channel: {
            deep: true,
            handler() {
                this.clearResolvedRequiredSalesChannelFieldErrors();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        /** @deprecated tag:v6.8.0 - Will be removed */
        onClickInstallAgenticCommercePlugin() {
            if (this.$router.hasRoute('sw.extension.store.detail')) {
                this.$router.push({ name: 'sw.extension.store.detail', params: { id: '21761' } });
                return;
            }

            this.$router.push({ name: 'sw.extension.store.landing-page' });
        },

        createdComponent() {
            Contena.ExtensionAPI.publishData({
                id: 'sw-sales-channel-detail__channel',
                path: 'channel',
                scope: this,
            });
            this.loadEntityData();
            this.loadProductExportTemplates();
        },

        loadEntityData() {
            const hasRouteId = Boolean(this.$route.params.id);
            const hasRouteTypeId = Boolean(this.$route.params.typeId);

            if (!hasRouteId && hasRouteTypeId && this.channel?.id) {
                this.loadAgenticCommerceExportConfig();
                return;
            }

            if (!hasRouteId) {
                return;
            }

            if (hasRouteTypeId) {
                this.loadAgenticCommerceExportConfig();
                return;
            }

            if (this.channel) {
                this.channel = null;
            }

            this.loadSalesChannel();
            this.loadCustomFieldSets();
        },

        loadSalesChannel() {
            this.isLoading = true;
            this.channelRepository
                .get(this.$route.params.id.toLowerCase(), Context.api, this.getLoadSalesChannelCriteria())
                .then((entity) => {
                    this.channel = entity;

                    if (!this.channel.maintenanceIpAllowlist) {
                        this.channel.maintenanceIpAllowlist = [];
                    }

                    this.generateAccessUrl();
                    this.loadAgenticCommerceExportConfig();
                    this.detectCurrentTemplate();

                    this.isLoading = false;
                });
        },

        getLoadSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.getAssociation('paymentMethods').addSorting(Criteria.sort('distinguishableName', 'ASC'));
            criteria.getAssociation('shippingMethods').addSorting(Criteria.sort('name', 'ASC'));
            criteria.getAssociation('countries').addSorting(Criteria.sort('name', 'ASC'));
            criteria.getAssociation('currencies').addSorting(Criteria.sort('name', 'ASC'));
            criteria.addAssociation('domains');
            criteria
                .getAssociation('languages')
                .addSorting(Criteria.sort('name', 'ASC'))
                .addFilter(Criteria.equals('active', true));
            criteria.addAssociation('analytics');
            criteria.addAssociation('channelFiles');

            criteria.addAssociation('blogExports');
            criteria.addAssociation('blogExports.channelDomain.channel');

            criteria.getAssociation('domains.language').addSorting(Criteria.sort('name', 'ASC'));
            criteria.getAssociation('domains.snippetSet').addSorting(Criteria.sort('name', 'ASC'));
            criteria.addAssociation('domains.currency');
            criteria.addAssociation('domains.blogExports');

            return criteria;
        },

        onTemplateSelected(templateName) {
            if (this.blogComparison.templates === null || this.blogComparison.templates[templateName] === undefined) {
                return;
            }

            this.blogComparison.selectedTemplate = { ...this.blogComparison.templates[templateName] };
            const contentChanged = Object.keys(this.blogComparison.selectedTemplate).some((value) => {
                return this.blogExport[value] !== this.blogComparison.selectedTemplate[value];
            });

            if (!contentChanged) {
                this.blogComparison.templateName = templateName;
                return;
            }

            this.blogComparison.previousTemplateName = this.blogComparison.templateName;
            this.blogComparison.templateName = templateName;
            this.blogComparison.showTemplateModal = true;
        },

        onTemplateModalClose() {
            this.blogComparison.selectedTemplate = null;
            this.blogComparison.templateName = this.blogComparison.previousTemplateName ?? null;
            this.blogComparison.previousTemplateName = null;
            this.blogComparison.showTemplateModal = false;
        },

        onTemplateModalConfirm() {
            const selectedTemplate = this.blogComparison.selectedTemplate;

            Object.keys(selectedTemplate).forEach((key) => {
                if (key === 'providerName') {
                    this.blogExport.provider = selectedTemplate[key];
                    return;
                }

                this.blogExport[key] = selectedTemplate[key];
            });

            this.blogComparison.selectedTemplate = null;
            this.blogComparison.previousTemplateName = null;
            this.blogComparison.showTemplateModal = false;

            this.createNotificationInfo({
                message: this.$t('sw-sales-channel.detail.blogComparison.templates.message.template-applied-message'),
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('channel', false, 100).then((searchResult) => {
                this.customFieldSets = searchResult;
            });
        },

        generateAccessUrl() {
            if (!this.blogExport.channelDomain) {
                this.blogComparison.blogComparisonAccessUrl = '';
                return;
            }

            const domainUrl = this.blogExport.channelDomain.url.replace(/\/+$/g, '');
            this.blogComparison.blogComparisonAccessUrl = `${domainUrl}/store-api/blog-export/${this.blogExport.accessKey}/${this.blogExport.fileName}`;
        },

        loadProductExportTemplates() {
            this.blogComparison.templateOptions = Object.values(
                this.exportTemplateService.getProductExportTemplateRegistry(),
            );
            this.blogComparison.templates = this.exportTemplateService.getProductExportTemplateRegistry();
        },

        detectCurrentTemplate() {
            if (!this.blogComparison.templates || !this.blogExport) {
                return;
            }

            const matchedTemplate = this.blogComparison.templateOptions.find((template) => {
                return template.bodyTemplate !== undefined && template.bodyTemplate === this.blogExport.bodyTemplate;
            });

            if (matchedTemplate) {
                this.blogComparison.templateName = matchedTemplate.name;
            }
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        setInvalidFileName(invalidFileName) {
            this.blogComparison.invalidFileName = invalidFileName;
        },

        prepareSaveData() {
            const needsProductExport = this.isProductExportChannel;

            if (needsProductExport && !this.channel.blogExports.length) {
                this.channel.blogExports.add(this.blogExport);
            }

            return this.updateAnalytics();
        },

        async saveSalesChannel() {
            this.isLoading = true;
            this.isSaveSuccessful = false;
            const analyticsId = this.prepareSaveData();

            try {
                await this.channelRepository.save(this.channel, Context.api);

                if (analyticsId && !this.channel?.analytics?.trackingId) {
                    await this.channelAnalyticsRepository.delete(analyticsId, Context.api);
                }

                this.isSaveSuccessful = true;

                Contena.Utils.EventBus.emit('sw-sales-channel-detail-sales-channel-change');
            } catch (_error) {
                this.createNotificationError({
                    message: this.$t(
                        'sw-sales-channel.detail.messageSaveError',
                        {
                            name: this.channel.name || this.placeholder(this.channel, 'name'),
                        },
                        0,
                    ),
                });

                this.isLoading = false;

                return false;
            }

            this.isLoading = false;

            return true;
        },

        async onSave() {
            if (!this.validateRequiredSalesChannelFields()) {
                return;
            }

            if (!this.validateAgenticCommerceExportConfig()) {
                this.isLoading = false;
                return;
            }

            const saveSuccessful = await this.saveSalesChannel();

            if (!saveSuccessful) {
                return;
            }

            const configSaveSuccessful = await this.saveAgenticCommerceExportConfig();

            if (!configSaveSuccessful) {
                return;
            }

            this.loadEntityData();
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        validateAgenticCommerceExportConfig() {
            const requiredError = new ContenaError({ code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3' });
            const activeProvider = this.blogExport?.provider ?? this.defaultAgenticCommerceExportConfig[0]?.provider;
            let isValid = true;

            const activeEntries = this.agenticCommerceExportConfig.filter((entry) => {
                return entry.isLoaded && entry.provider === activeProvider;
            });

            for (const entry of activeEntries) {
                for (const el of entry.elements.filter((el) => el.config?.required && !entry.values[el.name])) {
                    entry.errors[el.name] = requiredError;
                    isValid = false;
                }
            }

            return isValid;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        async loadAgenticCommerceExportConfig() {
            this.agenticCommerceExportConfig = this.defaultAgenticCommerceExportConfig.map((configEntry) => {
                return {
                    ...configEntry,
                    elements: [],
                    values: {},
                    errors: {},
                    isLoading: false,
                    isLoaded: false,
                };
            });

            if (!this.isAgenticCommerce || !this.channel?.id) {
                return;
            }

            await Promise.all(
                this.agenticCommerceExportConfig.map(async (configEntry) => {
                    configEntry.isLoading = true;

                    try {
                        const [config, values] = await Promise.all([
                            this.systemConfigApiService.getConfig(configEntry.systemConfigDomain),
                            this.systemConfigApiService.getValues(configEntry.systemConfigDomain, this.channel.id),
                        ]);

                        configEntry.elements = config.flatMap((card) => card.elements);
                        configEntry.values = values;
                        configEntry.isLoaded = true;
                    } catch (_error) {
                        this.createNotificationError({
                            message: this.$t('sw-sales-channel.detail.messageAPIError'),
                        });
                    } finally {
                        configEntry.isLoading = false;
                    }
                }),
            );
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        async saveAgenticCommerceExportConfig() {
            if (!this.isAgenticCommerce || !this.channel?.id) {
                return true;
            }

            const loadedConfigs = this.agenticCommerceExportConfig.filter((configEntry) => configEntry.isLoaded);

            if (loadedConfigs.length === 0) {
                return true;
            }

            const mergedValues = loadedConfigs.reduce((accumulator, configEntry) => {
                return {
                    ...accumulator,
                    ...objectHelper.deepCopyObject(configEntry.values),
                };
            }, {});

            try {
                await this.systemConfigApiService.batchSave({
                    [this.channel.id]: mergedValues,
                });

                return true;
            } catch (_error) {
                this.createNotificationError({
                    message: this.$t('sw-sales-channel.detail.messageSaveError', {
                        name: this.channel.name || this.placeholder(this.channel, 'name'),
                    }),
                });

                return false;
            }
        },

        updateAnalytics() {
            const analyticsId = this.channel.analyticsId;
            if (analyticsId && !this.channel?.analytics?.trackingId) {
                this.channel.analyticsId = null;
                delete this.channel.analytics;
            }

            return analyticsId;
        },

        abortOnLanguageChange() {
            return this.channelRepository.hasChanges(this.channel);
        },

        async saveOnLanguageChange() {
            if (!this.validateRequiredSalesChannelFields()) {
                return Promise.reject();
            }

            const saveSuccessful = await this.saveSalesChannel();

            if (!saveSuccessful) {
                return Promise.reject();
            }

            return true;
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        validateRequiredSalesChannelFields() {
            if (!this.channel) {
                return false;
            }

            const missingFields = this.getRequiredSalesChannelFields().filter((fieldName) => {
                return this.isRequiredFieldEmpty(this.channel[fieldName]);
            });

            if (missingFields.length <= 0) {
                return true;
            }

            this.clearRequiredSalesChannelFieldErrors();
            missingFields.forEach((fieldName) => this.addRequiredSalesChannelFieldError(fieldName));

            this.createNotificationError({
                message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
            });

            this.isLoading = false;

            return false;
        },

        getRequiredSalesChannelFields() {
            if (this.isProductExportChannel) {
                return REQUIRED_BLOG_EXPORT_FIELDS;
            }

            return REQUIRED_BASE_FIELDS;
        },

        isRequiredFieldEmpty(value) {
            if (value === undefined || value === null) {
                return true;
            }

            if (typeof value === 'string') {
                return value.trim() === '';
            }

            return false;
        },

        addRequiredSalesChannelFieldError(fieldName) {
            Contena.Store.get('error').addApiError({
                expression: this.getRequiredSalesChannelFieldErrorExpression(fieldName),
                error: new ContenaError(EntityValidationService.createRequiredError(`/0/${fieldName}`)),
            });
        },

        clearRequiredSalesChannelFieldErrors() {
            this.getRequiredSalesChannelFields().forEach((fieldName) => {
                this.clearRequiredSalesChannelFieldError(fieldName);
            });
        },

        clearResolvedRequiredSalesChannelFieldErrors() {
            if (!this.channel?.id) {
                return;
            }

            this.getRequiredSalesChannelFields().forEach((fieldName) => {
                if (this.isRequiredFieldEmpty(this.channel[fieldName])) {
                    return;
                }

                this.clearRequiredSalesChannelFieldError(fieldName);
            });
        },

        clearRequiredSalesChannelFieldError(fieldName) {
            const error = this.getRequiredSalesChannelFieldError(fieldName);

            if (error?.code !== EntityValidationService.ERROR_CODE_REQUIRED) {
                return;
            }

            Contena.Store.get('error').removeApiError(this.getRequiredSalesChannelFieldErrorExpression(fieldName));
        },

        getRequiredSalesChannelFieldError(fieldName) {
            return Contena.Store.get('error').getApiErrorFromPath(this.getSalesChannelEntityName(), this.channel.id, [
                fieldName,
            ]);
        },

        getRequiredSalesChannelFieldErrorExpression(fieldName) {
            return `${this.getSalesChannelEntityName()}.${this.channel.id}.${fieldName}`;
        },

        getSalesChannelEntityName() {
            return typeof this.channel.getEntityName === 'function'
                ? this.channel.getEntityName()
                : 'channel';
        },
    },
};

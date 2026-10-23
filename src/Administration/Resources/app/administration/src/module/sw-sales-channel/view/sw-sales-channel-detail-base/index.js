/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-base.html.twig';
import './sw-sales-channel-detail-base.scss';

const { Component, Mixin, Context, Defaults } = Contena;
const { Criteria } = Contena.Data;
const domUtils = Contena.Utils.dom;
const objectHelper = Contena.Utils.object;
const ContenaError = Contena.Classes.ContenaError;
const utils = Contena.Utils;

const { mapPropertyErrors } = Component.getComponentHelper();

const FOREIGN_KEY_CONSTRAINT_VIOLATION_CODE = '1451';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: {
        channelService: 'channelService',
        blogExportService: 'blogExportService',
        repositoryFactory: 'repositoryFactory',
        knownIpsService: 'knownIpsService',
        acl: 'acl',
        /** @deprecated tag:v6.8.0 - Will be removed */
        swSalesChannelDetailGetAgenticCommerceExportConfig: {
            from: 'swSalesChannelDetailGetAgenticCommerceExportConfig',
            default: () => [],
        },
    },

    emits: [
        'template-selected',
        'template-modal-close',
        'template-modal-confirm',
        'invalid-file-name',
        'valid-file-name',
        'access-key-changed',
        'domain-changed',
    ],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    props: {
        channel: {
            required: true,
        },

        blogExport: {
            // type: Entity
            type: Object,
            required: true,
        },

        frontendSalesChannelCriteria: {
            type: Criteria,
            required: false,
        },

        customFieldSets: {
            type: Array,
            required: true,
        },

        isLoading: {
            type: Boolean,
            default: false,
        },

        blogComparisonAccessUrl: {
            type: String,
            default: '',
        },

        templateOptions: {
            type: Array,
            default: () => [],
        },

        showTemplateModal: {
            type: Boolean,
            default: false,
        },

        templateName: {
            type: String,
            default: null,
        },

        agenticCommerceExportConfig: {
            type: Array,
            required: false,
            default: () => [],
        },
    },

    data() {
        return {
            showDeleteModal: false,
            defaultSnippetSetId: '71a916e745114d72abafbfdc51cbd9d0',
            isLoadingDomains: false,
            deleteDomain: null,
            frontendDomains: [],
            selectedFrontendSalesChannel: null,
            invalidFileName: false,
            isFileNameChecking: false,
            disableGenerateByCronjob: false,
            knownIps: [],
            mainCategoriesCollection: null,
            footerCategoriesCollection: null,
            serviceCategoriesCollection: null,
            defaultMeasurementSystemId: null,
            defaultLengthUnitId: null,
            defaultWeightUnitId: null,
        };
    },

    computed: {
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        isFrontend() {
            return this.channel?.typeId === Defaults.frontendSalesChannelTypeId;
        },

        isDomainAware() {
            const domainAware = [Defaults.frontendSalesChannelTypeId, Defaults.apiSalesChannelTypeId];
            return domainAware.includes(this.channel.typeId);
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        isProductComparison() {
            return this.channel && this.channel.typeId === Defaults.blogComparisonTypeId;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        isAgenticCommerce() {
            return this.channel && this.channel.typeId === Defaults.agenticCommerceTypeId;
        },

        isProductExportChannel() {
            return this.isProductComparison || this.isAgenticCommerce;
        },

        isGoogleProductSearchTemplate() {
            return this.templateName === 'google-blog-search-de';
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        resolvedAgenticCommerceExportConfig() {
            let entries = [];

            if (Array.isArray(this.agenticCommerceExportConfig) && this.agenticCommerceExportConfig.length > 0) {
                entries = this.agenticCommerceExportConfig;
            } else if (typeof this.swSalesChannelDetailGetAgenticCommerceExportConfig === 'function') {
                entries = this.swSalesChannelDetailGetAgenticCommerceExportConfig() ?? [];
            }

            if (entries.length === 0) {
                return [];
            }

            const activeProvider = this.blogExport?.provider || entries[0]?.provider;
            const filtered = entries.filter((entry) => entry.provider === activeProvider);

            return filtered.length > 0 ? filtered : [entries[0]];
        },

        isHeadlessSalesChannel() {
            return this.channel?.typeId === Defaults.apiSalesChannelTypeId;
        },

        frontendSalesChannelDomainCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('channel');
            criteria.addFilter(
                Criteria.multi('or', [
                    Criteria.not('and', [Criteria.equals('channel.typeId', Defaults.apiSalesChannelTypeId)]),
                    Criteria.equals('isExternalFrontend', true),
                ]),
            );

            return criteria.addFilter(Criteria.equals('channelId', this.blogExport.frontendSalesChannelId));
        },

        frontendSalesChannelCurrencyCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('channels');

            return criteria.addFilter(Criteria.equals('channels.id', this.blogExport.frontendSalesChannelId));
        },

        paymentMethodCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('distinguishableName', 'ASC'));

            return criteria;
        },

        countryCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        languageCriteria() {
            const criteria = new Criteria();

            criteria.addSorting(Criteria.sort('name', 'ASC'));
            criteria.addFilter(Criteria.equals('active', true));

            return criteria;
        },

        disabledCountries() {
            return this.channel?.countries?.filter((country) => country.active === false) ?? [];
        },

        disabledCountryVariant() {
            return this.disabledCountries.find((country) => country.id === this.channel.countryId)
                ? 'attention'
                : 'info';
        },

        disabledPaymentMethods() {
            return this.channel?.paymentMethods?.filter((paymentMethod) => paymentMethod.active === false) ?? [];
        },

        disabledPaymentMethodVariant() {
            return this.disabledPaymentMethods.find(
                (paymentMethod) => paymentMethod.id === this.channel.paymentMethodId,
            )
                ? 'attention'
                : 'info';
        },

        disabledShippingMethods() {
            return this.channel?.shippingMethods?.filter((shippingMethod) => shippingMethod.active === false) ?? [];
        },

        disabledShippingMethodVariant() {
            return this.disabledShippingMethods.find(
                (shippingMethod) => shippingMethod.id === this.channel.shippingMethodId,
            )
                ? 'attention'
                : 'info';
        },

        unservedLanguages() {
            if (this.isProductExportChannel) {
                return [];
            }

            return (
                this.channel.languages?.filter(
                    (language) =>
                        (this.channel.domains?.filter((domain) => domain.languageId === language.id) || []).length ===
                        0,
                ) ?? []
            );
        },

        unservedLanguageVariant() {
            return this.unservedLanguages.find((language) => language.id === this.channel.languageId)
                ? 'attention'
                : 'info';
        },

        primaryUnservedLanguage() {
            return (
                this.unservedLanguages.find((language) => language.id === this.channel.languageId) ??
                this.unservedLanguages[0] ??
                null
            );
        },

        frontendDomainsLoaded() {
            return this.frontendDomains.length > 0;
        },

        domainRepository() {
            return this.repositoryFactory.create(this.channel.domains.entity, this.channel.domains.source);
        },

        globalDomainRepository() {
            return this.repositoryFactory.create('channel_domain');
        },

        blogExportRepository() {
            return this.repositoryFactory.create('blog_export');
        },

        mainNavigationCriteria() {
            const criteria = new Criteria(1, 10);
            return criteria.addFilter(Criteria.equalsAny('type', ['page', 'folder']));
        },

        getIntervalOptions() {
            return [
                {
                    id: 0,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.0'),
                },
                {
                    id: 120,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.120'),
                },
                {
                    id: 300,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.300'),
                },
                {
                    id: 600,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.600'),
                },
                {
                    id: 900,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.900'),
                },
                {
                    id: 1800,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.1800'),
                },
                {
                    id: 3600,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.3600'),
                },
                {
                    id: 7200,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.7200'),
                },
                {
                    id: 14400,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.14400'),
                },
                {
                    id: 28800,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.28800'),
                },
                {
                    id: 43200,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.43200'),
                },
                {
                    id: 86400,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.86400'),
                },
                {
                    id: 172800,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.172800'),
                },
                {
                    id: 259200,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.259200'),
                },
                {
                    id: 345600,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.345600'),
                },
                {
                    id: 432000,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.432000'),
                },
                {
                    id: 518400,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.518400'),
                },
                {
                    id: 604800,
                    label: this.$t('sw-sales-channel.detail.blogComparison.intervalLabels.604800'),
                },
            ];
        },

        getFileFormatOptions() {
            return [
                {
                    id: 'csv',
                    label: this.$t('sw-sales-channel.detail.blogComparison.fileFormatLabels.csv'),
                },
                {
                    id: 'xml',
                    label: this.$t('sw-sales-channel.detail.blogComparison.fileFormatLabels.xml'),
                },
            ];
        },

        getEncodingOptions() {
            return [
                {
                    id: 'ISO-8859-1',
                    label: 'ISO-8859-1',
                },
                {
                    id: 'UTF-8',
                    label: 'UTF-8',
                },
            ];
        },

        invalidFileNameError() {
            if (this.invalidFileName && !this.isFileNameChecking) {
                this.$emit('invalid-file-name');
                return new ContenaError({
                    code: 'DUPLICATED_BLOG_EXPORT_FILE_NAME',
                });
            }

            this.$emit('valid-file-name');
            return null;
        },

        helpTextTaxCalculation() {
            const link = {
                name: 'sw.settings.tax.index',
            };

            return this.$t(
                'sw-sales-channel.detail.helpTextTaxCalculation.label',
                {
                    link: `<sw-internal-link
                           :router-link=${JSON.stringify(link)}
                           :inline="true">
                           ${this.$t('sw-sales-channel.detail.helpTextTaxCalculation.linkText')}
                      </sw-internal-link>`,
                },
                0,
            );
        },

        taxCalculationTypeOptions() {
            return [
                {
                    value: 'horizontal',
                    name: this.$t('sw-sales-channel.detail.taxCalculation.horizontalName'),
                    description: this.$t('sw-sales-channel.detail.taxCalculation.horizontalDescription'),
                },
                {
                    value: 'vertical',
                    name: this.$t('sw-sales-channel.detail.taxCalculation.verticalName'),
                    description: this.$t('sw-sales-channel.detail.taxCalculation.verticalDescription'),
                },
            ];
        },

        maintenanceIpAllowlist: {
            get() {
                return this.channel.maintenanceIpAllowlist ?? [];
            },
            set(value) {
                this.channel.maintenanceIpAllowlist = value;
            },
        },

        ...mapPropertyErrors('channel', ['name', 'userGroupId', 'navigationCategoryId']),

        ...mapPropertyErrors('blogExport', [
            'blogStreamId',
            'encoding',
            'fileName',
            'fileFormat',
            'feedLabel',
            'frontendSalesChannelId',
            'channelDomainId',
            'currencyId',
        ]),

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        mainCategoryCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('id', this.channel.navigationCategoryId || null));

            return criteria;
        },

        footerCategoryCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('id', this.channel.footerCategoryId || null));

            return criteria;
        },

        serviceCategoryCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('id', this.channel.serviceCategoryId || null));

            return criteria;
        },

        mainCategories() {
            return this.mainCategoriesCollection ? this.mainCategoriesCollection : [];
        },

        footerCategories() {
            return this.footerCategoriesCollection ? this.footerCategoriesCollection : [];
        },

        serviceCategories() {
            return this.serviceCategoriesCollection ? this.serviceCategoriesCollection : [];
        },

        navigationCategoryPlaceholder() {
            return this.channel.navigationCategoryId ? '' : this.$t('sw-category.base.link.categoryPlaceholder');
        },

        footerCategoryPlaceholder() {
            return this.channel.footerCategoryId ? '' : this.$t('sw-category.base.link.categoryPlaceholder');
        },

        serviceCategoryPlaceholder() {
            return this.channel.serviceCategoryId ? '' : this.$t('sw-category.base.link.categoryPlaceholder');
        },

        businessTimeZoneOptions() {
            return Contena.Service('timezoneService').getTimezoneOptions();
        },

        channelFavoritesService() {
            return Contena.Service('channelFavorites');
        },

        currencyCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        shippingMethodCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        blogStreamCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('internal', false));
            return criteria;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Contena.Filter.getByName('date');
        },

        cliCommand() {
            if (this.channel.blogExports === undefined || this.channel.blogExports.length === 0) {
                return '';
            }

            return `php bin/console blog-export:generate ${this.channel.blogExports[0].frontendSalesChannelId} ${this.channel.blogExports[0].id}`;
        },

        templateSelectOptions() {
            return this.templateOptions
                .filter((exportTemplate) => {
                    if (this.isAgenticCommerce) {
                        return exportTemplate.channelTypeId === Defaults.agenticCommerceTypeId;
                    }

                    return !exportTemplate.channelTypeId;
                })
                .map((templateOption) => {
                    return {
                        value: templateOption.name,
                        id: templateOption.name,
                        label: this.$t(templateOption.translationKey),
                    };
                });
        },
    },

    watch: {
        'blogExport.fileName'() {
            this.onChangeFileName();
        },
        channel() {
            this.createCategoryCollections();
        },
    },

    created() {
        this.knownIpsService.getKnownIps().then((ips) => {
            this.knownIps = ips;
        });

        this.createCategoryCollections();
        this.defaultMeasurementSystemId = this.channel?.measurementSystemId;
        this.defaultLengthUnitId = this.channel?.lengthUnitId;
        this.defaultWeightUnitId = this.channel?.weightUnitId;
    },

    methods: {
        onFeedLabelInput(value) {
            if (value === '') {
                this.blogExport.feedLabel = null;

                return;
            }

            this.blogExport.feedLabel = value.toUpperCase();
        },

        onGenerateKeys() {
            this.channelService
                .generateKey()
                .then((response) => {
                    this.channel.accessKey = response.accessKey;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-sales-channel.detail.messageAPIError'),
                    });
                });
        },

        onGenerateProductExportKey(displaySaveNotification = true) {
            this.blogExportService
                .generateKey()
                .then((response) => {
                    this.blogExport.accessKey = response.accessKey;
                    this.$emit('access-key-changed');

                    if (displaySaveNotification) {
                        this.createNotificationInfo({
                            message: this.$t('sw-sales-channel.detail.blogComparison.messageAccessKeyChanged'),
                        });
                    }
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-sales-channel.detail.messageAPIError'),
                    });
                });
        },

        onToggleActive() {
            if (this.channel.active !== true || this.isProductExportChannel || this.isHeadlessSalesChannel) {
                return;
            }

            const criteria = new Criteria(1, 25);
            criteria.addAssociation('themes');

            this.channelRepository.get(this.$route.params.id.toLowerCase(), Context.api, criteria).then((entity) => {
                if (entity.extensions.themes !== undefined && entity.extensions.themes.length >= 1) {
                    return;
                }

                this.channel.active = false;
                this.createNotificationError({
                    message: this.$t(
                        'sw-sales-channel.detail.messageActivateWithoutThemeError',
                        {
                            name: this.channel.name || this.placeholder(this.channel, 'name'),
                        },
                        0,
                    ),
                });
            });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.showDeleteModal = false;

            this.$nextTick(async () => {
                const success = await this.deleteSalesChannel(this.channel.id);

                if (!success) {
                    return;
                }

                this.$router.push({ name: 'sw.dashboard.index' });
            });
        },

        deleteSalesChannel(channelId) {
            return this.channelRepository
                .delete(channelId, Context.api)
                .then(() => {
                    Contena.Utils.EventBus.emit('sw-sales-channel-detail-base-sales-channel-change');
                    this.channelFavoritesService.refresh();

                    return true;
                })
                .catch((error) => {
                    const current = error?.response?.data?.errors?.[0];
                    const assignment = this.extractFkInfo(current?.detail);

                    if (current?.code === FOREIGN_KEY_CONSTRAINT_VIOLATION_CODE && assignment) {
                        Contena.Store.get('error').resetApiErrors();
                        const translated = this.$t(`global.entities.${assignment}`, 0).toLowerCase();

                        this.createNotificationError({
                            message: this.$t('sw-sales-channel.detail.foreignKeyDelete', {
                                assignment: translated,
                            }),
                        });

                        return false;
                    }

                    throw error;
                });
        },

        extractFkInfo(detail = '') {
            if (!detail.includes('Integrity constraint violation: 1451')) return null;

            // matches e.g. "CONSTRAINT `fk.user."
            const match = detail.match(/CONSTRAINT `fk\.([^.]+)\./);

            if (!match) return null;

            // returns e.g. "user"
            return match[1];
        },

        async copyToClipboard() {
            try {
                await domUtils.copyStringToClipboard(this.channel.accessKey);
                this.createNotificationSuccess({
                    message: this.$t('global.sw-field.notification.notificationCopySuccessMessage'),
                });
            } catch (_err) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('global.sw-field.notification.notificationCopyFailureMessage'),
                });
            }
        },

        onFrontendSelectionChange(frontendSalesChannelId) {
            this.blogExport.channelDomainId = null;
            this.blogExport.channelDomain = null;
            this.loadFrontendDomains(frontendSalesChannelId);

            const criteria = new Criteria(1, 1);
            criteria.addAssociation('language');

            this.channelRepository.get(frontendSalesChannelId, Context.api, criteria).then((entity) => {
                if (!entity) {
                    return;
                }

                this.channel.languageId = entity.languageId;
                this.channel.currencyId = entity.currencyId;
                this.channel.paymentMethodId = entity.paymentMethodId;
                this.channel.shippingMethodId = entity.shippingMethodId;
                this.channel.countryId = entity.countryId;
                this.channel.navigationCategoryId = entity.navigationCategoryId;
                this.channel.navigationCategoryVersionId = entity.navigationCategoryVersionId;
                this.channel.userGroupId = entity.userGroupId;

                this.addLanguageToSalesChannel(entity.language);
            });
        },

        /**
         * The server-side ChannelValidator rejects a languageId that is
         * not part of the sales channel's language list, so the adopted
         * frontend language has to be added to the language association.
         */
        addLanguageToSalesChannel(language) {
            if (!language || this.channel.languages?.has(language.id) !== false) {
                return;
            }

            this.channel.languages.add(language);
        },

        onFrontendDomainSelectionChange(frontendSalesChannelDomainId) {
            this.globalDomainRepository.get(frontendSalesChannelDomainId).then((entity) => {
                this.blogExport.channelDomain = entity;
                this.blogExport.currencyId = entity.currencyId;
                this.$emit('domain-changed');
            });
        },

        loadFrontendDomains(frontendSalesChannelId) {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('channelId', frontendSalesChannelId));

            this.globalDomainRepository.search(criteria).then((searchResult) => {
                this.frontendDomains = searchResult;
            });
        },

        onChangeFileName() {
            this.isFileNameChecking = true;
            this.onChangeFileNameDebounce();
        },

        onChangeFileNameDebounce: utils.debounce(function executeChange() {
            if (!this.blogExport) {
                return;
            }

            if (typeof this.blogExport.fileName !== 'string' || this.blogExport.fileName.trim() === '') {
                this.invalidFileName = false;
                this.isFileNameChecking = false;
                return;
            }

            const criteria = new Criteria(1, 1);
            criteria.addFilter(
                Criteria.multi('AND', [
                    Criteria.equals('fileName', this.blogExport.fileName),
                    Criteria.not('AND', [Criteria.equals('id', this.blogExport.id)]),
                ]),
            );

            this.blogExportRepository
                .search(criteria)
                .then(({ total }) => {
                    this.invalidFileName = total > 0;
                    this.isFileNameChecking = false;
                })
                .catch(() => {
                    this.invalidFileName = true;
                    this.isFileNameChecking = false;
                });
        }, 500),

        changeInterval(value) {
            this.blogExport.interval = value;

            this.disableGenerateByCronjob = this.blogExport.interval === 0;
            this.blogExport.generateByCronjob = !this.disableGenerateByCronjob;
        },

        createCategoryCollections() {
            if (!this.channel) {
                return;
            }

            this.createCategoriesCollection(this.mainCategoryCriteria, 'mainCategoriesCollection');
            this.createCategoriesCollection(this.footerCategoryCriteria, 'footerCategoriesCollection');
            this.createCategoriesCollection(this.serviceCategoryCriteria, 'serviceCategoriesCollection');
        },

        async createCategoriesCollection(criteria, collectionName) {
            this[collectionName] = await this.categoryRepository.search(criteria, Contena.Context.api);
        },

        onMainSelectionAdd(item) {
            this.channel.navigationCategoryId = item.id;
        },

        onMainSelectionRemove() {
            this.channel.navigationCategoryId = null;
        },

        onFooterSelectionAdd(item) {
            this.channel.footerCategoryId = item.id;
        },

        onFooterSelectionRemove() {
            this.channel.footerCategoryId = null;
        },

        onServiceSelectionAdd(item) {
            this.channel.serviceCategoryId = item.id;
        },

        onServiceSelectionRemove() {
            this.channel.serviceCategoryId = null;
        },

        buildDisabledPaymentAlert(snippet, collection, property = 'name') {
            const route = { name: 'sw.settings.payment.overview' };
            const routeData = this.$router.resolve(route);

            const data = {
                separatedList: collection
                    .map((item) => `<span>${item.translated[property].replaceAll('|', '&vert;')}</span>`)
                    .join(', '),
                paymentSettingsLink: routeData.href,
            };

            return this.$t(snippet, data, collection.length);
        },

        buildDisabledShippingAlert(snippet, collection, property = 'name') {
            const data = {
                name: collection.first().translated[property].replaceAll('|', '&vert;'),
                addition:
                    collection.length > 2
                        ? this.$t('sw-sales-channel.detail.warningDisabledAddition', { amount: collection.length - 1 }, 1)
                        : collection.last().translated[property].replaceAll('|', '&vert;'),
            };

            return this.$t(snippet, data, collection.length);
        },

        buildUnservedLanguagesAlert(snippet, collection, property = 'name') {
            const data = {
                list: collection.map((item) => item[property]).join(', '),
            };

            return this.$t(snippet, data, collection.length);
        },

        onClickCreateDomainForUnservedLanguage() {
            if (typeof this.$refs.channelDomains?.onClickOpenCreateDomainModal !== 'function') {
                return;
            }

            this.$refs.channelDomains.onClickOpenCreateDomainModal({
                languageId: this.primaryUnservedLanguage?.id,
                currencyId: this.channel.currencyId,
            });

            this.$nextTick(() => {
                this.$refs.channelDomains?.$el?.scrollIntoView?.({
                    behavior: 'smooth',
                    block: 'center',
                });
            });
        },

        isFavorite() {
            return this.channelFavoritesService.isFavorite(this.channel.id);
        },

        validateMaintenanceIpCidr(term) {
            return utils.string.isValidIp(term) || utils.string.isValidCidr(term);
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        getAgenticCommerceExportElementBind(element) {
            const bind = objectHelper.deepCopyObject(element);

            if (['single-select', 'multi-select'].includes(bind.type)) {
                bind.config.labelProperty = 'name';
                bind.config.valueProperty = 'id';
            }

            if (bind.type === 'text-editor') {
                bind.config.componentName = 'sw-text-editor';
            }

            return bind;
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        getAgenticCommerceExportCardTitle(configEntry) {
            if (configEntry?.titleSnippet) {
                return this.$t(configEntry.titleSnippet);
            }

            return configEntry?.provider ?? '';
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        getAgenticCommerceExportCardPositionIdentifier(configEntry) {
            if (configEntry?.positionIdentifier) {
                return configEntry.positionIdentifier;
            }
            return 'sw-sales-channel-detail-base-agentic-commerce-export-config-provider';
        },

        /** @deprecated tag:v6.8.0 - Will be removed */
        onAgenticCommerceExportFieldUpdate(configEntry, fieldName, value) {
            configEntry.values[fieldName] = value;

            if (configEntry.errors?.[fieldName]) {
                delete configEntry.errors[fieldName];
            }
        },

        onTrackingConfigChange(config) {
            this.channel.configuration = config;
        },
    },
};

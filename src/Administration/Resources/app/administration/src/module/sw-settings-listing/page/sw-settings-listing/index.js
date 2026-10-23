/**
 * @sw-package inventory
 */
import template from './sw-settings-listing.html.twig';
import './sw-settings-listing.scss';

const { Criteria } = Contena.Data;
const { ContenaError } = Contena.Classes;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService'],

    mixins: ['notification', 'sw-inline-snippet'],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            blogSortingOptions: [],
            searchResultSortingOptions: [],
            sortingOptionsGridLimit: 10,
            sortingOptionsGridPage: 1,
            modalVisible: false,
            toBeDeletedProductSortingOption: null,
            blogSortingOptionsSearchTerm: null,
            isProductSortingOptionsCardLoading: false,
            isDefaultSalesChannelLoading: false,
            customFields: [],
            hasDefaultSortingError: false,
            hasDefaultSearchResultSortingError: false,
        };
    },

    computed: {
        blogSortingOptionRepository() {
            return this.repositoryFactory.create('blog_sorting');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        systemConfigRepository() {
            return this.repositoryFactory.create('system_config');
        },

        blogSortingsOptionsCriteria() {
            const criteria = new Criteria(this.sortingOptionsGridPage, this.sortingOptionsGridLimit);

            criteria.addSorting(Criteria.sort('priority', 'DESC'));

            return criteria;
        },

        searchResultSortingOptionCriteria() {
            const criteria = new Criteria(this.sortingOptionsGridPage, this.sortingOptionsGridLimit);

            criteria.addSorting(Criteria.sort('priority', 'DESC'));

            return criteria;
        },

        blogSortingOptionsSearchCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('priority', 'DESC'));

            criteria.addFilter(Criteria.contains('label', this.blogSortingOptionsSearchTerm));

            return criteria;
        },

        sortingOptionsGridTotal() {
            return this.blogSortingOptions.total;
        },

        customFieldCriteria() {
            return new Criteria(1, 25);
        },

        blogSortingOptionColumns() {
            return [
                {
                    property: 'label',
                    routerLink: 'sw.settings.listing.edit',
                    label: this.$t('sw-settings-listing.index.blogSorting.grid.header.name'),
                },
                {
                    property: 'criteria',
                    label: this.$t('sw-settings-listing.index.blogSorting.grid.header.criteria'),
                    multiLine: true,
                },
                {
                    property: 'priority',
                    inlineEdit: 'number',
                    label: this.$t('sw-settings-listing.index.blogSorting.grid.header.priority'),
                },
            ];
        },

        /** @deprecated tag:v6.8.0 - Will be removed, use Contena.Filter.getByName('asset') instead. */
        assetFilter() {
            return Contena.Filter.getByName('asset');
        },

        channelDefaultSortingError() {
            const code = this.$refs.systemConfig.isNotDefaultSalesChannel
                ? 'PARENT_MUST_NOT_BE_EMPTY'
                : 'c1051bb4-d103-4f74-8988-acbcafc7fdc3';

            return new ContenaError({
                code,
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        createdComponent() {
            this.fetchProductSortingOptions();
            this.fetchSearchResultSortingOptions();
            this.fetchCustomFields();
        },

        fetchProductSortingOptions() {
            this.isProductSortingOptionsCardLoading = true;

            this.blogSortingOptionRepository.search(this.blogSortingsOptionsCriteria).then((response) => {
                this.blogSortingOptions = response;

                this.isProductSortingOptionsCardLoading = false;
            });
        },

        fetchSearchResultSortingOptions() {
            this.isProductSortingOptionsCardLoading = true;

            this.blogSortingOptionRepository.search(this.searchResultSortingOptionCriteria).then((response) => {
                this.searchResultSortingOptions = response;

                this.isProductSortingOptionsCardLoading = false;
            });
        },

        fetchCustomFields() {
            this.customFieldRepository.search(this.customFieldCriteria).then((response) => {
                this.customFields = response;
            });
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.hasDefaultSortingError = false;
            this.hasDefaultSearchResultSortingError = false;

            const validateDefaultSortingConfigs = new Promise((resolve, reject) => {
                if (!this.$refs.systemConfig.actualConfigData.null['core.listing.defaultSorting']) {
                    this.hasDefaultSortingError = true;
                }

                if (!this.$refs.systemConfig.actualConfigData.null['core.listing.defaultSearchResultSorting']) {
                    this.hasDefaultSearchResultSortingError = true;
                }

                if (this.hasDefaultSortingError || this.hasDefaultSearchResultSortingError) {
                    /**
                     * display both errors in the UI
                     * instead of just one using reject() right after the first error
                     */
                    reject(new Error(this.$t('sw-settings-listing.general.messageSaveDefaultValuesEmpty')));
                }

                resolve();
            });

            return validateDefaultSortingConfigs
                .then(async () => {
                    const saveSalesChannelConfig = this.$refs.systemConfig.saveAll();

                    this.setDefaultSortingActive();

                    const saveProductSortingOptions = this.saveProductSortingOptions();

                    const saveSearchResultSortingOptions = this.saveSearchResultSortingOptions();

                    const saveSalesChannelVisibilityConfig =
                        this.$refs.defaultSalesChannelCard.saveSalesChannelVisibilityConfig();

                    return Promise.all([
                        saveSalesChannelConfig,
                        saveProductSortingOptions,
                        saveSearchResultSortingOptions,
                        saveSalesChannelVisibilityConfig,
                    ]);
                })
                .then(() => {
                    this.isSaveSuccessful = true;

                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-listing.general.messageSaveSuccess'),
                    });
                })
                .catch((e) => {
                    const options = {
                        message: e?.response?.data?.errors[0]?.detail || e?.message || 'Unknown error',
                    };
                    this.createNotificationError({
                        message: this.$t('sw-settings-listing.general.messageSaveError', options),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        saveProductSortingOptions() {
            return this.blogSortingOptionRepository.saveAll(this.blogSortingOptions);
        },

        saveSearchResultSortingOptions() {
            return this.blogSortingOptionRepository.saveAll(this.searchResultSortingOptions);
        },

        onDeleteProductSorting(item) {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('configurationKey', 'core.listing.defaultSorting'));
            criteria.addFilter(Criteria.equals('configurationValue', item.id));

            this.systemConfigRepository.search(criteria).then((result) => {
                const actualConfigData = {};
                result.forEach((entry) => {
                    actualConfigData[entry.channelId] = {
                        'core.listing.defaultSorting': null,
                    };
                });
                // cannot delete the entries directly via the systemConfigRepository, because Rufus blocks write access
                this.systemConfigApiService.batchSave(actualConfigData);
            });

            Object.keys(this.$refs.systemConfig.actualConfigData).forEach((id) => {
                const configData = this.$refs.systemConfig.actualConfigData[id];
                if (configData && configData['core.listing.defaultSorting'] === item.id) {
                    configData['core.listing.defaultSorting'] = null;
                }
            });

            // closes modal
            this.toBeDeletedProductSortingOption = null;

            this.blogSortingOptionRepository
                .delete(item.id)
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-listing.index.blogSorting.messageDeleteError'),
                    });
                })
                .finally(() => {
                    this.fetchProductSortingOptions();
                    this.checkForPagination();
                });
        },

        /**
         * check, if we need to paginate back, when deleting the last sorting option on a page
         */
        checkForPagination() {
            if (this.sortingOptionsGridPage !== 1) {
                const newTotal = this.blogSortingOptions.total - 1;

                if (this.sortingOptionsGridPage * this.sortingOptionsGridLimit >= newTotal) {
                    this.onPageChange({
                        page: this.sortingOptionsGridPage - 1,
                        limit: this.sortingOptionsGridLimit,
                    });
                }
            }
        },

        onPageChange({ page = 1, limit = 10 }) {
            this.sortingOptionsGridPage = page;
            this.sortingOptionsGridLimit = limit;

            this.fetchProductSortingOptions();
        },

        onEditProductSortingOption(blogSortingId) {
            this.$router.push({
                name: 'sw.settings.listing.edit',
                params: { id: blogSortingId },
            });
        },

        formatProductSortingOptionField(fields) {
            if (!Array.isArray(fields)) {
                return '';
            }

            const labels = fields.map((currentField) => {
                if (this.isItemACustomField(currentField.field)) {
                    return this.getCustomFieldLabelByCriteriaName(currentField.field);
                }

                return this.$t(`sw-settings-listing.general.blogSortingCriteriaGrid.options.label.${currentField.field}`);
            });

            return labels.join(', ');
        },

        getCustomFieldLabelByCriteriaName(criteriaName) {
            const technicalName = this.stripCustomFieldPath(criteriaName);
            const customField = this.getCustomFieldByName(technicalName);

            const inlineSnippet = this.getInlineSnippet(customField.config.label);

            if (inlineSnippet === null) {
                return technicalName;
            }

            return inlineSnippet;
        },

        getCustomFieldByName(technicalName) {
            return this.customFields.find((currentCustomField) => {
                return currentCustomField.name === technicalName;
            });
        },

        onAddNewProductSortingOption() {
            this.$router.push({ name: 'sw.settings.listing.create' });
        },

        onSearchProductSortingOptions() {
            const searchTerm = this.blogSortingOptionsSearchTerm;

            if (!searchTerm) {
                this.fetchProductSortingOptions();
                return;
            }

            this.blogSortingOptionRepository.search(this.blogSortingOptionsSearchCriteria).then((response) => {
                this.blogSortingOptions = response;
            });
        },

        onSaveProductSortingOptionInlineEdit(newProductSortingOption) {
            const indexOfOldProductSortingOption = this.blogSortingOptions.findIndex((currentElement) => {
                return currentElement.id === newProductSortingOption.id;
            });

            this.blogSortingOptions[indexOfOldProductSortingOption] = newProductSortingOption;

            this.onSave().then(() => {
                this.fetchProductSortingOptions();
            });
        },

        isItemACustomField(fieldName) {
            const strippedFieldName = this.stripCustomFieldPath(fieldName);

            return this.customFields.some((currentCustomField) => {
                return currentCustomField.name === strippedFieldName;
            });
        },

        getCustomFieldById(id) {
            const customField = this.customFields.find((currentCustomField) => {
                return currentCustomField.id === id;
            });

            return customField.name;
        },

        stripCustomFieldPath(fieldName) {
            return fieldName.replace(/customFields\./, '');
        },

        onChangeLanguage() {
            this.fetchProductSortingOptions();
        },

        setDefaultSortingActive() {
            const defaultSortingId = this.$refs.systemConfig.actualConfigData.null['core.listing.defaultSorting'];

            if (defaultSortingId) {
                Object.entries(this.blogSortingOptions).forEach(([, blogSorting]) => {
                    if (blogSorting.id === defaultSortingId) {
                        blogSorting.active = true;
                    }
                });
            }
        },

        isItemDefaultSorting(sortingId) {
            const systemSettingAvailable = !!this.$refs.systemConfig.actualConfigData.null;

            if (!systemSettingAvailable) {
                return null;
            }

            return sortingId === this.$refs.systemConfig.actualConfigData.null['core.listing.defaultSorting'];
        },

        onLoadingChanged(loading) {
            this.isLoading = loading;
        },

        allowProductSortingOptionDelete(blogSortingOption) {
            return !(blogSortingOption.locked || this.isItemDefaultSorting(blogSortingOption.id));
        },

        getProductSortingOptionDeleteTooltip(item) {
            const state = item.locked ? 'locked' : 'default';
            const snippetKey = `sw-settings-listing.index.blogSorting.grid.deleteTooltip.${state}`;

            return {
                showDelay: 300,
                message: this.$t(snippetKey),
                disabled: this.allowProductSortingOptionDelete(item),
            };
        },
    },
};

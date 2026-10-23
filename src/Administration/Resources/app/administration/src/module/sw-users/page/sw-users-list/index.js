import template from './sw-user-list.html.twig';
import './sw-user-list.scss';

/**
 * @sw-package checkout
 */

const { Mixin, Context } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('salutation'), Mixin.getByName('listing')],

    data() {
        return {
            users: null,
            sortBy: 'createdAt',
            naturalSorting: false,
            sortDirection: 'DESC',
            isLoading: false,
            showDeleteModal: false,
            /**
             * @deprecated tag:v6.8.0 - will be removed without replacement
             */
            filterLoading: false,
            /**
             * @deprecated tag:v6.8.0 - will be removed without replacement
             */
            availableAffiliateCodes: [],
            /**
             * @deprecated tag:v6.8.0 - will be removed without replacement
             */
            availableCampaignCodes: [],
            filterCriteria: [],
            defaultFilters: [
                'user-number-filter',
                'affiliate-code-filter',
                'campaign-code-filter',
                'user-group-request-filter',
                'salutation-filter',
                'account-status-filter',
                'default-payment-method-filter',
                'group-filter',
                'billing-address-country-filter',
                'shipping-address-country-filter',
                'tags-filter',
            ],
            storeKey: 'grid.filter.user',
            activeFilterNumber: 0,
            searchConfigEntity: 'user',
            showBulkEditModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        hasActiveSearchOrFilter() {
            return this.activeFilterNumber > 0 || this.isValidTerm(this.term);
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        userColumns() {
            return this.getCustomerColumns();
        },

        defaultCriteria() {
            const defaultCriteria = new Criteria(this.page, this.limit);
            this.naturalSorting = this.sortBy === 'userNumber';

            defaultCriteria.setTerm(this.term);

            this.sortBy.split(',').forEach((sortBy) => {
                defaultCriteria.addSorting(Criteria.sort(sortBy, this.sortDirection, this.naturalSorting));
            });

            defaultCriteria
                .addAssociation('defaultBillingAddress')
                .addAssociation('group')
                .addAssociation('requestedGroup')
                .addAssociation('boundSalesChannel');

            this.filterCriteria.forEach((filter) => {
                defaultCriteria.addFilter(filter);
            });

            return defaultCriteria;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        filterSelectCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(
                Criteria.not('AND', [Criteria.equals('affiliateCode', null), Criteria.equals('campaignCode', null)]),
            );
            criteria.addAggregation(Criteria.terms('affiliateCodes', 'affiliateCode', null, null, null));
            criteria.addAggregation(Criteria.terms('campaignCodes', 'campaignCode', null, null, null));

            return criteria;
        },

        listFilterOptions() {
            const options = {
                'user-number-filter': {
                    property: 'userNumber',
                    type: 'string-filter',
                    label: this.$t('sw-user.filter.userNumber.label'),
                    placeholder: this.$t('sw-user.filter.userNumber.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    criteriaFilterType: 'equals',
                },
                'affiliate-code-filter': {
                    property: 'affiliateCode',
                    type: 'string-filter',
                    label: this.$t('sw-user.filter.affiliateCode.label'),
                    placeholder: this.$t('sw-user.filter.affiliateCode.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                },
                'campaign-code-filter': {
                    property: 'campaignCode',
                    type: 'string-filter',
                    label: this.$t('sw-user.filter.campaignCode.label'),
                    placeholder: this.$t('sw-user.filter.campaignCode.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                },
                'user-group-request-filter': {
                    property: 'requestedGroupId',
                    type: 'existence-filter',
                    label: this.$t('sw-user.filter.userGroupRequest.label'),
                    placeholder: this.$t('sw-user.filter.userGroupRequest.placeholder'),
                    optionHasCriteria: this.$t('sw-user.filter.userGroupRequest.textHasCriteria'),
                    optionNoCriteria: this.$t('sw-user.filter.userGroupRequest.textNoCriteria'),
                },
                'salutation-filter': {
                    property: 'salutation',
                    label: this.$t('sw-user.filter.salutation.label'),
                    placeholder: this.$t('sw-user.filter.salutation.placeholder'),
                    labelProperty: 'displayName',
                },
                'account-status-filter': {
                    property: 'active',
                    label: this.$t('sw-user.filter.status.label'),
                    placeholder: this.$t('sw-user.filter.status.placeholder'),
                },
                'group-filter': {
                    property: 'group',
                    label: this.$t('sw-user.filter.userGroup.label'),
                    placeholder: this.$t('sw-user.filter.userGroup.placeholder'),
                },
                'billing-address-country-filter': {
                    property: 'defaultBillingAddress.country',
                    label: this.$t('sw-user.filter.billingCountry.label'),
                    placeholder: this.$t('sw-user.filter.billingCountry.placeholder'),
                },
                'shipping-address-country-filter': {
                    property: 'defaultShippingAddress.country',
                    label: this.$t('sw-user.filter.shippingCountry.label'),
                    placeholder: this.$t('sw-user.filter.shippingCountry.placeholder'),
                },
                'tags-filter': {
                    property: 'tags',
                    label: this.$t('sw-user.filter.tags.label'),
                    placeholder: this.$t('sw-user.filter.tags.placeholder'),
                },
            };

            return options;
        },

        listFilters() {
            return this.filterFactory.create('user', this.listFilterOptions);
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },

        emailIdnFilter() {
            return Contena.Filter.getByName('decode-idn-email');
        },

        adminEsEnable() {
            if (!Contena.Feature.isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                return false;
            }

            return Context.app.adminEsEnable ?? false;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        createdComponent() {
            return Promise.resolve();
        },

        onInlineEditSave(promise, user) {
            promise
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-user.detail.messageSaveSuccess', { name: this.salutation(user) }, 0),
                    });
                })
                .catch(() => {
                    this.getList();
                    this.createNotificationError({
                        message: this.$t('sw-user.detail.messageSaveError'),
                    });
                });
        },

        async getList() {
            this.isLoading = true;

            const criteria = await Contena.Service('filterService').mergeWithStoredFilters(
                this.storeKey,
                this.defaultCriteria,
            );

            let newCriteria;
            if (this.adminEsEnable) {
                newCriteria = criteria;
                newCriteria.setTerm(this.term);
            } else {
                newCriteria = await this.addQueryScores(this.term, criteria);
            }

            this.activeFilterNumber = criteria.filters.length;

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            if (this.freshSearchTerm) {
                newCriteria.resetSorting();
            }

            try {
                const items = await this.userRepository.search(newCriteria);

                this.total = items.total;
                this.users = items;
                this.isLoading = false;
                this.selection = {};
            } catch {
                this.isLoading = false;
            }
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.userRepository
                .delete(id)
                .then(() => {
                    this.getList();
                })
                .catch((errorResponse) => {
                    const errors = errorResponse?.response?.data?.errors;

                    if (Array.isArray(errors) && errors.length > 0) {
                        errors.forEach((error) => {
                            this.createNotificationError({
                                title: error.title,
                                message: error.detail,
                            });
                        });
                    } else {
                        this.createNotificationError({
                            title: this.$t('global.default.error'),
                            message: this.$t('global.notification.unspecifiedSaveErrorMessage'),
                        });
                    }
                });
        },

        async onChangeLanguage() {
            await this.createdComponent();
            await this.getList();
        },

        getCustomerColumns() {
            const columns = [
                {
                    property: 'firstName',
                    dataIndex: 'lastName,firstName',
                    inlineEdit: 'string',
                    label: 'sw-user.list.columnName',
                    routerLink: 'sw.user.detail',
                    width: '250px',
                    allowResize: true,
                    primary: true,
                    useCustomSort: true,
                },
                {
                    property: 'company',
                    label: 'sw-user.list.columnCompany',
                    allowResize: true,
                    visible: false,
                    useCustomSort: true,
                },
                {
                    property: 'defaultBillingAddress.street',
                    label: 'sw-user.list.columnStreet',
                    allowResize: true,
                    useCustomSort: true,
                },
                {
                    property: 'defaultBillingAddress.zipcode',
                    label: 'sw-user.list.columnZip',
                    align: 'right',
                    allowResize: true,
                    useCustomSort: true,
                },
                {
                    property: 'defaultBillingAddress.city',
                    label: 'sw-user.list.columnCity',
                    allowResize: true,
                    useCustomSort: true,
                },
                {
                    property: 'userNumber',
                    dataIndex: 'userNumber',
                    naturalSorting: true,
                    label: 'sw-user.list.columnCustomerNumber',
                    allowResize: true,
                    inlineEdit: 'string',
                    align: 'right',
                    useCustomSort: true,
                },
                {
                    property: 'group',
                    dataIndex: 'group.name',
                    naturalSorting: true,
                    label: 'sw-user.list.columnGroup',
                    allowResize: true,
                    inlineEdit: 'string',
                    align: 'right',
                    useCustomSort: true,
                },
                {
                    property: 'email',
                    inlineEdit: 'string',
                    label: 'sw-user.list.columnEmail',
                    allowResize: true,
                    useCustomSort: true,
                },
                {
                    property: 'affiliateCode',
                    inlineEdit: 'string',
                    label: 'sw-user.list.columnAffiliateCode',
                    allowResize: true,
                    visible: false,
                    useCustomSort: true,
                },
                {
                    property: 'campaignCode',
                    inlineEdit: 'string',
                    label: 'sw-user.list.columnCampaignCode',
                    allowResize: true,
                    visible: false,
                    useCustomSort: true,
                },
                {
                    property: 'boundSalesChannelId',
                    label: 'sw-user.list.columnBoundSalesChannel',
                    allowResize: true,
                    visible: false,
                    useCustomSort: true,
                },
                {
                    property: 'active',
                    inlineEdit: 'boolean',
                    label: 'sw-user.list.columnActive',
                    allowResize: true,
                    visible: false,
                    useCustomSort: true,
                },
                {
                    property: 'createdAt',
                    label: 'sw-user.list.columnCreatedAt',
                    allowResize: true,
                },
            ];

            return columns;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        loadFilterValues() {
            this.filterLoading = true;

            return this.userRepository
                .search(this.filterSelectCriteria)
                .then(({ aggregations }) => {
                    this.availableAffiliateCodes = aggregations?.affiliateCodes?.buckets ?? [];
                    this.availableCampaignCodes = aggregations?.campaignCodes?.buckets ?? [];
                    this.filterLoading = false;

                    return aggregations;
                })
                .catch(() => {
                    this.filterLoading = false;
                });
        },

        /**
         * @deprecated tag:v6.8.0 - Use listing mixin implementation directly
         */
        updateCriteria(criteria) {
            // Delegate to listing mixin implementation
            return Mixin.getByName('listing').methods.updateCriteria.call(this, criteria);
        },

        async onBulkEditItems() {
            await this.$nextTick();
            this.$router.push({ name: 'sw.bulk.edit.user' });
        },

        onBulkEditModalOpen() {
            this.showBulkEditModal = true;
        },

        onBulkEditModalClose() {
            this.showBulkEditModal = false;
        },
    },
};

import template from './sw-order-list.html.twig';
import './sw-order-list.scss';

/**
 * @sw-package checkout
 */

const { Mixin, Context } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'stateStyleDataProviderService',
        'acl',
        'filterFactory',
        'feature',
    ],

    mixins: [Mixin.getByName('listing')],

    data() {
        return {
            orders: [],
            sortBy: 'orderDateTime',
            sortDirection: 'DESC',
            isLoading: false,
            /**
             * @deprecated tag:v6.8.0 - will be removed without replacement
             */
            filterLoading: false,
            showDeleteModal: false,
            filterCriteria: [],
            defaultFilters: [
                'order-number-filter',
                'user-number-filter',
                'affiliate-code-filter',
                'campaign-code-filter',
                'promotion-code-filter',
                'document-filter',
                'order-date-filter',
                'order-value-filter',
                'status-filter',
                'payment-status-filter',
                'delivery-status-filter',
                'payment-method-filter',
                'shipping-method-filter',
                'sales-channel-filter',
                'billing-country-filter',
                'user-group-filter',
                'shipping-country-filter',
                'user-group-filter',
                'tag-filter',
                'line-item-filter',
            ],
            storeKey: 'grid.filter.order',
            activeFilterNumber: 0,
            showBulkEditModal: false,
            searchConfigEntity: 'order',
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

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        orderColumns() {
            return this.getOrderColumns();
        },

        orderCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);

            this.sortBy.split(',').forEach((sortBy) => {
                criteria.addSorting(Criteria.sort(sortBy, this.sortDirection));
            });

            this.filterCriteria.forEach((filter) => {
                criteria.addFilter(filter);
            });

            criteria.addAssociation('billingAddress');
            criteria.addAssociation('channel');
            criteria.addAssociation('orderCustomer');
            criteria.addAssociation('currency');
            criteria.addAssociation('documents');
            criteria.addAssociation('stateMachineState');
            criteria.addAssociation('primaryOrderTransaction.stateMachineState');
            criteria.addAssociation('primaryOrderDelivery.stateMachineState');
            criteria.addAssociation('primaryOrderDelivery.shippingOrderAddress');

            if (!Contena.Feature.isActive('v6.8.0.0')) {
                criteria.addAssociation('addresses');

                criteria
                    .getAssociation('transactions')
                    .addAssociation('stateMachineState')
                    .addSorting(Criteria.sort('createdAt'));

                criteria
                    .addAssociation('primaryOrderTransaction.paymentMethod')
                    .addAssociation('primaryOrderDelivery.shippingMethod')
                    .addAssociation('primaryOrderDelivery.shippingOrderAddress.country');

                criteria
                    .getAssociation('deliveries')
                    .addAssociation('stateMachineState')
                    .addAssociation('shippingOrderAddress')
                    .addAssociation('shippingMethod')
                    .addSorting(Criteria.sort('shippingCosts.unitPrice', 'DESC'))
                    .addSorting(Criteria.sort('id', 'ASC'));
            }

            return criteria;
        },

        channelCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name'));

            return criteria;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed without replacement
         */
        filterSelectCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAggregation(Criteria.terms('affiliateCodes', 'affiliateCode', null, null, null));
            criteria.addAggregation(Criteria.terms('campaignCodes', 'campaignCode', null, null, null));
            criteria.addAggregation(Criteria.terms('promotionCodes', 'lineItems.payload.code', null, null, null));

            return criteria;
        },

        listFilterOptions() {
            return {
                'order-number-filter': {
                    property: 'orderNumber',
                    type: 'string-filter',
                    label: this.$t('sw-order.filters.orderNumberFilter.label'),
                    placeholder: this.$t('sw-order.filters.orderNumberFilter.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    criteriaFilterType: 'equalsAny',
                },
                'sales-channel-filter': {
                    property: 'channel',
                    label: this.$t('sw-order.filters.channelFilter.label'),
                    placeholder: this.$t('sw-order.filters.channelFilter.placeholder'),
                    criteria: this.channelCriteria,
                },
                'order-value-filter': {
                    property: 'amountTotal',
                    type: 'number-filter',
                    label: this.$t('sw-order.filters.orderValueFilter.label'),
                    fromFieldLabel: null,
                    toFieldLabel: null,
                    fromPlaceholder: this.$t('global.default.from'),
                    toPlaceholder: this.$t('global.default.to'),
                },
                'payment-status-filter': {
                    property: 'primaryOrderTransaction.stateMachineState',
                    criteria: this.getStatusCriteria('order_transaction.state'),
                    label: this.$t('sw-order.filters.paymentStatusFilter.label'),
                    placeholder: this.$t('sw-order.filters.paymentStatusFilter.placeholder'),
                },
                'delivery-status-filter': {
                    property: 'primaryOrderDelivery.stateMachineState',
                    criteria: this.getStatusCriteria('order_delivery.state'),
                    label: this.$t('sw-order.filters.deliveryStatusFilter.label'),
                    placeholder: this.$t('sw-order.filters.deliveryStatusFilter.placeholder'),
                },
                'status-filter': {
                    property: 'stateMachineState',
                    criteria: this.getStatusCriteria('order.state'),
                    label: this.$t('sw-order.filters.statusFilter.label'),
                    placeholder: this.$t('sw-order.filters.statusFilter.placeholder'),
                },
                'order-date-filter': {
                    property: 'orderDateTime',
                    label: this.$t('sw-order.filters.orderDateFilter.label'),
                    dateType: 'date',
                    fromFieldLabel: null,
                    toFieldLabel: null,
                    showTimeframe: true,
                },
                'user-number-filter': {
                    property: 'orderCustomer.user.userNumber',
                    type: 'string-filter',
                    label: this.$t('sw-order.filters.userNumberFilter.label'),
                    placeholder: this.$t('sw-order.filters.userNumberFilter.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    criteriaFilterType: 'equals',
                },
                'tag-filter': {
                    property: 'tags',
                    label: this.$t('sw-order.filters.tagFilter.label'),
                    placeholder: this.$t('sw-order.filters.tagFilter.placeholder'),
                },
                'affiliate-code-filter': {
                    property: 'affiliateCode',
                    type: 'string-filter',
                    label: this.$t('sw-order.filters.affiliateCodeFilter.label'),
                    placeholder: this.$t('sw-order.filters.affiliateCodeFilter.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                },
                'campaign-code-filter': {
                    property: 'campaignCode',
                    type: 'string-filter',
                    label: this.$t('sw-order.filters.campaignCodeFilter.label'),
                    placeholder: this.$t('sw-order.filters.campaignCodeFilter.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                },
                'promotion-code-filter': {
                    property: 'lineItems.payload.code',
                    type: 'string-filter',
                    label: this.$t('sw-order.filters.promotionCodeFilter.label'),
                    placeholder: this.$t('sw-order.filters.promotionCodeFilter.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                },
                'document-filter': {
                    property: 'documents',
                    label: this.$t('sw-order.filters.documentFilter.label'),
                    placeholder: this.$t('sw-order.filters.documentFilter.placeholder'),
                    optionHasCriteria: this.$t('sw-order.filters.documentFilter.textHasCriteria'),
                    optionNoCriteria: this.$t('sw-order.filters.documentFilter.textNoCriteria'),
                },
                'payment-method-filter': {
                    property: 'primaryOrderTransaction.paymentMethod',
                    label: this.$t('sw-order.filters.paymentMethodFilter.label'),
                    placeholder: this.$t('sw-order.filters.paymentMethodFilter.placeholder'),
                },
                'shipping-method-filter': {
                    property: 'primaryOrderDelivery.shippingMethod',
                    label: this.$t('sw-order.filters.shippingMethodFilter.label'),
                    placeholder: this.$t('sw-order.filters.shippingMethodFilter.placeholder'),
                },
                'billing-country-filter': {
                    property: 'billingAddress.country',
                    label: this.$t('sw-order.filters.billingCountryFilter.label'),
                    placeholder: this.$t('sw-order.filters.billingCountryFilter.placeholder'),
                },
                'shipping-country-filter': {
                    property: 'primaryOrderDelivery.shippingOrderAddress.country',
                    label: this.$t('sw-order.filters.shippingCountryFilter.label'),
                    placeholder: this.$t('sw-order.filters.shippingCountryFilter.placeholder'),
                },
                'user-group-filter': {
                    property: 'orderCustomer.user.group',
                    label: this.$t('sw-order.filters.userGroupFilter.label'),
                    placeholder: this.$t('sw-order.filters.userGroupFilter.placeholder'),
                },
                'line-item-filter': {
                    property: 'lineItems.blog',
                    label: this.$t('sw-order.filters.blogFilter.label'),
                    placeholder: this.$t('sw-order.filters.blogFilter.placeholder'),
                    criteria: this.blogCriteria,
                    displayVariants: true,
                },
            };
        },

        listFilters() {
            return this.filterFactory.create('order', this.listFilterOptions);
        },

        blogCriteria() {
            const blogCriteria = new Criteria(1, 25);
            blogCriteria.addAssociation('options.group');

            return blogCriteria;
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Contena.Filter.getByName('date');
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
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
        createdComponent() {},

        /**
         * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderDelivery instead
         */
        deliveryTooltip(deliveries) {
            return deliveries
                .map((delivery) => {
                    return `${delivery.shippingOrderAddress.street},
                        ${delivery.shippingOrderAddress.zipcode}
                        ${delivery.shippingOrderAddress.city}`;
                })
                .join('<hr style="margin: 8px 0">');
        },

        onEdit(order) {
            if (order?.id) {
                this.$router.push({
                    name: 'sw.order.detail',
                    params: {
                        id: order.id,
                    },
                });
            }
        },

        onInlineEditSave(order) {
            order.save();
        },

        onChangeLanguage() {
            this.getList();
        },

        async getList() {
            this.isLoading = true;

            let criteria = await Contena.Service('filterService').mergeWithStoredFilters(this.storeKey, this.orderCriteria);

            if (this.adminEsEnable) {
                criteria.setTerm(this.term);
            } else {
                criteria = await this.addQueryScores(this.term, criteria);
            }

            this.activeFilterNumber = criteria.filters.length;

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            try {
                const response = await this.orderRepository.search(criteria);

                this.total = response.total;
                this.orders = response;
                this.isLoading = false;
            } catch {
                this.isLoading = false;
            }
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, use order.billingAddress instead
         */
        getBillingAddress(order) {
            return order.addresses.find((address) => {
                return address.id === order.billingAddressId;
            });
        },

        disableDeletion(order) {
            if (!this.acl.can('order.deleter')) {
                return true;
            }

            return order.documents.length > 0;
        },

        getOrderColumns() {
            return [
                {
                    property: 'orderNumber',
                    label: 'sw-order.list.columnOrderNumber',
                    routerLink: 'sw.order.detail',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'channel.name',
                    label: 'sw-order.list.columnSalesChannel',
                    allowResize: true,
                    visible: false,
                },
                {
                    property: 'orderCustomer.firstName',
                    dataIndex: 'orderCustomer.lastName,orderCustomer.firstName',
                    label: 'sw-order.list.columnCustomerName',
                    allowResize: true,
                },
                {
                    property: 'orderCustomer.company',
                    label: 'sw-order.list.columnCustomerCompany',
                    allowResize: true,
                    visible: false,
                },
                {
                    property: 'billingAddressId',
                    dataIndex: 'billingAddress.street',
                    label: 'sw-order.list.columnBillingAddress',
                    allowResize: true,
                    visible: false,
                },
                {
                    property: 'primaryOrderDelivery.shippingOrderAddress.street',
                    label: 'sw-order.list.columnDeliveryAddress',
                    allowResize: true,
                },
                {
                    property: 'amountTotal',
                    label: 'sw-order.list.columnAmount',
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'stateMachineState.name',
                    label: 'sw-order.list.columnState',
                    allowResize: true,
                },
                {
                    property: 'primaryOrderTransaction.stateMachineState.name',
                    label: 'sw-order.list.columnTransactionState',
                    allowResize: true,
                },
                {
                    property: 'primaryOrderDelivery.stateMachineState.name',
                    label: 'sw-order.list.columnDeliveryState',
                    allowResize: true,
                },
                {
                    property: 'orderDateTime',
                    label: 'sw-order.list.orderDate',
                    allowResize: true,
                },
                {
                    property: 'affiliateCode',
                    inlineEdit: 'string',
                    label: 'sw-order.list.columnAffiliateCode',
                    allowResize: true,
                    visible: false,
                },
                {
                    property: 'campaignCode',
                    inlineEdit: 'string',
                    label: 'sw-order.list.columnCampaignCode',
                    allowResize: true,
                    visible: false,
                },
            ];
        },

        getVariantFromOrderState(order) {
            const style = this.stateStyleDataProviderService.getStyle('order.state', order.stateMachineState.technicalName);

            return style.colorCode;
        },

        getVariantFromPaymentState(order) {
            const technicalName = this.getTransactionState(order)?.technicalName;

            return this.stateStyleDataProviderService.getStyle('order_transaction.state', technicalName).colorCode;
        },

        getVariantFromDeliveryState(order) {
            const technicalName = this.getDeliveryState(order)?.technicalName;

            return this.stateStyleDataProviderService.getStyle('order_delivery.state', technicalName).colorCode;
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.orderRepository.delete(id).then(() => {
                this.$refs.orderGrid.resetSelection();
                this.getList();
            });
        },

        /**
         * @deprecated tag:v6.8.0 - Use listing mixin implementation directly
         */
        updateCriteria(criteria) {
            // Delegate to listing mixin implementation
            return Mixin.getByName('listing').methods.updateCriteria.call(this, criteria);
        },

        getStatusCriteria(value) {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('stateMachine.technicalName', value));

            return criteria;
        },

        async onBulkEditItems() {
            await this.$nextTick();

            const ordersExcludeDelivery = Object.values(this.$refs.orderGrid.selection).filter((order) => {
                if (!Contena.Feature.isActive('v6.8.0.0')) {
                    return !this.getDelivery(order);
                }
                return !order.primaryOrderDelivery;
            });
            const excludeDelivery = ordersExcludeDelivery.length > 0 ? '1' : '0';

            this.$router.push({
                name: 'sw.bulk.edit.order',
                params: {
                    excludeDelivery,
                },
            });
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderTransaction instead
         */
        transaction(order) {
            if (!Contena.Feature.isActive('v6.8.0.0')) {
                if (order.primaryOrderTransaction) {
                    return order.primaryOrderTransaction;
                }

                const transactions = order.transactions ?? [];
                for (let i = 0; i < transactions.length; i += 1) {
                    if (!['cancelled', 'failed'].includes(transactions[i].stateMachineState?.technicalName)) {
                        return transactions[i];
                    }
                }

                return transactions.last?.() ?? transactions[transactions.length - 1] ?? null;
            }

            return order.primaryOrderTransaction ?? null;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderDelivery instead
         */
        getDelivery(order) {
            if (!Contena.Feature.isActive('v6.8.0.0')) {
                return order.primaryOrderDelivery ?? order.deliveries?.[0] ?? null;
            }

            return order.primaryOrderDelivery ?? null;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderDelivery.shippingOrderAddress instead
         */
        getDeliveryAddress(order) {
            return this.getDelivery(order)?.shippingOrderAddress ?? null;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderDelivery.stateMachineState instead
         */
        getDeliveryState(order) {
            return this.getDelivery(order)?.stateMachineState ?? null;
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, use order.primaryOrderTransaction.stateMachineState instead
         */
        getTransactionState(order) {
            return this.transaction(order)?.stateMachineState ?? null;
        },
    },
};

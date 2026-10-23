import template from './sw-user-detail-order.html.twig';
import './sw-user-detail-order.scss';

/**
 * @sw-package checkout
 */

const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        user: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isLoading: false,
            activeCustomer: this.user,
            orders: null,
            term: '',
            sortBy: 'orderDateTime',
            sortDirection: 'DESC',
        };
    },

    computed: {
        orderColumns() {
            return this.getOrderColumns();
        },

        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        emptyTitle() {
            return this.term
                ? this.$t('sw-user.detailOrder.emptySearchTitle')
                : this.$t('sw-user.detailOrder.emptyTitle');
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },
    },

    watch: {
        user() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (this.orders?.criteria) {
                this.orders.criteria = null;
            }

            this.refreshList();
        },

        onChange(term) {
            this.term = term;
            this.orders.criteria.setPage(1);
            this.orders.criteria.setTerm(term);

            this.refreshList();
        },

        getOrderColumns() {
            return [
                {
                    property: 'orderNumber',
                    label: 'sw-user.detailOrder.columnNumber',
                },
                {
                    property: 'amountTotal',
                    label: 'sw-user.detailOrder.columnAmount',
                },
                {
                    property: 'stateMachineState.name',
                    label: 'sw-user.detailOrder.columnOrderState',
                },
                {
                    property: 'orderDateTime',
                    label: 'sw-user.detailOrder.columnOrderDate',
                },
            ];
        },

        refreshList() {
            let criteria = new Criteria(1, 25);
            if (!this.orders || !this.orders.criteria) {
                criteria.addFilter(Criteria.equals('order.orderCustomer.userId', this.user.id));
            } else {
                criteria = this.orders.criteria;
            }
            criteria.addAssociation('stateMachineState').addAssociation('currency');

            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            this.orderRepository.search(criteria).then((orders) => {
                this.orders = orders;
                this.isLoading = false;
            });
        },

        navigateToCreateOrder() {
            this.$router.push({
                name: 'sw.order.create',
                query: {
                    userId: this.user.id,
                },
            });
        },
    },
};

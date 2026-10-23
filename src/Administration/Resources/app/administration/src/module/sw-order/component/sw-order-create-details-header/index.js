import template from './sw-order-create-details-header.html.twig';

/**
 * @sw-package checkout
 */

const { Criteria } = Contena.Data;

/**
 * @deprecated tag:v6.8.0 - will be removed, is not used anymore
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['on-select-existing-user'],

    props: {
        user: {
            type: Object,
        },

        orderDate: {
            type: String,
            required: true,
        },

        cartPrice: {
            type: Object,
        },

        currency: {
            type: Object,
        },
    },

    data() {
        return {
            showNewCustomerModal: false,
        };
    },

    computed: {
        userId: {
            get() {
                return this.user ? this.user.id : '';
            },

            set(userId) {
                if (this.user) this.user.id = userId;
            },
        },

        userCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('defaultBillingAddress.country');

            return criteria;
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },
    },

    methods: {
        onSelectExistingCustomer(userId) {
            // Keep the current value when admin_user tries to delete it
            if (!userId) {
                return;
            }

            this.$emit('on-select-existing-user', userId);
        },

        onShowNewCustomerModal() {
            this.showNewCustomerModal = true;
        },

        onCloseNewCustomerModal() {
            this.showNewCustomerModal = false;
        },
    },
};

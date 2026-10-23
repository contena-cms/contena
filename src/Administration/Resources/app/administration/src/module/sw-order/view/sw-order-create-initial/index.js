import template from './sw-order-create-initial.html.twig';

/**
 * @sw-package checkout
 */

const { Store, Data, Service } = Contena;
const { Criteria } = Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            routeCustomerReady: false,
        };
    },

    computed: {
        userRepository() {
            return Service('repositoryFactory').create('user');
        },

        userCriteria() {
            const criteria = new Criteria(1, 25);
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('channel')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags');

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            const userId = this.$route.query?.userId;
            const orderStore = Store.get('swOrder');

            // Reset so grid never sees a stale user (with or without userId)
            orderStore.$reset();

            if (!userId) {
                this.routeCustomerReady = true;
                return;
            }

            try {
                const user = await this.userRepository.get(userId, Contena.Context.api, this.userCriteria);
                if (user) orderStore.setCustomer(user);
            } finally {
                this.routeCustomerReady = true;
            }
        },

        onCloseCreateModal() {
            this.$nextTick(() => {
                this.$router.push({ name: 'sw.order.index' });
            });
        },

        onPreviewOrder() {
            this.$nextTick(() => {
                this.$router.push({ name: 'sw.order.create.general' });
            });
        },
    },
};

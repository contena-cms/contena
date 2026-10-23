import template from './sw-order-create-details-body.html.twig';

/**
 * @sw-package checkout
 */

/**
 * @deprecated tag:v6.8.0 - will be removed, is not used anymore
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['on-edit-billing-address', 'on-edit-shipping-address'],

    props: {
        user: {
            type: Object,
        },

        isCustomerActive: {
            type: Boolean,
            default: false,
        },
    },

    computed: {
        email: {
            get() {
                return this.user ? this.user.email : null;
            },

            set(email) {
                if (this.user) this.user.email = email;
            },
        },

        phoneNumber: {
            get() {
                return this.user ? this.user.defaultBillingAddress.phoneNumber : null;
            },

            set(phoneNumber) {
                if (this.user) this.user.defaultBillingAddress.phoneNumber = phoneNumber;
            },
        },

        billingAddress: {
            get() {
                if (this.user) {
                    const { billingAddress, defaultBillingAddress } = this.user;
                    return billingAddress || defaultBillingAddress;
                }

                return null;
            },

            set(billingAddress) {
                if (this.user) this.user.billingAddress = billingAddress;
            },
        },

        shippingAddress: {
            get() {
                if (this.user) {
                    const { shippingAddress, defaultShippingAddress } = this.user;
                    return shippingAddress || defaultShippingAddress;
                }

                return null;
            },

            set(shippingAddress) {
                if (this.user) this.user.shippingAddress = shippingAddress;
            },
        },

        isAddressIdentical() {
            return this.shippingAddress?.id === this.billingAddress?.id;
        },
    },

    methods: {
        onEditBillingAddress() {
            this.$emit('on-edit-billing-address');
        },

        onEditShippingAddress() {
            this.$emit('on-edit-shipping-address');
        },
    },
};

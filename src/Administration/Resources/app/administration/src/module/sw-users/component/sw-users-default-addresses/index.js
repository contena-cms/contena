import template from './sw-user-default-addresses.html.twig';
import './sw-user-default-addresses.scss';

/**
 * @sw-package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['customSnippetApiService'],

    props: {
        user: {
            type: Object,
            required: true,
        },

        userEditMode: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            formattingShippingAddress: '',
            formattingBillingAddress: '',
        };
    },

    computed: {
        defaultShippingAddressLink() {
            return {
                name: 'sw.user.detail.addresses',
                params: {
                    id: this.user.id,
                },
                query: {
                    detailId: this.user.defaultShippingAddress.id,
                    edit: this.userEditMode,
                },
            };
        },

        defaultBillingAddressLink() {
            return {
                name: 'sw.user.detail.addresses',
                params: {
                    id: this.user.id,
                },
                query: {
                    detailId: this.user.defaultBillingAddress.id,
                    edit: this.userEditMode,
                },
            };
        },
    },

    created() {
        this.createdComponent();
    },

    watch: {
        user() {
            this.createdComponent();
        },
    },

    methods: {
        createdComponent() {
            this.renderFormattingAddress();
        },

        renderFormattingAddress() {
            this.customSnippetApiService
                .render(this.user.defaultShippingAddress, this.user.defaultShippingAddress.country?.addressFormat)
                .then((res) => {
                    this.formattingShippingAddress = res.rendered;
                });

            this.customSnippetApiService
                .render(this.user.defaultBillingAddress, this.user.defaultBillingAddress.country?.addressFormat)
                .then((res) => {
                    this.formattingBillingAddress = res.rendered;
                });
        },
    },
};

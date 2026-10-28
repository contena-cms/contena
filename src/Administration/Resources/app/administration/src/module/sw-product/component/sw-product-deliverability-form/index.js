/*
 * @sw-package inventory
 */

import template from './sw-product-deliverability-form.html.twig';

const { Mixin } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    mixins: [Mixin.getByName('placeholder')],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    computed: {
        product() {
            return Contena.Store.get('swProductDetail').product;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        loading() {
            return Contena.Store.get('swProductDetail').loading;
        },

        showModeSetting() {
            return Contena.Store.get('swProductDetail').showModeSetting;
        },

        ...mapPropertyErrors('product', [
            'stock',
            'deliveryTimeId',
            'isCloseout',
            'maxPurchase',
            'purchaseSteps',
            'minPurchase',
            'shippingFree',
            'restockTime',
        ]),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (typeof this.product.stock === 'undefined') {
                this.product.stock = 0;
            }
        },
    },
};

/*
 * @sw-package inventory
 */

import template from './sw-product-feature-set-form.html.twig';
import './sw-product-feature-set-form.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },
    },
};

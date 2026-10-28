/*
 * @sw-package inventory
 */

import template from './sw-product-detail-specifications.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'feature', 'repositoryFactory'],

    data() {
        return {
            showMediaModal: false,
        };
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

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        customFieldSets() {
            return Contena.Store.get('swProductDetail').customFieldSets;
        },

        showModeSetting() {
            return Contena.Store.get('swProductDetail').showModeSetting;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `productType` instead.
         */
        productStates() {
            return Contena.Store.get('swProductDetail').productStates;
        },

        productType() {
            return Contena.Store.get('swProductDetail').productType;
        },

        isDigitalProduct() {
            return this.productType === 'digital' || this.productStates.includes('is-download');
        },

        customFieldsExists() {
            return !this.customFieldSets.length <= 0;
        },

        showCustomFieldsCard() {
            return this.showProductCard('custom_fields') && !this.isLoading && this.customFieldsExists;
        },
    },

    methods: {
        showProductCard(key) {
            return Contena.Store.get('swProductDetail').showProductCard(key);
        },
    },
};

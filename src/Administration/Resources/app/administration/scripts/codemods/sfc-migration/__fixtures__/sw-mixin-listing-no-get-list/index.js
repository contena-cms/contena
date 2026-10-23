import template from './sw-mixin-listing-no-get-list.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Contena.Mixin.getByName('listing'),
    ],

    data() {
        return {
            items: null,
        };
    },
};

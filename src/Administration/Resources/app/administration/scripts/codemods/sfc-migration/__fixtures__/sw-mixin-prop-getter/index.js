import template from './sw-mixin-prop-getter.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Contena.Mixin.getByName('video-cover'),
    ],

    props: {
        item: {
            type: Object,
            required: true,
        },
    },

    methods: {
        onSelect() {
            this.openCoverSelectionModal();
        },
    },
};

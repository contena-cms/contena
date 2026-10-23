import template from './sw-cms-el-config-blog-listing-config-delete-modal.html.twig';
import './sw-cms-el-config-blog-listing-config-delete-modal.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    emits: ['confirm', 'cancel'],

    props: {
        blogSorting: {
            type: Object,
            required: true,
        },
    },

    methods: {
        onConfirm() {
            this.$emit('confirm');
        },

        onCancel() {
            this.$emit('cancel');
        },
    },
};

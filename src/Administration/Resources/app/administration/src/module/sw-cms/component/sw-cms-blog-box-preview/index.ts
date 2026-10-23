import template from './sw-cms-blog-box-preview.html.twig';
import './sw-cms-blog-box-preview.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Contena.Component.wrapComponentConfig({
    template,

    props: {
        hasText: {
            type: Boolean,
            required: false,
            default() {
                return false;
            },
        },
    },

    computed: {
        assetFilter() {
            return Contena.Filter.getByName('asset');
        },
    },
});

import template from './sw-cms-block-blog-heading.html.twig';
import './sw-cms-block-blog-heading.scss';

const { Store } = Contena;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    computed: {
        currentDeviceView() {
            return Store.get('cmsPage').currentCmsDeviceView;
        },

        currentDeviceViewClass() {
            return `is--${this.currentDeviceView}`;
        },
    },
};

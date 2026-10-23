import template from './sw-cms-el-category-navigation.html.twig';
import './sw-cms-el-category-navigation.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [Contena.Mixin.getByName('cms-element'), Contena.Mixin.getByName('placeholder')],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('category-navigation');
        },
    },
};

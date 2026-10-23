import template from './sw-cms-el-blog-listing.html.twig';
import './sw-cms-el-blog-listing.scss';

const { Mixin } = Contena;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [Mixin.getByName('cms-element')],

    computed: {
        currentDemoProducts() {
            return Contena.Store.get('cmsPage').currentDemoProducts;
        },

        demoProductCount() {
            return this.currentDemoProducts?.length || 8;
        },

        demoProductElement() {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: this.element.config.boxLayout.value,
                    },
                    displayMode: {
                        source: 'static',
                        value: 'standard',
                    },
                },
                data: null,
            };
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('blog-listing');
        },

        mountedComponent() {
            const section = this.$el.closest('.sw-cms-section');

            if (!this.$el?.closest?.classList?.contains) {
                return;
            }

            if (section.classList.contains('is--sidebar')) {
                this.demoProductCount = 6;
            }
        },

        getProduct(index) {
            const blog = this.currentDemoProducts?.at(index - 1);

            if (blog) {
                return { ...this.demoProductElement, data: { blog } };
            }

            return this.demoProductElement;
        },
    },
};

import './sw-cms-el-blog-name.scss';

const { Mixin } = Contena;

/**
 * @private
 * @sw-package discovery
 */
export default {
    mixins: [Mixin.getByName('cms-element')],

    computed: {
        isProductPage() {
            return this.cmsPageState?.currentPage?.type ?? '' === 'blog_detail';
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('blog-name');

            if (this.isProductPage && !this.element?.translated?.config?.content) {
                this.element.config.content.source = 'mapped';
                this.element.config.content.value = 'blog.name';
            }
            this.updateDemoValue();
        },

        updateDemoValue() {
            if (this.element.config.content.source === 'mapped') {
                let label = '';
                let className = 'sw-cms-el-blog-name__skeleton';

                if (this.element.config.content.value === 'blog.name') {
                    className = 'sw-cms-el-blog-name__placeholder';
                    label = this.$t('sw-cms.elements.blogName.label');
                }

                this.demoValue = `<h1 class="${className}">${label}</h1>`;

                if (this.cmsPageState.currentDemoEntity) {
                    this.demoValue = this.getDemoValue(this.element.config.content.value);
                }
            }
        },
    },
};

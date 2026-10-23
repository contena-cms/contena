/**
 * @private
 * @sw-package discovery
 */
export default {
    computed: {
        isProductPage() {
            return (this.cmsPageState?.currentPage?.type ?? '') === 'blog_detail';
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('blog-name');

            if (!this.isProductPage || this.element?.translated?.config?.content) {
                return;
            }

            if (this.element.config.content.source && this.element.config.content.value) {
                return;
            }

            this.element.config.content.source = 'mapped';
            this.element.config.content.value = 'blog.name';
        },
    },
};

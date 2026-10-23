/**
 * @private
 * @sw-package discovery
 */
export default {
    computed: {
        isProductPage() {
            return this.cmsPageState?.currentPage?.type ?? '' === 'blog_detail';
        },
    },

    methods: {
        createdComponent() {
            this.initElementConfig('manufacturer-logo');

            if (this.isProductPage && !this.element?.translated?.config?.media && !this.element?.data?.media) {
                this.element.config.media.source = 'mapped';
                this.element.config.media.value = 'blog.manufacturer.media';
            }
        },
    },
};

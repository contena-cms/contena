/*
 * @sw-package inventory
 */

import template from './sw-blog-detail-seo.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature', 'acl', 'repositoryFactory'],

    data() {
        return {
            currentSalesChannelId: undefined,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        categories() {
            if (this.blog.categories.length > 0) {
                return this.blog.categories;
            }

            return this.parentProduct.categories ?? [];
        },

        mainCategoryRepository() {
            return this.repositoryFactory.create('main_category');
        },

        parentMainCategory() {
            if (this.parentProduct.mainCategories && this.currentSalesChannelId) {
                return this.parentProduct.mainCategories.find((category) => {
                    return category.channelId === this.currentSalesChannelId;
                });
            }

            return null;
        },

        blogMainCategory: {
            get() {
                return this.blog.mainCategories.find((category) => {
                    return category.channelId === this.currentSalesChannelId;
                });
            },
            set(newMainCategory) {
                if (!newMainCategory) {
                    this.blog.mainCategories = this.blog.mainCategories.filter((category) => {
                        return category.channelId !== this.currentSalesChannelId;
                    });
                    return;
                }

                const newEntity = this.mainCategoryRepository.create();
                newEntity.blogId = this.blog.id;
                newEntity.categoryId = newMainCategory.categoryId;
                newEntity.channelId = newMainCategory.channelId;

                if (newMainCategory.category) {
                    newEntity.category = newMainCategory.category;
                }

                this.onRemoveMainCategory(newMainCategory);
                this.onAddMainCategory(newEntity);
            },
        },
    },

    methods: {
        onAddMainCategory(mainCategory) {
            if (this.blog.mainCategories) {
                this.blog.mainCategories.push(mainCategory);
            }
        },

        onRemoveMainCategory(mainCategory) {
            if (!this.blog.mainCategories) {
                return;
            }

            this.blog.mainCategories = this.blog.mainCategories.filter((item) => {
                return item.channelId !== mainCategory.channelId;
            });
        },

        onChangeSalesChannel(currentSalesChannelId) {
            this.currentSalesChannelId = currentSalesChannelId;
        },
    },
};

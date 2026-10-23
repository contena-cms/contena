/*
 * @sw-package inventory
 */

import template from './sw-blog-modal-delivery.html.twig';
import './sw-blog-modal-delivery.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature', 'repositoryFactory', 'acl'],

    emits: ['modal-close', 'configuration-close'],

    props: {
        blog: {
            type: Object,
            required: true,
        },

        selectedGroups: {
            type: Array,
            required: true,
        },
    },

    data() {
        return {
            activeTab: 'order',
            isLoading: false,
        };
    },

    computed: {
        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        deliveryTabs() {
            return [
                {
                    label: this.$t('sw-blog.variations.deliveryModal.order'),
                    name: 'order',
                },
                {
                    label: this.$t('sw-blog.variations.deliveryModal.media'),
                    name: 'media',
                },
                {
                    label: this.$t('sw-blog.variations.deliveryModal.listing'),
                    name: 'listing',
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.blog.variantListingConfig) {
                this.blog.variantListingConfig = {
                    displayParent: null,
                    configuratorGroupConfig: [],
                };
            }
        },

        saveDeliveryConfiguration() {
            this.isLoading = true;

            // Handle variant listing modes (single, expanded) if exists
            const blog = this.handleExpandedListing(this.blog);

            // Save the blog after generating
            this.blogRepository.save(blog).then(() => {
                this.$emit('configuration-close');
            });
        },

        cancelDeliveryConfiguration() {
            this.$emit('configuration-close');
        },

        handleExpandedListing(blog) {
            if (blog && blog.listingMode === 'expanded') {
                const configuratorGroupConfig = blog.variantListingConfig.configuratorGroupConfig ?? [];

                // remove main_variant_id and display_parent from configuratorGroupConfig
                blog.variantListingConfig.mainVariantId = null;
                blog.variantListingConfig.displayParent = null;
                blog.variantListingConfig.configuratorGroupConfig = configuratorGroupConfig;
            }

            if (blog && blog.listingMode === 'single') {
                blog.variantListingConfig.displayParent = blog.variantListingConfig.displayParent === true;
                blog.variantListingConfig.configuratorGroupConfig?.forEach((group) => {
                    group.expressionForListings = false;
                });
            }

            delete blog.listingMode;

            return blog;
        },
    },
};

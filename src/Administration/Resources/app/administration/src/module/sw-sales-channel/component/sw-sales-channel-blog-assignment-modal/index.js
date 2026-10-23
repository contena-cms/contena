/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-blogs-assignment-modal.html.twig';
import './sw-sales-channel-blogs-assignment-modal.scss';

const { uniqBy } = Contena.Utils.array;

const updateElementVisibility = (element, binding) => {
    element.style.visibility = binding.value ? 'visible' : 'hidden';
    element.style.position = binding.value ? 'static' : 'absolute';
    element.style.top = binding.value ? 'auto' : '0';
    element.style.left = binding.value ? 'auto' : '0';
    element.style.bottom = binding.value ? 'auto' : '0';
    element.style.right = binding.value ? 'auto' : '0';
    element.style.transform = binding.value ? 'translateX(0)' : 'translateX(100%)';
};

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    emits: ['modal-close', 'blogs-add'],

    directives: {
        hide: {
            beforeMount: updateElementVisibility,
            updated: updateElementVisibility,
        },
    },

    props: {
        channel: {
            type: Object,
            required: true,
        },

        isAssignProductLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            activeTab: 'singleProducts',
            singleProducts: [],
            categoryProducts: [],
            groupProducts: [],
            isProductLoading: false,
            tabContentHeight: '600px',
            blogContainerStyle: {
                display: 'grid',
                placeItems: 'stretch',
            },
            categoryContainerStyle: {
                display: 'grid',
                placeItems: 'stretch',
            },
            blogGroupContainerStyle: {
                display: 'grid',
                placeItems: 'stretch',
            },
        };
    },

    computed: {
        blogCount() {
            return this.blogs.length;
        },

        blogs() {
            return uniqBy([...this.singleProducts, ...this.categoryProducts, ...this.groupProducts], 'id');
        },

        blogAssignmentTabs() {
            return [
                {
                    label: this.$t('sw-sales-channel.detail.blogAssignmentModal.singleProducts'),
                    name: 'singleProducts',
                },
                {
                    label: this.$t('sw-sales-channel.detail.blogAssignmentModal.categories.title'),
                    name: 'categories',
                },
                {
                    label: this.$t('sw-sales-channel.detail.blogAssignmentModal.dynamicProductGroups.title'),
                    name: 'dynamicProductGroups',
                },
            ];
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.getProductContainerStyle();
            this.getCategoryContainerStyle();
            this.getProductGroupContainerStyle();
        },

        getProductContainerStyle() {
            const cardSectionSecondaryHeight = `${this.$refs?.blog?.$refs?.cardSectionSecondary?.$el?.offsetHeight ?? 0}px`;

            this.blogContainerStyle['grid-template-rows'] =
                `auto calc(${this.tabContentHeight} - ${cardSectionSecondaryHeight})`;
        },

        getCategoryContainerStyle() {
            const tabContentGutter = '20px';
            const alertHeight = `${this.$refs?.category?.$refs?.alert?.$el?.offsetHeight ?? 0}px`;
            const cardSectionSecondaryHeight = `${this.$refs?.category?.$refs?.cardSectionSecondary?.$el?.offsetHeight ?? 0}px`;

            this.categoryContainerStyle['grid-template-rows'] =
                `auto calc(${this.tabContentHeight} - (${tabContentGutter} + ${alertHeight} + ${
                    cardSectionSecondaryHeight
                }))`;
        },

        getProductGroupContainerStyle() {
            const tabContentGutter = '20px';
            const alertHeight = `${this.$refs?.blogGroup?.$refs?.alert?.$el?.offsetHeight ?? 0}px`;
            const cardSectionSecondaryHeight = `${this.$refs?.blogGroup?.$refs?.cardSectionSecondary?.$el?.offsetHeight ?? 0}px`;

            this.blogGroupContainerStyle['grid-template-rows'] =
                `auto calc(${this.tabContentHeight} - (${tabContentGutter} + ${alertHeight} + ${
                    cardSectionSecondaryHeight
                }))`;
        },

        onChangeSelection(blogs, type) {
            this[type] = blogs;
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        onAddProducts() {
            this.$emit('blogs-add', this.blogs);
        },

        setProductLoading(isProductLoading) {
            this.isProductLoading = isProductLoading;
        },
    },
};

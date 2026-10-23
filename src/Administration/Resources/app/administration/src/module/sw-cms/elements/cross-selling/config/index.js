import template from './sw-cms-el-config-cross-selling.html.twig';
import './sw-cms-el-config-cross-selling.scss';

const { Mixin } = Contena;
const { Criteria } = Contena.Data;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: ['feature', 'repositoryFactory'],

    emits: ['element-update'],

    mixins: [Mixin.getByName('cms-element')],

    data() {
        return {
            activeTab: 'content',
        };
    },

    computed: {
        tabs() {
            return [
                {
                    label: this.$t('sw-cms.elements.general.config.tab.content'),
                    name: 'content',
                },
                {
                    label: this.$t('sw-cms.elements.general.config.tab.options'),
                    name: 'options',
                },
            ];
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogSelectContext() {
            return {
                ...Contena.Context.api,
                inheritance: true,
            };
        },

        blogCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        selectedProductCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('crossSellings.assignedProducts.blog');

            return criteria;
        },

        isProductPageType() {
            return this.cmsPageState?.currentPage?.type === 'blog_detail';
        },

        boxLayoutOptions() {
            return [
                {
                    id: 1,
                    value: 'standard',
                    label: this.$t('sw-cms.elements.blogBox.config.label.layoutTypeStandard'),
                },
                {
                    id: 2,
                    value: 'image',
                    label: this.$t('sw-cms.elements.blogBox.config.label.layoutTypeImage'),
                },
                {
                    id: 3,
                    value: 'minimal',
                    label: this.$t('sw-cms.elements.blogBox.config.label.layoutTypeMinimal'),
                },
            ];
        },

        displayModeOptions() {
            return [
                {
                    id: 1,
                    value: 'standard',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeStandard'),
                },
                {
                    id: 2,
                    value: 'cover',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeCover'),
                },
                {
                    id: 3,
                    value: 'contain',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeContain'),
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('cross-selling');
        },

        async onProductChange(blogId) {
            if (blogId) {
                await this.fetchProduct(blogId);
            } else {
                this.deleteProduct();
            }

            this.$emit('element-update', this.element);
        },

        async fetchProduct(blogId) {
            const blog = await this.blogRepository.get(
                blogId,
                this.blogSelectContext,
                this.selectedProductCriteria,
            );
            this.element.config.blog.value = blogId;

            this.element.data.blog = blog;
        },

        deleteProduct() {
            this.element.config.blog.value = null;

            this.element.data.blog = null;
        },
    },
};

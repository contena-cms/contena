import Criteria from 'src/core/data/criteria.data';
import template from './sw-cms-el-config-blog-description-reviews.html.twig';
import './sw-cms-el-config-blog-description-reviews.scss';

const { Mixin } = Contena;

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
            criteria.addAssociation('properties');

            return criteria;
        },

        isProductPage() {
            return this.cmsPageState?.currentPage?.type === 'blog_detail';
        },

        alignmentOptions() {
            return [
                {
                    id: 1,
                    value: 'flex-start',
                    label: this.$t('sw-cms.elements.general.config.label.verticalAlignTop'),
                },
                {
                    id: 2,
                    value: 'center',
                    label: this.$t('sw-cms.elements.general.config.label.verticalAlignCenter'),
                },
                {
                    id: 3,
                    value: 'flex-end',
                    label: this.$t('sw-cms.elements.general.config.label.verticalAlignBottom'),
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('blog-description-reviews');
        },

        onProductChange(blogId) {
            if (!blogId) {
                this.element.config.blog.value = null;

                this.element.data.blogId = null;
                this.element.data.blog = null;
            } else {
                this.blogRepository
                    .get(blogId, this.blogSelectContext, this.selectedProductCriteria)
                    .then((blog) => {
                        this.element.config.blog.value = blogId;

                        this.element.data.blogId = blogId;
                        this.element.data.blog = blog;
                    });
            }

            this.$emit('element-update', this.element);
        },
    },
};

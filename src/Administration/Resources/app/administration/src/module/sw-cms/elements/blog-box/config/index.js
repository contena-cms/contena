import Criteria from 'src/core/data/criteria.data';
import template from './sw-cms-el-config-blog-box.html.twig';
import './sw-cms-el-config-blog-box.scss';

const { Mixin } = Contena;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['element-update'],

    mixins: [Mixin.getByName('cms-element')],

    computed: {
        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogSelectContext() {
            const context = { ...Contena.Context.api };
            context.inheritance = true;

            return context;
        },

        blogCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        boxLayoutOptions() {
            return [
                {
                    value: 'standard',
                    label: this.$t('sw-cms.elements.blogBox.config.label.layoutTypeStandard'),
                },
                {
                    value: 'image',
                    label: this.$t('sw-cms.elements.blogBox.config.label.layoutTypeImage'),
                },
                {
                    value: 'minimal',
                    label: this.$t('sw-cms.elements.blogBox.config.label.layoutTypeMinimal'),
                },
            ];
        },

        displayModeOptions() {
            return [
                {
                    value: 'standard',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeStandard'),
                },
                {
                    value: 'cover',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeCover'),
                },
                {
                    value: 'contain',
                    label: this.$t('sw-cms.elements.general.config.label.displayModeContain'),
                },
            ];
        },

        verticalAlignOptions() {
            return [
                {
                    value: 'flex-start',
                    label: this.$t('sw-cms.elements.general.config.label.verticalAlignTop'),
                },
                {
                    value: 'center',
                    label: this.$t('sw-cms.elements.general.config.label.verticalAlignCenter'),
                },
                {
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
            this.initElementConfig('blog-box');
        },

        onProductChange(blogId) {
            if (!blogId) {
                this.element.config.blog.value = null;
                this.element.data.blogId = null;
                this.element.data.blog = null;
            } else {
                const criteria = new Criteria(1, 25);
                criteria.addAssociation('cover');
                criteria.addAssociation('options.group');

                this.blogRepository.get(blogId, this.blogSelectContext, criteria).then((blog) => {
                    this.element.config.blog.value = blogId;

                    this.element.data.blogId = blogId;
                    this.element.data.blog = blog;
                });
            }

            this.$emit('element-update', this.element);
        },
    },
};

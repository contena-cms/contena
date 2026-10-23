import template from './sw-cms-el-config-blog-slider.html.twig';
import './sw-cms-el-config-blog-slider.scss';

const { Mixin } = Contena;
const { Criteria, EntityCollection } = Contena.Data;

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    inject: ['repositoryFactory', 'feature'],

    mixins: [Mixin.getByName('cms-element')],

    data() {
        return {
            activeTab: 'content',
            blogCollection: null,
            blogStream: null,
            showProductStreamPreview: false,

            // Temporary values to store the previous selection in case the admin_user changes the assignment type.
            tempProductIds: [],
            tempStreamId: null,
            originProductsValue: [],
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
                    label: this.$t('sw-cms.elements.general.config.tab.settings'),
                    name: 'settings',
                },
            ];
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogStreamRepository() {
            return this.repositoryFactory.create('blog_stream');
        },

        blogs() {
            if (this.element?.data?.blogs && this.element.data.blogs.length > 0) {
                return this.element.data.blogs;
            }

            return null;
        },

        blogMediaFilter() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('cover');
            criteria.addAssociation('options.group');

            return criteria;
        },

        blogMultiSelectContext() {
            const context = { ...Contena.Context.api };
            context.inheritance = true;

            return context;
        },

        blogAssignmentTypes() {
            return this.getProductAssignmentTypes();
        },

        blogStreamSortingOptions() {
            return this.getProductStreamSortingOptions();
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

        arrowOptions() {
            return [
                {
                    id: 1,
                    value: 'none',
                    label: this.$t('sw-cms.elements.blogSlider.config.label.navigationPositionNone'),
                },
                {
                    id: 2,
                    value: 'inside',
                    label: this.$t('sw-cms.elements.blogSlider.config.label.navigationPositionInside'),
                },
                {
                    id: 3,
                    value: 'outside',
                    label: this.$t('sw-cms.elements.blogSlider.config.label.navigationPositionOutside'),
                },
            ];
        },

        blogStreamCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('internal', false));
            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('blog-slider');

            this.originProductsValue = this.element.translated?.config.blogs.value;
            this.blogCollection = new EntityCollection('/blog', 'blog', Contena.Context.api);

            if (this.element.config.blogs.value.length <= 0) {
                return;
            }

            if (this.element.config.blogs.source === 'blog_stream') {
                this.loadProductStream();
            } else {
                this.loadManualAssignment();
            }
        },

        async loadManualAssignment() {
            const criteria = new Criteria(1, 100);
            criteria.addAssociation('cover');
            criteria.addAssociation('options.group');
            criteria.setIds(this.element.config.blogs.value);

            this.blogCollection = await this.blogRepository.search(criteria, {
                ...Contena.Context.api,
                inheritance: true,
            });
        },

        getProductAssignmentTypes() {
            return [
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogAssignmentTypeOptions.manual'),
                    value: 'static',
                },
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogAssignmentTypeOptions.blogStream'),
                    value: 'blog_stream',
                },
            ];
        },

        getProductStreamSortingOptions() {
            return [
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogStreamSortingOptions.nameAsc'),
                    value: 'name:ASC',
                },
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogStreamSortingOptions.nameDesc'),
                    value: 'name:DESC',
                },
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogStreamSortingOptions.creationDateAsc'),
                    value: 'createdAt:ASC',
                },
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogStreamSortingOptions.creationDateDesc'),
                    value: 'createdAt:DESC',
                },
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogStreamSortingOptions.priceAsc'),
                    value: 'cheapestPrice:ASC',
                },
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogStreamSortingOptions.priceDesc'),
                    value: 'cheapestPrice:DESC',
                },
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogStreamSortingOptions.releaseDateAsc'),
                    value: 'releaseDate:ASC',
                },
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogStreamSortingOptions.releaseDateDesc'),
                    value: 'releaseDate:DESC',
                },
                {
                    label: this.$t('sw-cms.elements.blogSlider.config.blogStreamSortingOptions.random'),
                    value: 'random',
                },
            ];
        },

        onChangeAssignmentType(type) {
            if (type === 'blog_stream') {
                this.tempProductIds = this.element.config.blogs.value;
                this.element.config.blogs.value = this.tempStreamId;
            } else {
                this.tempStreamId = this.element.config.blogs.value;
                this.element.config.blogs.value = this.tempProductIds;
            }
        },

        async loadProductStream() {
            this.blogStream = await this.blogStreamRepository.get(
                this.element.config.blogs.value,
                Contena.Context.api,
                new Criteria(1, 25),
            );
        },

        onChangeProductStream(streamId) {
            if (streamId === null) {
                this.blogStream = null;
                return;
            }

            this.loadProductStream();
        },

        onClickProductStreamPreview() {
            if (this.blogStream === null) {
                return;
            }

            this.showProductStreamPreview = true;
        },

        onCloseProductStreamModal() {
            this.showProductStreamPreview = false;
        },

        onProductsChange() {
            this.element.config.blogs.value = this.blogCollection.getIds();

            if (this.element.translated?.config?.blogs) {
                this.element.translated.config.blogs.value = this.blogCollection.getIds();
            }

            if (!this.element?.data) {
                return;
            }

            this.element.data.blogs = this.blogCollection;
        },

        isSelected(itemId) {
            return this.blogCollection.has(itemId);
        },

        onRestoreInheritance() {
            if (this.element.config.blogs.source === 'blog_stream') {
                this.element.config.blogs.value = this.originProductsValue;
                this.loadProductStream();
            } else {
                this.element.config.blogs.value = this.originProductsValue;
                this.loadManualAssignment();
            }
        },
    },
};

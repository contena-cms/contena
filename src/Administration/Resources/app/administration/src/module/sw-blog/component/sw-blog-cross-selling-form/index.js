/**
 * @sw-package inventory
 */

import template from './sw-blog-cross-selling-form.html.twig';
import './sw-blog-cross-selling-form.scss';

const { Criteria } = Contena.Data;
const { Component, Mixin } = Contena;
const { mapPropertyErrors } = Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'blogStreamConditionService'],

    provide() {
        return {
            blogCustomFields: {},
        };
    },

    mixins: [Mixin.getByName('placeholder')],

    props: {
        crossSelling: {
            type: Object,
            required: true,
        },

        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            showDeleteModal: false,
            showModalPreview: false,
            blogStream: null,
            blogStreamFilter: [],
            blogStreamFilterTree: null,
            optionSearchTerm: '',
            useManualAssignment: false,
            sortBy: 'name',
            sortDirection: 'ASC',
            assignmentKey: 0,
        };
    },

    computed: {
        ...mapPropertyErrors('crossSelling', ['name', 'type', 'position']),

        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        /**
         * @deprecated tag:v6.8.0 - Unused, will be removed without replacement
         */
        blogCrossSellingRepository() {
            return this.repositoryFactory.create('blog_cross_selling');
        },

        blogStreamRepository() {
            return this.repositoryFactory.create('blog_stream');
        },

        blogStreamFilterRepository() {
            if (!this.blogStream || !this.blogStream.filters) {
                return null;
            }

            const { entity, source } = this.blogStream.filters;

            return this.repositoryFactory.create(entity, source);
        },

        blogStreamFilterCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('blogStreamId', this.crossSelling.blogStreamId));

            return criteria;
        },

        /**
         * @deprecated tag:v6.8.0 - Unused, will be removed without replacement
         */
        crossSellingAssigmentRepository() {
            return this.repositoryFactory.create('blog_cross_selling_assigned_products');
        },

        crossSellingTitle() {
            return (
                this.crossSelling.name ||
                this.crossSelling.translated?.name ||
                this.$t('sw-blog.crossselling.newCrossSellingTitle')
            );
        },

        sortingTypes() {
            return [
                {
                    label: this.$t('sw-blog.crossselling.priceDescendingSortingType'),
                    value: 'cheapestPrice:DESC',
                },
                {
                    label: this.$t('sw-blog.crossselling.priceAscendingSortingType'),
                    value: 'cheapestPrice:ASC',
                },
                {
                    label: this.$t('sw-blog.crossselling.nameSortingType'),
                    value: 'name:ASC',
                },
                {
                    label: this.$t('sw-blog.crossselling.releaseDateDescendingSortingType'),
                    value: 'releaseDate:DESC',
                },
                {
                    label: this.$t('sw-blog.crossselling.releaseDateAscendingSortingType'),
                    value: 'releaseDate:ASC',
                },
            ];
        },

        crossSellingTypes() {
            return [
                {
                    label: this.$t('sw-blog.crossselling.blogStreamType'),
                    value: 'blogStream',
                },
                {
                    label: this.$t('sw-blog.crossselling.blogListType'),
                    value: 'blogList',
                },
            ];
        },

        previewDisabled() {
            return !this.blogStream;
        },

        sortingConCat() {
            return `${this.crossSelling.sortBy}:${this.crossSelling.sortDirection}`;
        },

        /**
         * @deprecated tag:v6.8.0 - Unused, will be removed without replacement
         */
        disablePositioning() {
            return !!this.term || this.sortBy !== 'position';
        },

        associationValue() {
            return this.crossSelling?.blogStreamId || '';
        },

        crossSellingTypeOptions() {
            return this.crossSellingTypes.map((item) => {
                return {
                    id: item.value,
                    value: item.value,
                    label: item.label,
                };
            });
        },

        sortingTypeOptions() {
            return this.sortingTypes.map((item) => {
                return {
                    id: item.value,
                    value: item.value,
                    label: item.label,
                };
            });
        },

        blogStreamCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('internal', false));
            return criteria;
        },
    },

    watch: {
        'crossSelling.blogStreamId'() {
            if (!this.useManualAssignment) {
                this.loadStreamPreview();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.useManualAssignment = this.crossSelling.type === 'blogList';
            if (!this.useManualAssignment && this.crossSelling.blogStreamId !== null) {
                this.loadStreamPreview();
            }
        },

        onShowDeleteModal() {
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.onCloseDeleteModal();
            this.$nextTick(() => {
                this.blog.crossSellings.remove(this.crossSelling.id);
            });
        },

        openModalPreview() {
            if (this.previewDisabled) {
                return;
            }

            this.showModalPreview = true;
        },

        closeModalPreview() {
            this.showModalPreview = false;
        },

        loadStreamPreview() {
            this.blogStreamRepository.get(this.crossSelling.blogStreamId).then((blogStream) => {
                this.blogStream = blogStream;
                this.getProductStreamFilter();
            });
        },

        getProductStreamFilter() {
            if (this.blogStreamFilterRepository === null) {
                return [];
            }
            return this.blogStreamFilterRepository
                .search(this.blogStreamFilterCriteria)
                .then((blogStreamFilter) => {
                    this.blogStreamFilter = blogStreamFilter;
                });
        },

        updateProductStreamFilterTree({ conditions }) {
            this.blogStreamFilterTree = conditions;
        },

        onSortingChanged(value) {
            [this.crossSelling.sortBy, this.crossSelling.sortDirection] = value.split(':');
        },

        onTypeChanged(value) {
            this.useManualAssignment = value === 'blogList';
        },
    },
};

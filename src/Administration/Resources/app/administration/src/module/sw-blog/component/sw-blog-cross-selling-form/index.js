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

    inject: ['repositoryFactory', 'contentStreamConditionService'],

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
            contentStream: null,
            contentStreamFilter: [],
            contentStreamFilterTree: null,
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

        contentStreamRepository() {
            return this.repositoryFactory.create('content_stream');
        },

        contentStreamFilterRepository() {
            if (!this.contentStream || !this.contentStream.filters) {
                return null;
            }

            const { entity, source } = this.contentStream.filters;

            return this.repositoryFactory.create(entity, source);
        },

        contentStreamFilterCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('contentStreamId', this.crossSelling.contentStreamId));

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
                    label: this.$t('sw-blog.crossselling.contentStreamType'),
                    value: 'contentStream',
                },
                {
                    label: this.$t('sw-blog.crossselling.blogListType'),
                    value: 'blogList',
                },
            ];
        },

        previewDisabled() {
            return !this.contentStream;
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
            return this.crossSelling?.contentStreamId || '';
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

        contentStreamCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('internal', false));
            return criteria;
        },
    },

    watch: {
        'crossSelling.contentStreamId'() {
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
            if (!this.useManualAssignment && this.crossSelling.contentStreamId !== null) {
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
            this.contentStreamRepository.get(this.crossSelling.contentStreamId).then((contentStream) => {
                this.contentStream = contentStream;
                this.getProductStreamFilter();
            });
        },

        getProductStreamFilter() {
            if (this.contentStreamFilterRepository === null) {
                return [];
            }
            return this.contentStreamFilterRepository
                .search(this.contentStreamFilterCriteria)
                .then((contentStreamFilter) => {
                    this.contentStreamFilter = contentStreamFilter;
                });
        },

        updateProductStreamFilterTree({ conditions }) {
            this.contentStreamFilterTree = conditions;
        },

        onSortingChanged(value) {
            [this.crossSelling.sortBy, this.crossSelling.sortDirection] = value.split(':');
        },

        onTypeChanged(value) {
            this.useManualAssignment = value === 'blogList';
        },
    },
};

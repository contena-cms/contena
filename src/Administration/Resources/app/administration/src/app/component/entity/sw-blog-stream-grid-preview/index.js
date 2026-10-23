/**
 * @sw-package inventory
 */

import template from './sw-blog-stream-grid-preview.html.twig';
import './sw-blog-stream-grid-preview.scss';

const { Context, Defaults } = Contena;
const { Criteria } = Contena.Data;

/**
 * @private
 */
export default {
    template,

    inject: ['repositoryFactory', 'contentStreamPreviewService'],

    emits: ['selection-change'],

    props: {
        /**
         * The apiFilter of a loaded blog stream
         */
        filters: {
            required: true,
        },
        columns: {
            required: false,
            type: Array,
            default() {
                return [];
            },
        },
        criteria: {
            required: false,
            type: Object,
            default() {
                return new Criteria(1, 10);
            },
        },
        showSelection: {
            required: false,
            type: Boolean,
            default: false,
        },
        /**
         * Whether matching variants are grouped, mirroring the blog stream's "display as group" setting.
         */
        displayAsGroup: {
            required: false,
            type: Boolean,
            default: true,
        },
    },

    data() {
        return {
            blogs: [],
            systemCurrency: null,
            searchTerm: '',
            page: 1,
            total: 0,
            limit: 10,
            isLoading: false,
        };
    },

    computed: {
        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        channelRepository() {
            return this.repositoryFactory.create('channel');
        },

        channelCriteria() {
            return new Criteria(1, 1)
                .addFilter(Criteria.not('OR', [Criteria.equals('typeId', Defaults.blogComparisonTypeId)]))
                .addSorting(Criteria.sort('type.iconName', 'ASC'));
        },

        defaultColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-blog-stream.filter.values.blog'),
                    type: 'text',
                    routerLink: 'sw.blog.detail',
                },
                {
                    property: 'manufacturer.name',
                    label: this.$t('sw-blog-stream.filter.values.manufacturer'),
                },
                {
                    property: 'active',
                    label: this.$t('sw-blog-stream.filter.values.active'),
                    align: 'center',
                    type: 'bool',
                },
                {
                    property: 'price',
                    label: this.$t('sw-blog-stream.filter.values.price'),
                },
                {
                    property: 'stock',
                    label: this.$t('sw-blog-stream.filter.values.stock'),
                    align: 'right',
                },
            ];
        },

        blogColumns() {
            if (this.columns.length) {
                return this.columns;
            }

            return this.defaultColumns;
        },

        emptyStateMessage() {
            if (!this.filters) {
                return this.$t('global.entity-components.contentStreamPreview.emptyMessageNoStream');
            }

            if (this.searchTerm.length) {
                return this.$t('global.entity-components.contentStreamPreview.emptyMessageNoSearchResults', {
                    term: this.searchTerm,
                });
            }

            return this.$t('global.entity-components.contentStreamPreview.emptyMessageNoProducts');
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },
    },

    watch: {
        async filters(filtersValue) {
            if (!filtersValue) {
                this.total = 0;
                return;
            }

            this.isLoading = true;
            this.systemCurrency = await this.loadSystemDefaultCurrency();
            this.loadProducts();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        onSearchTermChange(searchTerm) {
            this.searchTerm = searchTerm;
            this.page = 1;
            this.loadProducts();
        },
        async createdComponent() {
            if (!this.filters) {
                return;
            }

            this.isLoading = true;
            this.systemCurrency = await this.loadSystemDefaultCurrency();
            this.loadProducts();
        },

        loadSystemDefaultCurrency() {
            return this.repositoryFactory
                .create('currency')
                .get(Contena.Context.app.systemCurrencyId, Contena.Context.api, {
                    cacheKey: [
                        'shared-data',
                        'system-currency',
                        Contena.Context.app.systemCurrencyId,
                        Contena.Context.api.languageId ?? 'default',
                    ],
                    ttl: 5 * 60 * 1000,
                });
        },

        loadProducts() {
            this.criteria.term = this.searchTerm || null;
            this.criteria.filters = [...this.filters];
            this.criteria.limit = this.limit;
            this.criteria.setPage(this.page);
            this.criteria.addAssociation('manufacturer');
            this.criteria.addAssociation('options.group');

            return this.channelRepository
                .searchIds(this.channelCriteria)
                .then(({ data }) => {
                    return this.contentStreamPreviewService.preview(
                        data.at(0),
                        this.criteria,
                        [],
                        {
                            'sw-currency-id': Context.app.systemCurrencyId,
                            'sw-inheritance': true,
                        },
                        this.displayAsGroup,
                    );
                })
                .then((result) => {
                    this.blogs = Object.values(result.elements);
                    this.total = result.total;
                    this.isLoading = false;
                });
        },

        onPageChange({ page = 1, limit = 25 }) {
            this.page = page;
            this.limit = limit;
            this.isLoading = true;

            this.loadProducts();
        },

        getPriceForDefaultCurrency(blog) {
            const price = blog.price.find((blogPrice) => {
                return blogPrice.currencyId === this.systemCurrency.id;
            });

            return price ? price.gross : '-';
        },

        onSelectionChange(blogs) {
            this.$emit('selection-change', blogs);
        },
    },
};

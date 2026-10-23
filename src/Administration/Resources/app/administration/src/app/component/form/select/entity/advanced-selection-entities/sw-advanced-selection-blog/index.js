/**
 * @sw-package framework
 */

import template from './sw-advanced-selection-blog.html.twig';

const { Criteria } = Contena.Data;

/**
 * @private
 * @description Configures the advanced selection in entity selects.
 * Should only be used as a parameter `advanced-selection-component="sw-advanced-selection-blog"`
 * to `sw-entity-...-select` components.
 * @status prototype
 */
export default {
    template,

    inject: ['repositoryFactory'],

    emits: ['selection-submit', 'modal-close'],

    data() {
        return {
            currencies: [],
        };
    },

    computed: {
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        blogContext() {
            return { ...Contena.Context.api, inheritance: true };
        },

        currenciesColumns() {
            return [...this.currencies]
                .sort((a, b) => {
                    return b.isSystemDefault ? 1 : -1;
                })
                .map((item) => {
                    return {
                        property: `price-${item.isoCode}`,
                        dataIndex: `price.${item.id}`,
                        label: `${item.name}`,
                        routerLink: 'sw.blog.detail',
                        allowResize: true,
                        currencyId: item.id,
                        visible: item.isSystemDefault,
                        align: 'right',
                        useCustomSort: true,
                    };
                });
        },

        blogColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-blog.list.columnName'),
                    routerLink: 'sw.blog.detail',
                    inlineEdit: 'string',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'blogNumber',
                    naturalSorting: true,
                    label: this.$t('sw-blog.list.columnProductNumber'),
                    align: 'right',
                    allowResize: true,
                },
                {
                    property: 'manufacturer.name',
                    label: this.$t('sw-blog.list.columnManufacturer'),
                    allowResize: true,
                },
                {
                    property: 'active',
                    label: this.$t('sw-blog.list.columnActive'),
                    inlineEdit: 'boolean',
                    allowResize: true,
                    align: 'center',
                },
                ...this.currenciesColumns,
                {
                    property: 'stock',
                    label: this.$t('sw-blog.list.columnInStock'),
                    inlineEdit: 'number',
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'availableStock',
                    label: this.$t('sw-blog.list.columnAvailableStock'),
                    allowResize: true,
                    align: 'right',
                },
                {
                    property: 'releaseDate',
                    label: this.$t('sw-blog.list.columnReleaseDate'),
                    allowResize: true,
                },
                {
                    property: 'visibilities',
                    dataIndex: 'visibilities.channel',
                    label: this.$t('sw-blog.list.columnVisibilities'),
                    allowResize: true,
                    sortable: false,
                    visible: false,
                },
                {
                    property: 'categories',
                    label: this.$t('sw-blog.list.columnCategories'),
                    allowResize: true,
                    sortable: false,
                    visible: false,
                },
                {
                    property: 'tags',
                    label: this.$t('sw-blog.list.columnTags'),
                    allowResize: true,
                    sortable: false,
                    visible: false,
                },
            ];
        },

        blogFilters() {
            return {
                'active-filter': {
                    property: 'active',
                    label: this.$t('sw-blog.filters.activeFilter.label'),
                    placeholder: this.$t('sw-blog.filters.activeFilter.placeholder'),
                },
                'stock-filter': {
                    property: 'stock',
                    label: this.$t('sw-blog.filters.stockFilter.label'),
                    numberType: 'int',
                    step: 1,
                    min: 0,
                    fromPlaceholder: this.$t('sw-blog.filters.fromPlaceholder'),
                    toPlaceholder: this.$t('sw-blog.filters.toPlaceholder'),
                },
                'blog-without-images-filter': {
                    property: 'media',
                    label: this.$t('sw-blog.filters.imagesFilter.label'),
                    placeholder: this.$t('sw-blog.filters.imagesFilter.placeholder'),
                    optionHasCriteria: this.$t('sw-blog.filters.imagesFilter.textHasCriteria'),
                    optionNoCriteria: this.$t('sw-blog.filters.imagesFilter.textNoCriteria'),
                },
                'manufacturer-filter': {
                    property: 'manufacturer',
                    label: this.$t('sw-blog.filters.manufacturerFilter.label'),
                    placeholder: this.$t('sw-blog.filters.manufacturerFilter.placeholder'),
                },
                'visibilities-filter': {
                    property: 'visibilities.channel',
                    label: this.$t('sw-blog.filters.channelsFilter.label'),
                    placeholder: this.$t('sw-blog.filters.channelsFilter.placeholder'),
                },
                'categories-filter': {
                    property: 'categories',
                    label: this.$t('sw-blog.filters.categoriesFilter.label'),
                    placeholder: this.$t('sw-blog.filters.categoriesFilter.placeholder'),
                    displayPath: true,
                },
                'sales-filter': {
                    property: 'sales',
                    label: this.$t('sw-blog.filters.salesFilter.label'),
                    digits: 20,
                    min: 0,
                    fromPlaceholder: this.$t('sw-blog.filters.fromPlaceholder'),
                    toPlaceholder: this.$t('sw-blog.filters.toPlaceholder'),
                },
                'price-filter': {
                    property: 'price',
                    label: this.$t('sw-blog.filters.priceFilter.label'),
                    digits: 20,
                    min: 0,
                    fromPlaceholder: this.$t('sw-blog.filters.fromPlaceholder'),
                    toPlaceholder: this.$t('sw-blog.filters.toPlaceholder'),
                },
                'tags-filter': {
                    property: 'tags',
                    label: this.$t('sw-blog.filters.tagsFilter.label'),
                    placeholder: this.$t('sw-blog.filters.tagsFilter.placeholder'),
                },
                'release-date-filter': {
                    property: 'releaseDate',
                    label: this.$t('sw-blog.filters.releaseDateFilter.label'),
                    dateType: 'datetime-local',
                    fromFieldLabel: null,
                    toFieldLabel: null,
                    showTimeframe: true,
                },
            };
        },

        blogAssociations() {
            return [
                'cover',
                'media',
                'manufacturer',
                'options.group',
                'visibilities.channel',
                'categories',
                'tags',
            ];
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },

        dateFilter() {
            return Contena.Filter.getByName('date');
        },

        stockColorVariantFilter() {
            return Contena.Filter.getByName('stockColorVariant');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.currencyRepository.search(new Criteria(1, 500)).then((currencies) => {
                this.currencies = currencies;
            });
        },

        blogHasVariants(blogEntity) {
            const childCount = blogEntity.childCount;

            return childCount !== null && childCount > 0;
        },

        getCurrencyPriceByCurrencyId(currencyId, prices) {
            const priceForProduct = prices.find((price) => price.currencyId === currencyId);

            if (priceForProduct) {
                return priceForProduct;
            }

            return {
                currencyId: null,
                gross: null,
                linked: true,
                net: null,
            };
        },

        getCategoryBreadcrumb(item) {
            if (item.breadcrumb) {
                return item.breadcrumb.join(' / ');
            }
            return item.translated.name || item.name;
        },
    },
};

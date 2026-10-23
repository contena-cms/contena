/*
 * @sw-package inventory
 */

import { searchRankingPoint } from 'src/app/service/search-ranking.service';
import template from './sw-blog-list.html.twig';
import './sw-blog-list.scss';

const { Mixin, Context } = Contena;
const { Criteria } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'numberRangeService',
        'acl',
        'filterFactory',
    ],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('listing'), Mixin.getByName('placeholder')],

    data() {
        const data = {
            blogs: null,
            currencies: [],
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            naturalSorting: true,
            isLoading: false,
            isBulkLoading: false,
            total: 0,
            blog: null,
            cloning: false,
            blogEntityVariantModal: false,
            filterCriteria: [],
            // @deprecated tag:v6.8.0 - Will be removed
            blogTypeOptions: [
                {
                    label: this.$t('sw-blog.type.physical'),
                    value: 'physical',
                },
                {
                    label: this.$t('sw-blog.type.digital'),
                    value: 'digital',
                },
            ],
            defaultFilters: [
                'blog-number-filter',
                'active-filter',
                'blog-without-images-filter',
                'release-date-filter',
                'stock-filter',
                'price-filter',
                'manufacturer-filter',
                'visibilities-filter',
                'categories-filter',
                'sales-filter',
                'tags-filter',
                'blog-states-filter',
                'blog-type-filter',
            ],
            storeKey: 'grid.filter.blog',
            activeFilterNumber: 0,
            showBulkEditModal: false,
            searchConfigEntity: 'blog',
        };

        if (Contena.Feature.isActive('v6.8.0.0')) {
            data.defaultFilters = data.defaultFilters.filter((filter) => filter !== 'blog-states-filter');
        }

        return data;
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        blogColumns() {
            return this.getProductColumns();
        },

        currenciesColumns() {
            return this.currencies
                .toSorted((a, b) => {
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

        blogCriteria() {
            const blogCriteria = new Criteria(this.page, this.limit);
            const sorting = Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting);

            blogCriteria.setTerm(this.term);
            blogCriteria.addSorting(sorting);

            if (!blogCriteria.sortings.some((existing) => existing.field === 'id')) {
                blogCriteria.addSorting(Criteria.sort('id'));
            }

            blogCriteria.addAssociation('cover.media');
            blogCriteria.addAssociation('manufacturer');
            blogCriteria.addAssociation('tax');

            this.filterCriteria.forEach((filter) => {
                blogCriteria.addFilter(filter);
            });

            return blogCriteria;
        },

        channelCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addSorting(Criteria.sort('name'));

            return criteria;
        },

        showVariantModal() {
            return !!this.blogEntityVariantModal;
        },

        listFilterOptions() {
            const filters = {
                'blog-number-filter': {
                    property: 'blogNumber',
                    type: 'string-filter',
                    label: this.$t('sw-blog.filters.blogNumberFilter.label'),
                    placeholder: this.$t('sw-blog.filters.blogNumberFilter.placeholder'),
                    valueProperty: 'key',
                    labelProperty: 'key',
                    criteriaFilterType: this.adminEsEnable ? 'equals' : 'contains',
                },
                'release-date-filter': {
                    property: 'releaseDate',
                    label: this.$t('sw-blog.filters.releaseDateFilter.label'),
                    dateType: 'datetime-local',
                    fromFieldLabel: null,
                    toFieldLabel: null,
                    showTimeframe: true,
                },
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
                    criteria: this.channelCriteria,
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
                'blog-states-filter': {
                    property: 'states',
                    label: this.$t('sw-blog.filters.blogStatesFilter.label'),
                    placeholder: this.$t('sw-blog.filters.blogStatesFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: [
                        {
                            label: this.$t('sw-blog.filters.blogStatesFilter.options.physical'),
                            value: 'is-physical',
                        },
                        {
                            label: this.$t('sw-blog.filters.blogStatesFilter.options.digital'),
                            value: 'is-download',
                        },
                    ],
                },
                'blog-type-filter': {
                    property: 'type',
                    label: this.$t('sw-blog.filters.blogTypeFilter.label'),
                    placeholder: this.$t('sw-blog.filters.blogTypeFilter.placeholder'),
                    type: 'multi-select-filter',
                    options: this.blogTypes.map((type) => ({
                        label: this.$t(`sw-blog.type.${type}`),
                        value: type,
                    })),
                },
            };

            if (Contena.Feature.isActive('v6.8.0.0')) {
                delete filters['blog-states-filter'];
            }

            return filters;
        },

        listFilters() {
            return this.filterFactory.create('blog', this.listFilterOptions);
        },

        blogBulkEditColumns() {
            return this.blogColumns.map((item) => {
                const { inlineEdit, ...restParams } = item;
                return restParams;
            });
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, because the filter is unused
         */
        dateFilter() {
            return Contena.Filter.getByName('date');
        },

        stockColorVariantFilter() {
            return Contena.Filter.getByName('stockColorVariant');
        },

        adminEsEnable() {
            if (!Contena.Feature.isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                return false;
            }

            return Context.app.adminEsEnable ?? false;
        },

        blogTypes() {
            return ['physical', 'digital'];
        },
    },

    beforeRouteLeave(to, from, next) {
        const goingToProductDetailPage = to.name === 'sw.blog.detail.base';

        if (goingToProductDetailPage && this.showVariantModal) {
            this.closeVariantModal();
        }

        this.$nextTick(() => {
            next();
        });
    },

    methods: {
        async getList() {
            this.isLoading = true;

            let criteria = await Contena.Service('filterService').mergeWithStoredFilters(
                this.storeKey,
                this.blogCriteria,
            );
            criteria.filters = this.normalizeCategoryFilters(criteria.filters);

            if (!criteria.filters.some((filter) => filter.field === 'type')) {
                criteria.addPostFilter(Criteria.equalsAny('type', this.blogTypes));
            }

            if (this.adminEsEnable) {
                criteria.setTerm(this.term);
            } else {
                criteria = await this.addQueryScores(this.term, criteria);
            }

            // Clone blog query to its variant
            const variantCriteria = cloneDeep(criteria);
            criteria.addFilter(Criteria.equals('blog.parentId', null));
            variantCriteria.addFilter(Criteria.not('AND', [Criteria.equals('blog.parentId', null)]));

            this.activeFilterNumber = criteria.filters.length - 1;

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            try {
                if (this.term) {
                    const variants = await this.blogRepository.search(variantCriteria, {
                        ...Context.api,
                        inheritance: true,
                    });
                    if (variants.length > 0) {
                        const parentIds = [];

                        variants.forEach((variant) => {
                            parentIds.push(variant.parentId);
                        });

                        criteria.addQuery(Criteria.equalsAny('id', parentIds), searchRankingPoint.HIGH_SEARCH_RANKING);
                    }
                }

                const currencyCriteria = new Criteria(1, 500);
                currencyCriteria.addSorting(Criteria.sort('name', 'ASC', false));

                const result = await Promise.all([
                    this.blogRepository.search(criteria),
                    this.repositoryFactory.create('currency').search(currencyCriteria, Contena.Context.api, {
                        cacheKey: ['shared-data', 'currencies', Contena.Context.api.languageId ?? 'default'],
                        ttl: 5 * 60 * 1000,
                    }),
                ]);

                const blogs = result[0];
                const currencies = result[1];

                this.total = blogs.total;
                this.blogs = blogs;

                this.currencies = currencies;
                this.isLoading = false;

                this.selection = {};
            } catch {
                this.isLoading = false;
            }
        },

        onInlineEditSave(promise, blog) {
            const blogName = blog.name || this.placeholder(blog, 'name');

            return promise
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-blog.list.messageSaveSuccess', { name: blogName }, 0),
                    });
                })
                .catch(() => {
                    this.getList();
                    this.createNotificationError({
                        message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
                    });
                });
        },

        onInlineEditCancel(blog) {
            blog.discardChanges();
        },

        updateTotal({ total }) {
            this.total = total;
        },

        onChangeLanguage(languageId) {
            Contena.Store.get('context').setApiLanguageId(languageId);
            this.getList();
        },

        updateCriteria(criteria) {
            return Mixin.getByName('listing').methods.updateCriteria.call(this, this.normalizeCategoryFilters(criteria));
        },

        normalizeCategoryFilters(filters) {
            return filters.map((filter) => {
                if (filter.field !== 'categories.id') {
                    return filter;
                }

                const categoryIds = Array.isArray(filter.value) ? filter.value : filter.value.split('|');
                if (categoryIds.length === 0) {
                    return filter;
                }

                return Criteria.multi('OR', [filter, Criteria.equalsAny('blog.streams.categories.id', categoryIds)]);
            });
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

        getProductColumns() {
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
                    naturalSorting: this.naturalSorting,
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
                {
                    property: 'sales',
                    label: this.$t('sw-blog.list.columnSales'),
                    allowResize: true,
                    align: 'right',
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
                    property: 'createdAt',
                    label: this.$t('sw-blog.list.columnCreatedAt'),
                    allowResize: true,
                },
                {
                    property: 'updatedAt',
                    label: this.$t('sw-blog.list.columnUpdatedAt'),
                    allowResize: true,
                    visible: false,
                },
            ];
        },

        onDuplicate(referenceProduct) {
            this.blog = referenceProduct;
            this.cloning = true;
        },

        onDuplicateFinish(duplicate) {
            this.cloning = false;
            this.blog = null;

            this.$nextTick(() => {
                this.$router.push({
                    name: 'sw.blog.detail',
                    params: { id: duplicate.id },
                });
            });
        },

        onColumnSort(column) {
            this.onSortColumn(column);
        },

        blogHasVariants(blogEntity) {
            const childCount = blogEntity.childCount;

            return childCount !== null && childCount > 0;
        },

        blogIsDigital(blogEntity) {
            return blogEntity.type && blogEntity.type === 'digital';
        },

        openVariantModal(item) {
            this.blogEntityVariantModal = item;
        },

        closeVariantModal() {
            this.blogEntityVariantModal = null;
        },

        onBulkEditItems() {
            let includesDigital = '0';
            const digital = Object.values(this.selection).filter((blog) => blog.type === 'digital');
            if (digital.length > 0) {
                includesDigital = digital.filter((blog) => blog.isCloseout).length !== digital.length ? '1' : '2';
            }

            this.$router.push({
                name: 'sw.bulk.edit.blog',
                params: {
                    parentId: 'null',
                    includesDigital,
                },
            });
        },

        onBulkEditModalOpen() {
            this.showBulkEditModal = true;
        },

        onBulkEditModalClose() {
            this.showBulkEditModal = false;
        },
    },
};

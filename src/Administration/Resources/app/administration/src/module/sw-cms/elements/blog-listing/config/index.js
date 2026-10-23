import template from './sw-cms-el-config-blog-listing.html.twig';
import './sw-cms-el-config-blog-listing.scss';

const { Mixin } = Contena;
const { Criteria, EntityCollection } = Contena.Data;
const { get, set, unset, has, cloneDeep } = Contena.Utils.object;
const { isEmpty } = Contena.Utils.types;

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
            blogSortings: new EntityCollection('/blog-sorting', 'blog_sorting', Contena.Context.api),
            defaultSorting: {},
            defaultSortingId: null,
            filters: [],
            filterPropertiesTerm: '',
            properties: [],
            propertiesPage: 1,
            propertiesLimit: 6,
            propertiesTotal: 0,
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
                    label: this.$t('sw-cms.elements.blogListing.config.tab.sorting'),
                    name: 'sorting',
                },
                {
                    label: this.$t('sw-cms.elements.blogListing.config.tab.filter'),
                    name: 'filter',
                },
            ];
        },

        showSortingGrid() {
            return this.element.config.useCustomSorting.value;
        },

        showFilterGrid() {
            return !this.filterByProperties;
        },

        blogSortingRepository() {
            return this.repositoryFactory.create('blog_sorting');
        },

        propertyRepository() {
            return this.repositoryFactory.create('property_group');
        },

        blogSortingsCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equalsAny('id', [...Object.keys(this.blogSortingsConfigValue)]));
            criteria.addSorting(Criteria.sort('priority', 'desc'));

            return criteria;
        },

        propertyCriteria() {
            const criteria = new Criteria(this.propertiesPage, this.propertiesLimit);

            criteria.setTerm(this.filterPropertiesTerm);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));
            criteria.addFilter(Criteria.equals('filterable', true));

            return criteria;
        },

        allProductSortingsCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('locked', false));

            return criteria;
        },

        excludedDefaultSortingCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.defaultSorting.id) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equals('id', this.defaultSorting.id)]));
            }

            criteria.addFilter(Criteria.equals('locked', false));

            return criteria;
        },

        blogSortingsConfigValue() {
            return this.element.config.availableSortings.value;
        },

        filterByManufacturer: {
            get() {
                return this.isActiveFilter('manufacturer-filter');
            },
            set(value) {
                this.updateFilters('manufacturer-filter', value);
            },
        },

        filterByRating: {
            get() {
                return this.isActiveFilter('rating-filter');
            },
            set(value) {
                this.updateFilters('rating-filter', value);
            },
        },

        filterByPrice: {
            get() {
                return this.isActiveFilter('price-filter');
            },
            set(value) {
                this.updateFilters('price-filter', value);
            },
        },

        filterByFreeShipping: {
            get() {
                return this.isActiveFilter('shipping-free-filter');
            },
            set(value) {
                this.updateFilters('shipping-free-filter', value);
            },
        },

        filterByProperties: {
            get() {
                return !this.isActiveFilter('property-filter');
            },
            set(value) {
                this.updateFilters('property-filter', !value);
                this.sortProperties(this.properties);
            },
        },

        showPropertySelection() {
            return !this.properties.length < 1;
        },

        showProductSortingsSelection() {
            return this.blogSortings.length > 0;
        },

        gridColumns() {
            return [
                {
                    property: 'status',
                    label: 'sw-cms.elements.blogListing.config.filter.gridHeaderStatus',
                    disabled: this.showFilterGrid,
                    align: 'center',
                    width: '70px',
                },
                {
                    property: 'name',
                    label: 'sw-cms.elements.blogListing.config.filter.gridHeaderName',
                },
            ];
        },

        gridClasses() {
            return {
                'is--disabled': this.showFilterGrid,
            };
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
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

        boxHeadlineLevel() {
            return [
                {
                    id: 1,
                    value: null,
                    label: this.$t('sw-cms.elements.blogBox.config.label.headlineLevelEmptyOption'),
                },
                {
                    id: 2,
                    value: 2,
                    label: this.$t('sw-cms.elements.blogBox.config.label.headlineLevelDefaultOption'),
                },
                {
                    id: 3,
                    value: 3,
                    label: this.$t('sw-cms.elements.blogBox.config.label.headlineLevelOption', { level: 3 }),
                },
                {
                    id: 4,
                    value: 4,
                    label: this.$t('sw-cms.elements.blogBox.config.label.headlineLevelOption', { level: 4 }),
                },
                {
                    id: 5,
                    value: 5,
                    label: this.$t('sw-cms.elements.blogBox.config.label.headlineLevelOption', { level: 5 }),
                },
                {
                    id: 6,
                    value: 6,
                    label: this.$t('sw-cms.elements.blogBox.config.label.headlineLevelOption', { level: 6 }),
                },
            ];
        },
    },

    watch: {
        blogSortings() {
            this.onUpdateProductSortings();
        },
        defaultSorting() {
            if (Object.keys(this.defaultSorting).length === 0) {
                this.element.config.defaultSorting.value = '';
            } else {
                this.element.config.defaultSorting.value = this.defaultSorting.id;
            }
        },
        'element.config.filters.value'() {
            this.unpackFilters();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('blog-listing');

            this.initProductSorting();
            this.initDefaultSorting();
            this.unpackFilters();
            this.loadFilterableProperties();
        },

        onUpdateProductSortings() {
            const newValue = {};

            this.blogSortings.forEach((item) => {
                newValue[item.id] = item.priority;
            });

            // add the default sorting to available sortings, so it won't break logic
            if (this.defaultSorting.id && !this.blogSortings.has(this.defaultSorting.id)) {
                newValue[this.defaultSorting.id] = this.defaultSorting.priority;

                const collection = EntityCollection.fromCollection(this.blogSortings);
                collection.add(this.defaultSorting);
                this.blogSortings = collection;
            }

            this.element.config.availableSortings.value = newValue;
        },

        onSortingPrioritySave() {
            this.onUpdateProductSortings();
        },

        onSortingPriorityCancel(blogSorting) {
            blogSorting.priority = this.blogSortingsConfigValue[blogSorting.id];
        },

        async initProductSorting() {
            if (Object.keys(this.blogSortingsConfigValue).length === 0) {
                this.blogSortings = new EntityCollection('/blog-sorting', 'blog_sorting', Contena.Context.api);

                return;
            }

            this.blogSortings = await this.fetchProductSortings();
        },

        fetchProductSortings() {
            return this.blogSortingRepository
                .search(this.blogSortingsCriteria)
                .then((blogSortings) => this.updateValuesFromConfig(blogSortings));
        },

        updateValuesFromConfig(blogSortings) {
            Object.entries(this.blogSortingsConfigValue).forEach(([id, value]) => {
                const matchingProductSorting = blogSortings.find((blogSorting) => blogSorting.id === id);

                if (!matchingProductSorting) {
                    return;
                }

                matchingProductSorting.priority = value;
            });

            return blogSortings;
        },

        /**
         * This functions transforms the blog sorting entities to an format that the server accepts
         * e.g. 'Blog sorting entity' => [{ 'test-sorting': 10 }]
         */
        transformProductSortings() {
            if (this.blogSortings.length === 0) {
                return [];
            }

            const object = {};

            this.blogSortings.forEach((currentProductSorting) => {
                object[currentProductSorting.id] = currentProductSorting.priority;
            });

            return object;
        },

        initDefaultSorting() {
            const defaultSortingId = this.element.config.defaultSorting.value;
            if (!defaultSortingId) {
                this.defaultSorting = {};
                this.defaultSortingId = null;

                return;
            }

            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('id', defaultSortingId));

            return this.blogSortingRepository.search(criteria).then((response) => {
                this.defaultSorting = response.first() || {};
                this.defaultSortingId = this.defaultSorting.id ?? null;
            });
        },

        async restoreDefaultSorting() {
            await this.initDefaultSorting();

            if (this.defaultSorting.id && !this.blogSortings.has(this.defaultSorting.id)) {
                const collection = EntityCollection.fromCollection(this.blogSortings);
                collection.add(this.defaultSorting);
                this.blogSortings = collection;
            }
        },

        loadFilterableProperties() {
            return this.propertyRepository.search(this.propertyCriteria).then((properties) => {
                this.propertiesTotal = properties.total;

                this.properties = this.sortProperties(properties);
            });
        },

        sortProperties(properties) {
            properties.forEach((property) => {
                if (!this.filterByProperties) {
                    property.active = true;

                    return;
                }

                // eslint-disable-next-line inclusive-language/use-inclusive-words
                property.active = this.element.config.propertyWhitelist.value.includes(property.id);
            });

            properties.sort((a, b) => {
                if (a.active === b.active || !a.active === !b.active) {
                    return 0;
                }

                if (a.active) {
                    return -1;
                }

                return 1;
            });

            return properties;
        },

        onDefaultSortingChange(entity, defaultSorting) {
            if (!defaultSorting) {
                this.defaultSorting = {};
                return;
            }

            // add the default sorting to available sortings, so it won't break logic
            if (!this.blogSortings.has(defaultSorting.id)) {
                const collection = EntityCollection.fromCollection(this.blogSortings);
                collection.add(defaultSorting);
                this.blogSortings = collection;
            }

            this.defaultSorting = defaultSorting;
        },

        isDefaultSorting(blogSorting) {
            return this.defaultSorting.id === blogSorting.id;
        },

        isActiveFilter(item) {
            return this.filters.includes(item);
        },

        updateFilters(item, active) {
            if (active) {
                this.filters = [...this.filters, item];
            } else {
                this.filters = this.filters.reduce((acc, current) => {
                    if (current === item) {
                        return acc;
                    }

                    return [...acc, current];
                }, []);
            }

            this.element.config.filters.value = this.filters.join();
        },

        unpackFilters() {
            if (this.element.config.filters === undefined) {
                return;
            }

            const filters = this.element.config.filters.value;

            if (filters === null || filters === '') {
                this.filters = [];
                return;
            }

            this.filters = filters.split(',');
        },

        onFilterProperties() {
            this.propertiesPage = 1;

            return this.loadFilterableProperties();
        },

        onPropertiesPageChange({ limit, page }) {
            this.propertiesLimit = limit;
            this.propertiesPage = page;

            return this.loadFilterableProperties();
        },

        propertyStatusChanged(enable, id) {
            // eslint-disable-next-line inclusive-language/use-inclusive-words
            const allowlist = this.element.config.propertyWhitelist.value;

            if (enable) {
                // eslint-disable-next-line inclusive-language/use-inclusive-words
                this.element.config.propertyWhitelist.value = [...allowlist, id];

                return;
            }

            // eslint-disable-next-line inclusive-language/use-inclusive-words
            this.element.config.propertyWhitelist.value = allowlist.reduce((acc, current) => {
                if (current === id) {
                    return acc;
                }

                return [...acc, current];
            }, []);
        },

        onFilterInheritanceRemove() {
            const childConfig = this.contentEntity?.slotConfig?.[this.element.id];

            if (!childConfig || has(childConfig, 'propertyWhitelist')) {
                return;
            }

            const inherited = get(this.inheritedSlotConfig?.[this.element.id], 'propertyWhitelist') ?? {
                source: 'static',
                value: [],
            };
            set(childConfig, 'propertyWhitelist', cloneDeep(inherited));
        },

        onFilterInheritanceRestore() {
            const contentEntity = this.contentEntity;
            const slotConfig = contentEntity?.slotConfig;

            if (slotConfig?.[this.element.id]) {
                unset(slotConfig[this.element.id], 'propertyWhitelist');

                if (isEmpty(slotConfig[this.element.id])) {
                    unset(slotConfig, this.element.id);
                }

                if (isEmpty(slotConfig)) {
                    set(contentEntity, 'slotConfig', null);
                }
            }

            const inherited = get(this.inheritedSlotConfig?.[this.element.id], 'propertyWhitelist') ?? {
                source: 'static',
                value: [],
            };
            set(this.element.config, 'propertyWhitelist', cloneDeep(inherited));
            this.unpackFilters();
            this.sortProperties(this.properties);
        },
    },
};

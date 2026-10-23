/*
 * @sw-package inventory
 */

import template from './sw-blog-detail-variants.html.twig';
import './sw-blog-detail-variants.scss';

const { Criteria, EntityCollection } = Contena.Data;
const { uniqBy } = Contena.Utils.array;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature', 'repositoryFactory', 'acl'],

    data() {
        return {
            variantListHasContent: false,
            activeModal: '',
            isLoading: true,
            blogEntity: {},
            configuratorSettingsRepository: {},
            groups: [],
            blogEntityLoaded: false,
            propertiesAvailable: true,
            showAddPropertiesModal: false,
            defaultTab: 'all',
            activeTab: 'all',
            limit: 500,
        };
    },

    computed: {
        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        variants() {
            return Contena.Store.get('swProductDetail').variants;
        },

        isStoreLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        contextLanguageId() {
            return Contena.Store.get('context').api.languageId;
        },

        blogRepository() {
            return this.repositoryFactory.create('blog');
        },

        groupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        propertyRepository() {
            return this.repositoryFactory.create('property_group_option');
        },

        variantCardTabs() {
            return [
                {
                    label: this.$t('sw-blog.variations.variationCard.tabs.allProducts'),
                    name: 'all',
                },
                {
                    label: this.$t('sw-blog.variations.variationCard.tabs.physicalProducts'),
                    name: 'physical',
                },
                {
                    label: this.$t('sw-blog.variations.variationCard.tabs.digitalProducts'),
                    name: 'digital',
                },
            ];
        },

        blogProperties() {
            return this.isChild && this.blog?.properties?.length <= 0
                ? this.parentProduct.properties
                : this.blog.properties;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed, use `currentProductType` instead.
         * @returns {string[]}
         */
        currentProductStates() {
            return this.activeTab.split(',');
        },

        currentProductType() {
            return this.activeTab.split(',')[0] ?? 'all';
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },

        groupCriteria() {
            const criteria = new Criteria(1, this.limit);

            criteria.addFields('name');

            return criteria;
        },

        /**
         * @returns {Object[]}
         */
        configSettingGroups() {
            const settings = this.blogEntity?.configuratorSettings ?? [];
            const groupIds = uniqBy(settings, 'option.groupId').map((item) => item.option.groupId);
            if (groupIds.length === 0) {
                return [];
            }
            const groupMap = new Map(this.groups.map((group) => [group.id, group]));
            return groupIds.map((id) => groupMap.get(id)).filter(Boolean);
        },
    },

    watch: {
        isStoreLoading: {
            handler() {
                if (this.isStoreLoading === false) {
                    this.loadData();
                }
            },
        },

        contextLanguageId: {
            handler() {
                this.$refs.generatedVariants.getList();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.checkIfPropertiesExists();
        },

        mountedComponent() {
            this.loadData();
        },

        setActiveTab(tabName) {
            this.activeTab = tabName;
        },

        loadData() {
            if (!this.isStoreLoading && this.blog?.id) {
                this.loadOptions().then(() => this.loadGroups());
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement.
         */
        async loadConfigSettingGroups() {
            // No-op: configSettingGroups is computed from blogEntity.configuratorSettings and groups.
        },

        loadOptions() {
            return new Promise((resolve) => {
                const criteria = new Criteria(1, 25);

                criteria.addAssociation('configuratorSettings.option');
                criteria.addAssociation('prices');

                this.blogRepository.get(this.blog.id, Contena.Context.api, criteria).then((blog) => {
                    this.blogEntity = blog;
                    this.blogEntityLoaded = true;

                    resolve();
                });
            });
        },

        loadGroups() {
            return new Promise((resolve) => {
                this.$nextTick().then(async () => {
                    this.groups = await this.loadAllPropertyGroups(this.groupCriteria);
                    resolve();
                });
            });
        },

        updateVariations() {
            // Reset filter
            this.$refs.generatedVariants.includeOptions = [];
            this.$refs.generatedVariants.filterWindowOpen = false;

            // get new filter options
            this.loadOptions()
                .then(() => {
                    return this.loadGroups();
                })
                .then(() => {
                    this.$refs.generatedVariants.getFilterOptions();
                    this.$refs.generatedVariants.resetFilterOptions();
                });
        },

        updateVariantListHasContent(variantList) {
            // Check for empty search or filter results
            const isFilterActive = this.$refs.generatedVariants.includeOptions.length > 0;
            const searchTerm = this.$route.query ? this.$route.query.term : '';

            this.variantListHasContent = variantList.length > 0 || searchTerm || isFilterActive;
            this.isLoading = false;
        },

        openModal(value) {
            this.activeModal = value;
        },

        onConfigurationClosed() {
            this.loadData();
            this.activeModal = '';
        },

        checkIfPropertiesExists() {
            this.propertyRepository.search(new Criteria(1, 1)).then((res) => {
                this.propertiesAvailable = res.total > 0;
            });
        },

        openAddPropertiesModal() {
            if (!this.propertiesAvailable) {
                this.$router.push({ name: 'sw.property.index' });
            } else {
                this.updateNewProperties();
                this.showAddPropertiesModal = true;
            }
        },

        closeAddPropertiesModal() {
            this.showAddPropertiesModal = false;
            this.updateNewProperties();
        },

        updateNewProperties() {
            this.newProperties = new EntityCollection(
                this.blogProperties.source,
                this.blogProperties.entity,
                this.blogProperties.context,
                Criteria.fromCriteria(this.blogProperties.criteria),
                this.blogProperties,
                this.blogProperties.total,
                this.blogProperties.aggregations,
            );
        },

        onCancelAddPropertiesModal() {
            this.closeAddPropertiesModal();
        },

        onSaveAddPropertiesModal(newProperties) {
            this.closeAddPropertiesModal();

            if (newProperties.length <= 0) {
                return;
            }

            this.blogProperties.splice(0, this.blogProperties.length, ...newProperties);
        },

        async loadAllPropertyGroups(criteria) {
            const initialResult = await this.groupRepository.search(criteria);
            const totalGroups = initialResult.total ?? initialResult.length ?? 0;
            const limit = initialResult.length || criteria.limit || 25;

            const totalPages = Math.ceil(totalGroups / limit);

            const promises = [];
            for (let page = 2; page <= totalPages; page++) {
                const nextCriteria = Criteria.fromCriteria(criteria).setPage(page).setLimit(limit);
                promises.push(this.groupRepository.search(nextCriteria));
            }

            const results = await Promise.all(promises);

            return [initialResult, ...results].flatMap((result) => result);
        },
    },
};

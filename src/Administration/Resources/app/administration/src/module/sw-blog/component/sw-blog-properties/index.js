/*
 * @sw-package inventory
 */

import template from './sw-blog-properties.html.twig';
import './sw-blog-properties.scss';

const { Context } = Contena;
const { Criteria, EntityCollection } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        isAssociation: {
            type: Boolean,
            required: false,
            default: true,
        },
        showInheritanceSwitcher: {
            type: Boolean,
            required: false,
            default: true,
        },
        emptyStateTitle: {
            type: String,
            required: false,
            default: null,
        },
        emptyStateDescription: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            groupIds: [],
            properties: [],
            isPropertiesLoading: false,
            searchTerm: null,
            showAddPropertiesModal: false,
            newProperties: [],
            propertiesAvailable: true,
        };
    },

    computed: {
        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        propertyOptionRepository() {
            return this.repositoryFactory.create('property_group_option');
        },

        propertyGroupCriteria() {
            const criteria = new Criteria(1, 10);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));
            criteria.addFilter(Criteria.equalsAny('id', this.groupIds));

            if (this.searchTerm) {
                criteria.setTerm(this.searchTerm);
            }

            const optionIds = this.blogProperties.getIds();

            criteria.getAssociation('options').addFilter(Criteria.equalsAny('id', optionIds));
            criteria.addFilter(Criteria.equalsAny('options.id', optionIds));

            return criteria;
        },

        propertyExistsCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.setTotalCountMode(0);

            return criteria;
        },

        propertyColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-blog.properties.columnProperty',
                    sortable: false,
                    routerLink: 'sw.property.detail',
                },
                {
                    property: 'values',
                    label: 'sw-blog.properties.columnValue',
                    sortable: false,
                    multiLine: true,
                },
            ];
        },

        blog() {
            return Contena.Store.get('swProductDetail').blog;
        },

        parentProduct() {
            return Contena.Store.get('swProductDetail').parentProduct;
        },

        isLoading() {
            return Contena.Store.get('swProductDetail').isLoading;
        },

        isChild() {
            return Contena.Store.get('swProductDetail').isChild;
        },

        blogProperties() {
            return this.isChild && this.blog?.properties?.length <= 0
                ? this.parentProduct.properties
                : this.blog.properties;
        },

        assetFilter() {
            return Contena.Filter.getByName('asset');
        },

        blogHasProperties() {
            return this.blog?.id && this.groupIds.length > 0;
        },
    },

    watch: {
        blogProperties: {
            immediate: true,
            handler(newValue) {
                if (!newValue) {
                    return;
                }
                this.getGroupIds();
                this.getProperties();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.checkIfPropertiesExists();
        },

        getGroupIds() {
            if (!this.blog?.id) {
                return;
            }

            this.groupIds = this.blogProperties.reduce((accumulator, { groupId }) => {
                if (accumulator.indexOf(groupId) < 0) {
                    accumulator.push(groupId);
                }

                return accumulator;
            }, []);
        },

        getProperties() {
            if (!this.blog?.id || this.groupIds.length <= 0) {
                this.properties = [];
                this.searchTerm = null;
                return Promise.resolve();
            }

            this.isPropertiesLoading = true;
            return this.propertyGroupRepository
                .search(this.propertyGroupCriteria, Context.api)
                .then((properties) => {
                    this.properties = properties;
                })
                .catch(() => {
                    this.properties = [];
                })
                .finally(() => {
                    this.isPropertiesLoading = false;
                });
        },

        onDeletePropertyValue(propertyValue) {
            this.blogProperties.remove(propertyValue.id);
            this.getProperties();
        },

        onDeleteProperty(property) {
            if (this.$refs.entityListing) {
                this.$refs.entityListing.deleteId = null;
            }

            this.$nextTick(() => {
                this.blogProperties
                    .filter(({ groupId }) => {
                        return groupId === property.id;
                    })
                    .forEach(({ id }) => {
                        this.blogProperties.remove(id);
                    });

                this.$refs.entityListing.resetSelection();
                this.getProperties();
            });
        },

        onDeleteProperties() {
            if (this.$refs.entityListing) {
                this.$refs.entityListing.showBulkDeleteModal = false;
            }

            this.$nextTick(() => {
                const properties = { ...this.$refs.entityListing.selection };

                Object.values(properties).forEach((property) => {
                    property.options.forEach((value) => {
                        this.blogProperties.remove(value.id);
                    });
                });
                this.$refs.entityListing.resetSelection();
                this.getProperties();
            });
        },

        onChangeSearchTerm(searchTerm) {
            this.searchTerm = searchTerm;
            return this.getProperties();
        },

        turnOnAddPropertiesModal() {
            if (!this.propertiesAvailable) {
                this.$router.push({ name: 'sw.property.index' });
            } else {
                this.updateNewProperties();
                this.showAddPropertiesModal = true;
            }
        },

        turnOffAddPropertiesModal() {
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
            this.turnOffAddPropertiesModal();
        },

        onSaveAddPropertiesModal(newProperties, callbackUpdateCurrentValues) {
            this.turnOffAddPropertiesModal();

            if (newProperties.length <= 0) {
                return;
            }

            if (typeof callbackUpdateCurrentValues === 'function') {
                callbackUpdateCurrentValues.bind(this)(newProperties);
            }
        },

        checkIfPropertiesExists() {
            this.propertyOptionRepository.searchIds(this.propertyExistsCriteria).then((res) => {
                this.propertiesAvailable = res.total > 0;
            });
        },
    },
};

import { computed } from 'vue';

import template from './sw-blog-stream-detail.html.twig';
import './sw-blog-stream-detail.scss';

const { Mixin, Context } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();
const { Criteria } = Contena.Data;

/**
 * @sw-package inventory
 * @private
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'blogStreamConditionService',
        'acl',
        'customFieldDataProviderService',
        'blogTypeService',
    ],

    provide() {
        return {
            blogCustomFields: computed(() => this.blogCustomFields),
            blogTypes: computed(() => this.blogTypes),
        };
    },

    beforeRouteLeave(to, from, next) {
        if (this.showModalPreview) {
            this.showModalPreview = false;
            this.$nextTick(() => next());
            return;
        }

        next();
    },

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('blogStream'),
        Mixin.getByName('sw-inline-snippet'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    props: {
        blogStreamId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: false,
            isSaving: false,
            customFieldsLoading: false,
            isSaveSuccessful: false,
            blogStream: {
                name: null,
                description: null,
            },
            blogStreamFilters: null,
            blogStreamFiltersTree: null,
            deletedProductStreamFilters: [],
            blogCustomFields: {},
            showModalPreview: false,
            languageId: null,
            customFieldSets: null,
            blogTypes: ['physical', 'digital'],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.blogStream, 'name');
        },

        blogStreamRepository() {
            return this.repositoryFactory.create('blog_stream');
        },

        blogStreamFiltersRepository() {
            if (!this.blogStream?.filters?.entity || !this.blogStream?.filters?.source) {
                return null;
            }

            return this.repositoryFactory.create(this.blogStream.filters.entity, this.blogStream.filters.source);
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        tooltipSave() {
            if (!this.acl.can('blog_stream.editor')) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    appearance: 'dark',
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        isSystemLanguage() {
            return this.languageId === Context.api.systemLanguageId;
        },

        nameRequired() {
            return this.isSystemLanguage;
        },

        ...mapPropertyErrors('blogStream', ['name']),

        showCustomFields() {
            return !!this.blogStream?.id && this.customFieldSets && this.customFieldSets.length > 0;
        },

        blogStreamIndexingEnabled() {
            return Context.app.blogStreamIndexingEnabled ?? true;
        },

        deprecatedFiltersInUse() {
            if (!this.blogStreamFiltersTree) {
                return [];
            }

            return this.blogStreamConditionService.getDeprecationsInTree(this.blogStreamFiltersTree);
        },
    },

    watch: {
        blogStreamId: {
            immediate: true,
            handler() {
                if (!this.blogStreamId) {
                    this.createProductStream();
                    return;
                }

                this.isLoading = true;
                this.loadEntityData(this.blogStreamId).then(() => {
                    this.isLoading = false;
                });
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Contena.ExtensionAPI.publishData({
                id: 'sw-blog-stream-detail__blogStream',
                path: 'blogStream',
                scope: this,
            });
            this.languageId = Context.api.languageId;

            const promises = [this.loadCustomFieldSets(), this.loadProductTypes()];

            if (this.blogStreamId) {
                promises.push(this.getProductCustomFields());
            }

            Promise.all(promises).then(() => {
                Promise.resolve();
            });
        },

        loadCustomFieldSets() {
            this.customFieldDataProviderService.getCustomFieldSets('blog_stream').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        loadProductTypes() {
            this.blogTypeService.fetchProductTypes().then((types) => {
                this.blogTypes = types;
            });
        },

        createProductStream() {
            this.getProductCustomFields().then(() => {
                Context.api.languageId = Context.api.systemLanguageId;
                this.blogStream = this.blogStreamRepository.create(Context.api);
                this.blogStream.displayAsGroup = true;
                this.blogStreamFilters = this.blogStream.filters;
            });
        },

        loadEntityData(blogStreamId) {
            return this.blogStreamRepository.get(blogStreamId, Context.api).then((blogStream) => {
                this.blogStream = blogStream;
                return this.loadFilters();
            });
        },

        loadFilters(collection = null) {
            if (collection === null) {
                const filterCriteria = new Criteria(1, 25);
                filterCriteria.addFilter(Criteria.equals('blogStreamId', this.blogStreamId));

                return this.blogStreamFiltersRepository.search(filterCriteria, Context.api).then((blogFilter) => {
                    return this.loadFilters(blogFilter);
                });
            }

            if (collection.length >= collection.total) {
                this.blogStreamFilters = collection;
                return Promise.resolve();
            }

            const nextCriteria = Criteria.fromCriteria(collection.criteria);
            nextCriteria.page += 1;

            return this.blogStreamFiltersRepository.search(nextCriteria, collection.context).then((nextFilters) => {
                collection.push(...nextFilters);
                collection.criteria = nextFilters.criteria;
                collection.total = nextFilters.total;

                return this.loadFilters(collection);
            });
        },

        abortOnLanguageChange() {
            return this.blogStreamRepository.hasChanges(this.blogStream);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            this.languageId = languageId;

            this.isLoading = true;
            this.loadEntityData(this.blogStream.id).then(() => {
                this.isLoading = false;
            });
        },

        onDuplicate() {
            return this.onSave().then(() => {
                const behavior = {
                    cloneChildren: true,
                    overwrites: {
                        name: `${this.blogStream.name || this.blogStream.translated.name} ${this.$t('global.default.copy')}`,
                    },
                };

                this.isLoading = true;

                return this.blogStreamRepository
                    .clone(this.blogStream.id, behavior, Contena.Context.api)
                    .then((clone) => {
                        const route = {
                            name: 'sw.blog.stream.detail',
                            params: { id: clone.id },
                        };

                        this.$router.push(route);
                    })
                    .catch(() => {
                        this.isLoading = false;

                        this.createNotificationError({
                            message: this.$t('global.notification.unspecifiedSaveErrorMessage'),
                        });
                    });
            });
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isSaving = true;

            if (this.blogStream.isNew()) {
                this.blogStream.filters = this.blogStreamFiltersTree;
                return this.saveProductStream()
                    .then(() => {
                        this.$router.push({
                            name: 'sw.blog.stream.detail',
                            params: { id: this.blogStream.id },
                        });
                        this.isSaveSuccessful = true;
                        this.isSaving = false;
                    })
                    .catch(() => {
                        this.showErrorNotification();
                        this.isSaving = false;
                    });
            }

            return this.blogStreamRepository
                .save(this.blogStream, Context.api)
                .then(this.syncProductStreamFilters)
                .then(() => {
                    return this.loadEntityData(this.blogStream.id);
                })
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.isSaving = false;
                })
                .catch(() => {
                    this.isSaving = false;
                    this.showErrorNotification();
                });
        },

        showErrorNotification() {
            this.createNotificationError({
                message: this.$t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
            });
        },

        saveProductStream() {
            return this.blogStreamRepository.save(this.blogStream, Context.api);
        },

        syncProductStreamFilters() {
            return this.blogStreamFiltersRepository.sync(this.blogStreamFiltersTree, Context.api).then(() => {
                if (this.deletedProductStreamFilters.length > 0) {
                    return this.blogStreamFiltersRepository
                        .syncDeleted(this.deletedProductStreamFilters, Context.api)
                        .then(() => {
                            this.deletedProductStreamFilters = [];
                        });
                }

                return Promise.resolve();
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.blog.stream.index' });
        },

        openModalPreview() {
            this.showModalPreview = true;
        },
        closeModalPreview() {
            this.showModalPreview = false;
        },

        getProductCustomFields() {
            this.customFieldsLoading = true;
            const customFieldsCriteria = new Criteria(1, null);
            customFieldsCriteria.addFilter(Criteria.equals('relations.entityName', 'blog'));

            const loadingPromises = [];
            return this.customFieldSetRepository.search(customFieldsCriteria, Context.api).then((customFieldSets) => {
                const singleCriteria = new Criteria(1, null);
                singleCriteria.addAssociation('customFields').addAssociation('relations');

                customFieldSets.forEach((customFieldSet) => {
                    loadingPromises.push(
                        this.customFieldSetRepository.get(customFieldSet.id, Context.api, singleCriteria).then((set) => {
                            const customFields = set.customFields.reduce((acc, customField) => {
                                acc[customField.name] = this.mapCustomFieldType({
                                    type: customField.type,
                                    value: `customFields.${customField.name}`,
                                    label: this.getCustomFieldLabel(customField),
                                    config: customField.config,
                                });
                                return acc;
                            }, {});
                            Object.assign(this.blogCustomFields, customFields);
                        }),
                    );
                });

                Promise.all(loadingPromises).then(() => {
                    this.customFieldsLoading = false;
                });
            });
        },

        getCustomFieldLabel(customField) {
            return this.getInlineSnippet(customField.config.label) || customField.name;
        },

        mapCustomFieldType(customField) {
            switch (customField.type) {
                case 'bool':
                    customField.type = 'boolean';
                    break;
                case 'html':
                case 'text':
                    customField.type = 'string';
                    break;
                case 'date':
                    customField.type = 'string';
                    customField.format = 'date-time';
                    break;
                default:
                    break;
            }
            return customField;
        },

        updateFilterTree({ conditions, deletedIds }) {
            this.blogStreamFiltersTree = conditions;
            this.deletedProductStreamFilters = [...this.deletedProductStreamFilters, ...deletedIds];
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                message: this.$t('sw-privileges.tooltip.warning'),
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
            };
        },
    },
};

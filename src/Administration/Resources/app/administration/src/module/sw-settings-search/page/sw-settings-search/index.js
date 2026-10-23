/**
 * @sw-package inventory
 */
import template from './sw-settings-search.html.twig';

const { Mixin } = Contena;
const { EntityCollection, Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    mixins: [Mixin.getByName('notification')],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSaveSearchSettings',
        },
        ESCAPE: 'onCancel',
    },

    data: () => {
        return {
            blogSearchConfigs: {
                andLogic: true,
                minSearchLength: 2,
            },
            isLoading: false,
            currentSalesChannelId: null,
            searchTerms: '',
            searchResults: null,
            defaultConfig: null,
            isSaveSuccessful: false,
            nextRoute: null,
            isDisplayingLeavePageWarning: false,
            leaveConfirmation: false,
            isEditing: false,
        };
    },

    computed: {
        blogSearchRepository() {
            return this.repositoryFactory.create('blog_search_config');
        },

        blogSearchFieldRepository() {
            return this.repositoryFactory.create('blog_search_config_field');
        },

        blogSearchConfigsCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('configFields');
            criteria.addFilter(Criteria.equals('languageId', Contena.Context.api.languageId));
            return criteria;
        },

        blogDefaultConfigsCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('configFields');
            criteria.addFilter(Criteria.equals('languageId', Contena.Context.api.systemLanguageId));
            return criteria;
        },

        allowSave() {
            return this.acl.can('blog_search_config.editor') || this.acl.can('blog_search_config.creator');
        },

        settingsSearchTabs() {
            const createRouteTab = (label, routeName, callback) => {
                const route = {
                    name: routeName,
                };

                return {
                    label: this.$t(label),
                    name: route.name,
                    onClick: () => {
                        callback?.();
                        void this.$router.push(route);
                    },
                };
            };

            return [
                createRouteTab('sw-settings-search.page.generalTab', 'sw.settings.search.index.general', () => {
                    this.onTabChange();
                }),
                createRouteTab('sw-settings-search.page.liveSearchTab', 'sw.settings.search.index.liveSearch'),
            ];
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },
    },

    created() {
        this.createdComponent();
    },

    beforeRouteUpdate(to, from, next) {
        this.unsavedDataLeaveHandler(to, from, next);
    },

    beforeRouteLeave(to, from, next) {
        this.unsavedDataLeaveHandler(to, from, next);
    },

    methods: {
        createdComponent() {
            this.getDefaultSearchConfig();
            this.getProductSearchConfigs();

            Contena.ExtensionAPI.publishData({
                id: 'sw-settings-search__defaultConfig',
                path: 'defaultConfig',
                scope: this,
            });

            Contena.ExtensionAPI.publishData({
                id: 'sw-settings-search__productSearchConfigs',
                path: 'blogSearchConfigs',
                scope: this,
            });
        },

        getProductSearchConfigs() {
            this.isLoading = true;
            this.blogSearchRepository
                .search(this.blogSearchConfigsCriteria)
                .then((items) => {
                    if (!items.total) {
                        this.onSaveDefaultSearchConfig();
                    } else {
                        this.blogSearchConfigs = items.first();
                    }
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        getDefaultSearchConfig() {
            this.blogSearchRepository
                .search(this.blogDefaultConfigsCriteria)
                .then((items) => {
                    this.defaultConfig = items.first();
                })
                .catch((err) => {
                    this.createNotificationError({
                        message: err.message,
                    });
                });
        },

        createDefaultSearchConfig() {
            const defaultConfig = this.blogSearchRepository.create();
            defaultConfig.andLogic = this.defaultConfig.andLogic;
            defaultConfig.minSearchLength = this.defaultConfig.minSearchLength;
            defaultConfig.excludedTerms = [];
            defaultConfig.languageId = Contena.Context.api.languageId;
            return defaultConfig;
        },

        createConfigFields() {
            if (!this.defaultConfig || !this.defaultConfig.configFields.length) {
                return null;
            }

            const configFieldCollection = new EntityCollection(
                this.blogSearchFieldRepository.route,
                this.blogSearchFieldRepository.entityName,
                Contena.Context.api,
            );
            this.defaultConfig.configFields.forEach((item) => {
                const newConfigField = this.blogSearchFieldRepository.create();
                newConfigField.field = item.field;
                newConfigField.ranking = item.ranking;
                newConfigField.searchable = item.searchable;
                newConfigField.tokenize = item.tokenize;
                newConfigField.customFieldId = null;
                newConfigField.searchConfigId = this.blogSearchConfigs.id;
                configFieldCollection.add(newConfigField);
            });
            return configFieldCollection;
        },

        onSaveDefaultSearchConfig() {
            this.blogSearchConfigs = this.createDefaultSearchConfig();
            this.blogSearchConfigs.configFields = this.createConfigFields();
            this.blogSearchRepository
                .save(this.blogSearchConfigs)
                .then(() => {
                    this.getProductSearchConfigs();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-search.notification.saveError'),
                    });
                });
        },

        onChangeLanguage() {
            this.getDefaultSearchConfig();
            this.getProductSearchConfigs();
        },

        onTabChange() {
            this.getProductSearchConfigs();
        },

        onSaveSearchSettings() {
            this.isLoading = true;
            this.blogSearchRepository
                .save(this.blogSearchConfigs)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-search.notification.saveSuccess'),
                    });
                    this.getProductSearchConfigs();
                    this.isSaveSuccessful = true;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-search.notification.saveError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                    this.isEditing = false;
                });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        fetchSalesChannels() {
            this.channelRepository.search(new Criteria(1, 25)).then((response) => {
                this.channels = response;
            });
        },

        unsavedDataLeaveHandler(to, from, next) {
            if (this.leaveConfirmation) {
                this.leaveConfirmation = false;
                next();

                return;
            }

            if (this.isEditing) {
                this.isDisplayingLeavePageWarning = true;
                this.nextRoute = to;
                next(false);
            } else {
                next();
            }
        },

        onSalesChannelChanged(channelId) {
            this.currentSalesChannelId = channelId;
        },

        onLiveSearchResultsChanged({ searchTerms, searchResults }) {
            this.searchTerms = searchTerms;
            this.searchResults = searchResults;
        },

        onEditChanged(isEditing) {
            this.isEditing = isEditing;
        },

        onConfirmLeave() {
            this.leaveConfirmation = true;
            this.isDisplayingLeavePageWarning = false;
            this.isEditing = false;

            this.$nextTick(() => {
                this.$router.push({
                    name: this.nextRoute.name,
                    params: this.nextRoute.params,
                });
            });
        },

        onCloseLeaveModal() {
            this.isDisplayingLeavePageWarning = false;
        },

        onCancelLeaveModal() {
            this.isDisplayingLeavePageWarning = false;
        },
    },
};

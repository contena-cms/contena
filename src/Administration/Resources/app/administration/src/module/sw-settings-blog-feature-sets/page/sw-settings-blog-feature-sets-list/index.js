/**
 * @sw-package inventory
 */
import FeatureGridTranslationService from 'src/module/sw-settings-blog-feature-sets/service/feature-grid-translation.service';
import template from './sw-settings-blog-feature-sets-list.html.twig';

const { Mixin } = Contena;
const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data() {
        return {
            entityName: 'blog_feature_set',
            blogFeatureSets: null,
            sortBy: 'blog_feature_set.name',
            isLoading: false,
            sortDirection: 'ASC',
            naturalSorting: true,
            showDeleteModal: false,
            translationService: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        blogFeatureSetsRepository() {
            return this.repositoryFactory.create('blog_feature_set');
        },

        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        featureGridTranslationService() {
            if (this.translationService === null) {
                this.translationService = new FeatureGridTranslationService(
                    this,
                    this.propertyGroupRepository,
                    this.customFieldRepository,
                );
            }

            return this.translationService;
        },
    },

    methods: {
        metaInfo() {
            return {
                title: this.$createTitle(),
            };
        },

        getList() {
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'name';

            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            this.blogFeatureSetsRepository
                .search(criteria)
                .then((items) => {
                    this.total = items.total;
                    this.blogFeatureSets = items;

                    return items;
                })
                .then((items) => {
                    const allFeatures = items.reduce((features, featureSet) => {
                        if (featureSet.features && featureSet.features.length) {
                            features = [...features, ...(featureSet.features || [])];
                        }
                        return features;
                    }, []);

                    return Promise.all([
                        this.featureGridTranslationService.fetchPropertyGroupEntities(allFeatures),
                        this.featureGridTranslationService.fetchCustomFieldEntities(allFeatures),
                    ]);
                })
                .then(() => {
                    this.isLoading = false;
                });
        },

        onChangeLanguage(languageId) {
            Contena.Store.get('context').api.languageId = languageId;
            this.getList();
        },

        onInlineEditSave(promise, blogFeatureSets) {
            promise
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t(
                            'sw-settings-blog-feature-sets.detail.messageSaveSuccess',
                            {
                                name: blogFeatureSets.name,
                            },
                            0,
                        ),
                    });
                })
                .catch(() => {
                    this.getList();
                    this.createNotificationError({
                        message: this.$t('sw-settings-blog-feature-sets.detail.messageSaveError'),
                    });
                });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.blogFeatureSetsRepository.delete(id).then(() => {
                this.getList();
            });
        },

        getProductFeatureSetsColumns() {
            return [
                {
                    property: 'name',
                    inlineEdit: 'string',
                    label: 'sw-settings-blog-feature-sets.list.columnTemplate',
                    routerLink: 'sw.settings.blog.feature.sets.detail',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'description',
                    inlineEdit: 'string',
                    label: 'sw-settings-blog-feature-sets.list.columnDescription',
                    allowResize: true,
                },
                {
                    property: 'features',
                    label: 'sw-settings-blog-feature-sets.list.columnValues',
                    allowResize: true,
                },
            ];
        },

        renderFeaturePreview(features) {
            if (!features.length) {
                return null;
            }

            const preview = features
                .slice(0, 4)
                .map((feature) => this.featureGridTranslationService.getNameTranslation(feature))
                .join(', ');

            return features.length > 4 ? `${preview}, ...` : preview;
        },
    },
};

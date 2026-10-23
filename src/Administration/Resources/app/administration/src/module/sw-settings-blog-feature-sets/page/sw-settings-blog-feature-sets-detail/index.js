/**
 * @sw-package inventory
 */
import template from './sw-settings-blog-feature-sets-detail.html.twig';

const { Mixin } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    props: {
        blogFeatureSetId: {
            type: String,
            required: false,
            default: null,
        },
    },

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            blogFeatureSet: {},
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.blogFeatureSet, 'name');
        },

        blogFeatureSetsRepository() {
            return this.repositoryFactory.create('blog_feature_set');
        },

        tooltipSave() {
            if (!this.acl.can('blog_feature_sets.editor')) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('blog_feature_sets.editor'),
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

        ...mapPropertyErrors('blogFeatureSet', ['name', 'description', 'features.id']),
    },

    watch: {
        blogFeatureSetId() {
            if (!this.blogFeatureSetId) {
                this.createdComponent();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            if (this.blogFeatureSetId) {
                this.blogFeatureSetsRepository.get(this.blogFeatureSetId).then((blogFeatureSet) => {
                    if (blogFeatureSet.features && !blogFeatureSet.features.length) {
                        blogFeatureSet.features = [];
                    }

                    this.blogFeatureSet = blogFeatureSet;
                    this.isLoading = false;
                });
                return;
            }

            this.blogFeatureSet = this.blogFeatureSetsRepository.create();
            this.isLoading = false;
        },

        loadEntityData() {
            this.blogFeatureSetsRepository.get(this.blogFeatureSet.id).then((blogFeatureSet) => {
                if (blogFeatureSet.features && !blogFeatureSet.features.length) {
                    blogFeatureSet.features = [];
                }

                this.blogFeatureSet = blogFeatureSet;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.blogFeatureSetsRepository
                .save(this.blogFeatureSet)
                .then(() => {
                    this.isSaveSuccessful = true;
                    if (!this.blogFeatureSetId) {
                        this.$router.push({
                            name: 'sw.settings.blog.feature.sets.detail',
                            params: { id: this.blogFeatureSet.id },
                        });
                    }
                })
                .then(() => {
                    this.loadEntityData();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-settings-blog-feature-sets.detail.notificationErrorMessage'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onCancel() {
            this.$router.push({
                name: 'sw.settings.blog.feature.sets.index',
            });
        },

        abortOnLanguageChange() {
            return this.blogFeatureSetsRepository.hasChanges(this.blogFeatureSet);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },
    },
};

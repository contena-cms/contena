/**
 * @sw-package inventory
 */
import FeatureGridTranslationService from 'src/module/sw-settings-blog-feature-sets/service/feature-grid-translation.service';
import template from './sw-settings-blog-feature-sets-values-card.html.twig';
import './sw-settings-blog-feature-sets-values-card.scss';

const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        blogFeatureSet: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            valuesLoading: false,
            cardLoading: false,
            values: [],
            selection: null,
            deleteButtonDisabled: true,
            term: '',
            showModal: false,
            currentValue: null,
            translationService: null,
        };
    },

    computed: {
        blogFeatureSetRepository() {
            return this.repositoryFactory.create('blog_feature_set');
        },

        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        valuesEmpty() {
            return this.values.length === 0;
        },

        valuesCardClasses() {
            return {
                'is--empty': this.valuesEmpty,
            };
        },

        blogFeatureSetCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('blog_feature_set.id', this.blogFeatureSet.id));

            return criteria;
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

        /** @deprecated tag:v6.8.0 - Will be removed, use Contena.Filter.getByName('asset') instead. */
        assetFilter() {
            return Contena.Filter.getByName('asset');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        onAddField() {
            this.onShowFeatureModal();
        },

        onGridSelectionChanged(selection, selectionCount) {
            this.selection = selection;
            this.deleteButtonDisabled = selectionCount <= 0;
        },

        onSearch() {
            this.doSearch();
        },

        doSearch() {
            if (!this.term) {
                this.getList();
            }

            this.values = this.blogFeatureSet.features.filter((item) => {
                return item.name.match(this.term) || item.type.match(this.term);
            });
        },

        getList() {
            this.valuesLoading = true;
            this.values = [];

            if (this.blogFeatureSet.features) {
                this.values = this.blogFeatureSet.features;

                if (this.term) {
                    this.doSearch();
                }
            }

            // Initially sort the features by position, further sorting will be handled by the grid component
            this.values.sort((a, b) => a.position - b.position);

            Promise.all([
                this.featureGridTranslationService.fetchPropertyGroupEntities(this.values),
                this.featureGridTranslationService.fetchCustomFieldEntities(this.values),
            ]).then(() => {
                this.valuesLoading = false;
            });
        },

        onModalClose() {
            this.showModal = false;
            this.currentValue = null;
            this.$nextTick(() => this.getList());
        },

        onShowFeatureModal() {
            this.showModal = true;
        },

        onDeleteFields() {
            if (this.selection) {
                const deletedKeys = Object.keys(this.selection);

                this.blogFeatureSet.features = this.blogFeatureSet.features.filter((feature) => {
                    return !deletedKeys.includes(feature.id);
                });

                this.resetPositions();
                this.getList();
            }
        },

        onPositionChange(features) {
            this.blogFeatureSet.features = features;
        },

        resetPositions() {
            this.blogFeatureSet.features.forEach((feature, index) => {
                feature.position = index + 1;
            });
        },

        getColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-settings-blog-feature-sets.valuesCard.labelValue',
                    primary: true,
                },
                {
                    property: 'type',
                    label: 'sw-settings-blog-feature-sets.valuesCard.labelType',
                },
                {
                    property: 'position',
                    label: 'sw-settings-blog-feature-sets.valuesCard.labelPosition',
                },
            ];
        },
    },
};

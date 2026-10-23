/**
 * @sw-package inventory
 */
import template from './sw-settings-listing-option-base.html.twig';
import './sw-settings-listing-option-base.scss';

const { Mixin } = Contena;
const { Criteria } = Contena.Data;
const { ContenaError } = Contena.Classes;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'systemConfigApiService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            blogSortingEntity: null,
            toBeDeletedCriteria: null,
            customFieldOptions: [],
            customFields: [],
            defaultSortingId: null,
            sortingOptionTechnicalNameError: null,
            sortingOptionLabelError: null,
        };
    },

    computed: {
        blogSortingRepository() {
            return this.repositoryFactory.create('blog_sorting');
        },

        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        smartBarHeading() {
            return this.blogSortingEntity && this.blogSortingEntity.label
                ? this.blogSortingEntity.label
                : this.$t('sw-settings-listing.base.smartBarTitle');
        },

        isGeneralCardLoading() {
            return !this.blogSortingEntity;
        },

        customFieldCriteria() {
            return new Criteria(1, 25);
        },

        /**
         * @deprecated tag:v6.8.0 - will be removed, is not used anymore
         */
        blogSortingEntityCriteria() {
            return new Criteria(1, 25);
        },

        isSaveButtonDisabled() {
            return (
                !this.blogSortingEntity ||
                this.blogSortingEntity.fields.length <= 0 ||
                this.blogSortingEntity.fields.some((field) => !field.field || field.field === 'customField')
            );
        },

        isDefaultSorting() {
            return this.defaultSortingId === this.blogSortingEntity.id;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Promise.all([this.fetchProductSortingEntity(), this.fetchCustomFields(), this.fetchDefaultSorting()]);
        },

        async fetchProductSortingEntity() {
            const blogSortingEntityId = this.getProductSortingEntityId();
            const blogSortingEntity = await this.blogSortingRepository.get(blogSortingEntityId);

            if (!Array.isArray(blogSortingEntity.fields)) {
                blogSortingEntity.fields = [];
            }

            this.blogSortingEntity = blogSortingEntity;
        },

        fetchCustomFields() {
            return this.customFieldRepository.search(this.customFieldCriteria).then((response) => {
                this.customFields = response;
            });
        },

        fetchDefaultSorting() {
            this.systemConfigApiService.getValues('core.listing').then((response) => {
                this.defaultSortingId = response['core.listing.defaultSorting'];
            });
        },

        getProductSortingEntityId() {
            return this.$route.params.id.toLowerCase();
        },

        async isValidSortingOption() {
            if (!this.blogSortingEntity.key) {
                this.sortingOptionTechnicalNameError = new ContenaError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
            }

            if (await this.searchForAlreadyExistingKey(this.blogSortingEntity.key)) {
                this.sortingOptionTechnicalNameError = new ContenaError({
                    code: 'DUPLICATED_NAME',
                });
            }

            if (!this.blogSortingEntity.label) {
                this.sortingOptionLabelError = new ContenaError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });
            }

            return !(this.sortingOptionTechnicalNameError || this.sortingOptionLabelError);
        },

        async searchForAlreadyExistingKey(key) {
            if (!key) {
                return false;
            }

            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('key', key));

            const response = await this.blogSortingRepository.search(criteria);

            if (!response.first()) {
                return false;
            }

            return response.first().id !== this.blogSortingEntity.id;
        },

        async saveProductSorting() {
            if (await this.isValidSortingOption()) {
                return this.blogSortingRepository.save(this.blogSortingEntity);
            }

            return Promise.reject();
        },

        onSave() {
            this.sortingOptionTechnicalNameError = null;
            this.sortingOptionLabelError = null;

            this.transformCustomFieldCriterias();

            this.blogSortingEntity.fields = this.blogSortingEntity.fields.filter((field) => {
                return field.field !== 'customField';
            });

            return this.saveProductSorting()
                .then(() => {
                    const sortingOptionName = this.blogSortingEntity.label;

                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-listing.base.notification.saveSuccess', { sortingOptionName }),
                    });
                })
                .catch(() => {
                    const sortingOptionName = this.blogSortingEntity.label;

                    this.createNotificationError({
                        message: this.$t('sw-settings-listing.base.notification.saveError', { sortingOptionName }),
                    });
                });
        },

        getCriteriaTemplate(fieldName) {
            return {
                field: fieldName,
                order: 'asc',
                priority: 1,
                naturalSorting: 0,
            };
        },

        onDeleteCriteria(toBeRemovedItem) {
            this.toBeDeletedCriteria = toBeRemovedItem;
        },

        onConfirmDeleteCriteria() {
            // filter out criteria
            this.blogSortingEntity.fields = this.blogSortingEntity.fields.filter((currentCriteria) => {
                return currentCriteria.field !== this.toBeDeletedCriteria.field;
            });

            // save blog sorting entity
            this.saveProductSorting().finally(() => {
                // close delete modal
                this.toBeDeletedCriteria = null;
            });
        },

        onAddCriteria(fieldName) {
            if (!fieldName) {
                return;
            }

            const newCriteria = this.getCriteriaTemplate(fieldName);

            if (!this.blogSortingEntity.fields) {
                this.blogSortingEntity.fields = [];
            }

            this.blogSortingEntity.fields.push(newCriteria);
        },

        onCancelEditCriteria(item) {
            if (this.getProductSortingEntityId()) {
                this.fetchProductSortingEntity();

                return;
            }

            this.blogSortingEntity.fields = this.blogSortingEntity.fields.filter((currentCriteria) => {
                return currentCriteria.field !== item.field;
            });
        },

        isCriteriaACustomField(technicalName) {
            return this.customFields.some((currentCustomField) => {
                return currentCustomField.name === technicalName;
            });
        },

        transformCustomFieldCriterias() {
            this.blogSortingEntity.fields = this.blogSortingEntity.fields.map((currentField) => {
                if (!this.isCriteriaACustomField(currentField.field)) {
                    return currentField;
                }

                currentField.field = `customFields.${currentField.field}`;

                return currentField;
            });
        },

        onChangeLanguage() {
            this.fetchProductSortingEntity();
        },
    },
};

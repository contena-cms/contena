/**
 * @sw-package inventory
 */
import '../sw-settings-listing-option-base';
import template from './sw-settings-listing-option-create.html.twig';

const { Criteria } = Contena.Data;
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    computed: {
        smartBarHeading() {
            return this.blogSortingEntity && this.blogSortingEntity.label
                ? this.blogSortingEntity.label
                : this.$t('sw-settings-listing.create.smartBarTitle');
        },

        isNewProductSorting() {
            return !this.blogSortingEntity || this.blogSortingEntity._isNew;
        },

        urlKeyCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('key', this.blogSortingEntity.key));
            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchCustomFields().then(() => {
                this.blogSortingEntity = this.createProductSortingEntity();
                Contena.Store.get('context').resetLanguageToDefault();
            });
        },

        createProductSortingEntity() {
            const blogSortingEntity = this.blogSortingRepository.create();
            blogSortingEntity.fields = [];
            blogSortingEntity.priority = 1;
            blogSortingEntity.active = false;

            return blogSortingEntity;
        },

        async onSave() {
            this.sortingOptionTechnicalNameError = null;
            this.sortingOptionLabelError = null;

            this.transformCustomFieldCriterias();

            this.blogSortingEntity.fields = this.blogSortingEntity.fields.filter((field) => {
                return field.field !== 'customField';
            });

            return this.saveProductSorting()
                .then((response) => {
                    const encodedResponse = JSON.parse(response.config.data);

                    this.$router.push({
                        name: 'sw.settings.listing.edit',
                        params: { id: encodedResponse.id },
                    });

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

        onConfirmDeleteCriteria() {
            // filter out criteria
            this.blogSortingEntity.fields = this.blogSortingEntity.fields.filter((currentCriteria) => {
                return currentCriteria.field !== this.toBeDeletedCriteria.field;
            });

            // close delete modal
            this.toBeDeletedCriteria = null;
        },
    },
};

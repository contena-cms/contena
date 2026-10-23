import template from './sw-user-detail-base.html.twig';

/**
 * @sw-package checkout
 */

const { Criteria } = Contena.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        // @deprecated tag:v6.8.0 - Only used by customFieldSetRepository; will be removed with it.
        'repositoryFactory',
        'customFieldDataProviderService',
    ],

    props: {
        user: {
            type: Object,
            required: true,
        },

        userEditMode: {
            type: Boolean,
            required: true,
            default: false,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            userCustomFieldSets: null,
        };
    },

    computed: {
        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        // @deprecated tag:v6.8.0 - Use customFieldDataProviderService instead.
        customFieldSetCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equals('relations.entityName', 'user'));
            criteria.getAssociation('customFields').addSorting(Criteria.sort('config.customFieldPosition', 'ASC'));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customFieldDataProviderService.getCustomFieldSets('user').then((customFieldSets) => {
                this.userCustomFieldSets = customFieldSets;
            });
        },
    },
};

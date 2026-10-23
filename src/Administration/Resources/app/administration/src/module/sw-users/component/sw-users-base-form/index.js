import template from './sw-user-base-form.html.twig';
import errorConfig from '../../error-config.json';

/**
 * @sw-package checkout
 */

const { Defaults } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();
const { Criteria } = Contena.Data;
const { CUSTOMER } = Contena.Constants;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['feature'],

    emits: ['sales-channel-change'],

    props: {
        user: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('user', errorConfig['sw.user.detail.base'].user),

        salutationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.not('or', [Criteria.equals('id', Defaults.defaultSalutationId)]));

            return criteria;
        },

        accountTypeOptions() {
            return [
                {
                    value: CUSTOMER.ACCOUNT_TYPE_PRIVATE,
                    label: this.$t('sw-user.userType.labelPrivate'),
                },
                {
                    value: CUSTOMER.ACCOUNT_TYPE_BUSINESS,
                    label: this.$t('sw-user.userType.labelBusiness'),
                },
            ];
        },

        isBusinessAccountType() {
            return this.user?.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS;
        },

        languageCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.user?.channelId) {
                criteria.addFilter(Criteria.equals('channels.id', this.user.channelId));
            }

            return criteria;
        },
    },

    watch: {
        'user.guest'(newVal) {
            if (newVal) {
                this.user.password = null;
            }
        },
    },

    methods: {
        onSalesChannelChange(channelId) {
            this.$emit('sales-channel-change', channelId);
        },
    },
};

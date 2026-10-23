import template from './sw-user-base-info.html.twig';
import './sw-user-base-info.scss';
import errorConfig from '../../error-config.json';

/**
 * @sw-package checkout
 */

const { Criteria } = Contena.Data;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

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
            orderCount: 0,
            userLanguage: null,
            currencyCode: Contena.Context.app.systemCurrencyISOCode,
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageId() {
            return this.user.languageId;
        },

        userLanguageName() {
            if (this.userLanguage) {
                return this.userLanguage.name;
            }
            return '…';
        },

        languageCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('channels.id', this.user.channelId));

            return criteria;
        },

        orderCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('order.orderCustomer.userId', this.$route.params.id));

            return criteria;
        },

        ...mapPropertyErrors('user', [...errorConfig['sw.user.detail.base'].user]),

        isBusinessAccountType() {
            return this.user?.accountType === Contena.Constants.CUSTOMER.ACCOUNT_TYPE_BUSINESS;
        },

        dateFilter() {
            return Contena.Filter.getByName('date');
        },

        currencyFilter() {
            return Contena.Filter.getByName('currency');
        },
    },

    watch: {
        languageId: {
            immediate: true,
            handler() {
                this.languageRepository.get(this.languageId).then((language) => {
                    this.userLanguage = language;
                });
            },
        },
        user() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.orderRepository.search(this.orderCriteria).then((response) => {
                this.orderCount = response.total;
            });
        },
    },
};

import template from './sw-user-create.html.twig';

/**
 * @sw-package checkout
 */

const { mapPropertyErrors } = Contena.Component.getComponentHelper();
const { ContenaError } = Contena.Classes;
const { Mixin } = Contena;
const { Criteria } = Contena.Data;
const { CUSTOMER } = Contena.Constants;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'numberRangeService',
        'systemConfigApiService',
        'userValidationService',
    ],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            user: null,
            address: null,
            userNumberPreview: '',
            isSaveSuccessful: false,
            isLoading: false,
        };
    },

    computed: {
        ...mapPropertyErrors('address', ['company']),

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        validCompanyField() {
            return this.user.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS ? this.address.company?.trim().length : true;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageCriteria() {
            const criteria = new Criteria();
            criteria.setLimit(1);

            if (this.user?.channelId) {
                criteria.addFilter(Criteria.equals('channelDefaultAssignments.id', this.user.channelId));
            }

            return criteria;
        },

        languageId() {
            return this.loadLanguage(this.user?.channelId);
        },

        salutationRepository() {
            return this.repositoryFactory.create('salutation');
        },

        salutationCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addFilter(Criteria.equals('salutationKey', 'not_specified'));

            return criteria;
        },

        salutationFilter() {
            return Contena.Filter.getByName('salutation');
        },
    },

    watch: {
        'user.channelId'(channelId) {
            this.systemConfigApiService.getValues('core.systemWideLoginRegistration').then((response) => {
                if (response['core.systemWideLoginRegistration.isCustomerBoundToSalesChannel']) {
                    this.user.boundSalesChannelId = channelId;
                }
            });

            const currentSalesChannelId = channelId;

            this.loadLanguage(currentSalesChannelId)
                .then((languageId) => {
                    if (!this.user || this.user.channelId !== currentSalesChannelId) {
                        return;
                    }

                    this.user.languageId = languageId || Contena.Context.api.languageId;
                })
                .catch(() => {
                    if (this.user && this.user.channelId === currentSalesChannelId) {
                        this.user.languageId = Contena.Context.api.languageId;
                    }
                });
        },

        'user.accountType'(value) {
            if (value === CUSTOMER.ACCOUNT_TYPE_BUSINESS || !this.addressCompanyError) {
                return;
            }

            Contena.Store.get('error').removeApiError(`user_address.${this.address.id}.company`);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            const defaultSalutationId = await this.getDefaultSalutation();

            Contena.Store.get('context').resetLanguageToDefault();
            this.user = this.userRepository.create();

            const addressRepository = this.repositoryFactory.create(
                this.user.addresses.entity,
                this.user.addresses.source,
            );

            this.user.accountType = CUSTOMER.ACCOUNT_TYPE_PRIVATE;
            this.address = addressRepository.create();

            this.user.addresses.add(this.address);
            this.user.defaultBillingAddressId = this.address.id;
            this.user.defaultShippingAddressId = this.address.id;
            this.user.password = '';
            this.user.vatIds = [];
            this.user.salutationId = defaultSalutationId;
            this.user.languageId = Contena.Context.api.languageId;
            this.address.salutationId = defaultSalutationId;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.user.detail',
                params: { id: this.user.id },
            });
        },

        validateEmail() {
            const { id, email, boundSalesChannelId } = this.user;

            if (!email) {
                return Promise.resolve({ isValid: true });
            }

            return this.userValidationService
                .checkCustomerEmail({
                    id,
                    email,
                    boundSalesChannelId,
                })
                .then((emailIsValid) => {
                    return emailIsValid;
                })
                .catch((exception) => {
                    Contena.Store.get('error').addApiError({
                        expression: `user.${this.user.id}.email`,
                        error: new ContenaError(exception.response.data.errors[0]),
                    });
                });
        },

        async onSave() {
            this.isLoading = true;

            let hasError = false;
            const res = await this.validateEmail();
            if (!res || !res.isValid) {
                hasError = true;
            }

            this.isSaveSuccessful = false;
            let numberRangePromise = Promise.resolve();
            if (this.userNumberPreview === this.user.userNumber) {
                numberRangePromise = this.numberRangeService
                    .reserve('user', this.user.channelId)
                    .then((response) => {
                        this.userNumberPreview = 'reserved';
                        this.user.userNumber = response.number;
                    });
            }

            if (!this.validCompanyField) {
                this.createErrorMessageForCompanyField();
                hasError = true;
            }

            if (hasError) {
                this.createNotificationError({
                    message: this.$t('sw-user.detail.messageSaveError'),
                });
                this.isLoading = false;
                return false;
            }

            const languageId = await this.languageId;
            const context = { ...Contena.Context.api, ...{ languageId } };

            return numberRangePromise.then(() => {
                return this.userRepository
                    .save(this.user, context)
                    .then((response) => {
                        this.isLoading = false;
                        this.isSaveSuccessful = true;

                        return response;
                    })
                    .catch((exception) => {
                        this.createNotificationError({
                            message: this.$t('sw-user.detail.messageSaveError'),
                        });
                        this.isLoading = false;
                        throw exception;
                    });
            });
        },

        onChangeSalesChannel(channelId) {
            this.user.channelId = channelId;
            this.numberRangeService.reserve('user', channelId, true).then((response) => {
                this.userNumberPreview = response.number;
                this.user.userNumber = response.number;
            });
        },

        createErrorMessageForCompanyField() {
            this.isLoading = false;
            Contena.Store.get('error').addApiError({
                expression: `user_address.${this.address.id}.company`,
                error: new Contena.Classes.ContenaError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                }),
            });

            this.createNotificationError({
                message: this.$t('sw-user.error.COMPANY_IS_REQUIRED'),
            });
        },

        async loadLanguage(channelId) {
            const languageId = Contena.Context.api.languageId;

            if (!channelId) {
                return languageId;
            }

            const res = await this.languageRepository.searchIds(this.languageCriteria);

            if (!res?.data) {
                return languageId;
            }

            return res.data[0];
        },

        async getDefaultSalutation() {
            const res = await this.salutationRepository.searchIds(this.salutationCriteria);

            return res.data?.[0];
        },
    },
};

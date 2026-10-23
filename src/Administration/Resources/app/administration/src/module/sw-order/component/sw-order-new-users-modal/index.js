import EntityValidationService from 'src/app/service/entity-validation.service';
import template from './sw-order-new-user-modal.html.twig';
import './sw-order-new-user-modal.scss';

/**
 * @sw-package checkout
 */

const { Mixin } = Contena;
const { Criteria } = Contena.Data;
const { mapPageErrors } = Contena.Component.getComponentHelper();
const { CUSTOMER } = Contena.Constants;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'numberRangeService',
        'systemConfigApiService',
        'userValidationService',
        'feature',
    ],

    emits: ['on-select-existing-user', 'close'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            user: null,
            isLoading: false,
            userNumberPreview: '',
            defaultSalutationId: null,
            activeTab: 'details',
        };
    },

    computed: {
        ...mapPageErrors({
            'sw.order.new.user.detail': {
                user: [
                    'firstName',
                    'lastName',
                    'email',
                    'channelId',
                    'userNumber',
                    'groupId',
                ],
            },

            'sw.order.new.user.address': {
                user_address: [
                    'firstName',
                    'lastName',
                    'street',
                    'city',
                    'countryId',
                ],
            },
        }),

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        addressRepository() {
            return this.repositoryFactory.create('user_address');
        },

        newCustomerModalTabs() {
            return [
                {
                    label: this.$t('sw-order.newCustomerModal.labelDetails'),
                    name: 'details',
                    hasError: this.swOrderNewCustomerDetailError,
                },
                {
                    label: this.$t('sw-order.createBase.detailsBody.labelBillingAddress'),
                    name: 'billingAddress',
                    hasError: this.swOrderNewCustomerAddressError,
                },
                {
                    label: this.$t('sw-order.createBase.detailsBody.labelShippingAddress'),
                    name: 'shippingAddress',
                    hasError: !this.isSameBilling && this.swOrderNewCustomerAddressError,
                },
            ];
        },

        shippingAddress() {
            if (this.isSameBilling) {
                return this.billingAddress;
            }

            return this.user !== null ? this.user.addresses.get(this.user.defaultShippingAddressId) : null;
        },

        billingAddress() {
            return this.user !== null ? this.user.addresses.get(this.user.defaultBillingAddressId) : null;
        },

        isSameBilling: {
            get() {
                if (this.user === null) {
                    return true;
                }

                return this.user.defaultBillingAddressId === this.user.defaultShippingAddressId;
            },

            set(newValue) {
                if (newValue === this.isSameBilling) {
                    return;
                }

                if (newValue === true) {
                    this.user.defaultShippingAddressId = this.user.defaultBillingAddressId;

                    // remove all addresses but default billing...
                    if (this.user.isNew()) {
                        this.user.addresses = this.user.addresses.filter((address) => {
                            return address.id === this.user.defaultBillingAddressId;
                        });
                    }

                    return;
                }

                const shippingAddress = this.addressRepository.create();
                shippingAddress.salutationId = this.defaultSalutationId;

                this.user.addresses.add(shippingAddress);
                this.user.defaultShippingAddressId = shippingAddress.id;
            },
        },

        validCompanyField() {
            return this.user?.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS
                ? this.user?.company?.trim().length
                : true;
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
            return this.loadLanguage(this.user.channelId);
        },

        salutationRepository() {
            return this.repositoryFactory.create('salutation');
        },

        salutationCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addFilter(Criteria.equals('salutationKey', 'not_specified'));

            return criteria;
        },
    },

    watch: {
        'user.channelId'(channelId) {
            this.systemConfigApiService.getValues('core.systemWideLoginRegistration').then((response) => {
                if (response['core.systemWideLoginRegistration.isCustomerBoundToSalesChannel']) {
                    this.user.boundSalesChannelId = channelId;
                }
            });
        },

        'user.accountType'(value) {
            if (value === CUSTOMER.ACCOUNT_TYPE_BUSINESS) {
                return;
            }

            Contena.Store.get('error').removeApiError(`user_address.${this.billingAddress?.id}.company`);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.user = this.userRepository.create();

            this.defaultSalutationId = await this.getDefaultSalutationId();

            const billingAddress = this.addressRepository.create();
            billingAddress.salutationId = this.defaultSalutationId;

            this.user.addresses.add(billingAddress);

            this.user.defaultShippingAddressId = billingAddress.id;
            this.user.defaultBillingAddressId = billingAddress.id;
            this.user.accountType = CUSTOMER.ACCOUNT_TYPE_PRIVATE;
            this.user.vatIds = [];
            this.user.salutationId = this.defaultSalutationId;
        },

        async onSave() {
            let hasError = false;

            const res = await this.validateEmail();

            if (!res || !res.isValid) {
                hasError = true;
            }

            if (!this.validCompanyField) {
                this.createErrorMessageForCompanyField();
                hasError = true;
            }

            if (this.user.accountType === CUSTOMER.ACCOUNT_TYPE_PRIVATE) {
                this.user.vatIds = [];
            }

            if (hasError) {
                this.createNotificationError({
                    message: this.$t('sw-user.detail.messageSaveError'),
                });

                this.isLoading = false;
                return false;
            }

            let numberRangePromise = Promise.resolve();
            if (this.userNumberPreview === this.user.userNumber) {
                numberRangePromise = this.numberRangeService
                    .reserve('user', this.user.channelId)
                    .then((response) => {
                        this.userNumberPreview = response.number;
                        this.user.userNumber = response.number;
                    });
            }

            return numberRangePromise.then(() => {
                return this.saveCustomer();
            });
        },

        async saveCustomer() {
            const languageId = await this.languageId;

            const context = { ...Contena.Context.api, ...{ languageId } };

            return this.userRepository
                .save(this.user, context)
                .then((response) => {
                    this.$emit('on-select-existing-user', this.user.id);
                    this.isLoading = false;

                    this.onClose();

                    return response;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-user.detail.messageSaveError'),
                    });
                    this.isLoading = false;
                });
        },

        onChangeSalesChannel(channelId) {
            this.user.channelId = channelId;
            this.numberRangeService.reserve('user', channelId, true).then((response) => {
                this.userNumberPreview = response.number;
                this.user.userNumber = response.number;
            });
        },

        onClose() {
            this.clearOwnApiErrors();

            this.$emit('close');
        },

        clearOwnApiErrors() {
            const errorStore = Contena.Store.get('error');

            [this.billingAddress?.id, this.shippingAddress?.id].forEach((addressId) => {
                if (!addressId) {
                    return;
                }

                errorStore.removeApiError(`user_address.${addressId}.company`);
            });

            if (this.user?.id) {
                errorStore.removeApiError(`user.${this.user.id}.email`);
            }
        },

        createErrorMessageForCompanyField() {
            Contena.Store.get('error').addApiError({
                expression: `user_address.${this.billingAddress.id}.company`,
                error: new Contena.Classes.ContenaError({
                    code: EntityValidationService.ERROR_CODE_REQUIRED,
                }),
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
                    if (!exception) {
                        return;
                    }

                    Contena.Store.get('error').addApiError({
                        expression: `user.${this.user.id}.email`,
                        error: exception?.response?.data?.errors[0],
                    });
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

        async getDefaultSalutationId() {
            const res = await this.salutationRepository.searchIds(this.salutationCriteria);

            return res.data?.[0];
        },
    },
};

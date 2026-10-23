import './sw-user-detail.scss';
import template from './sw-user-detail.html.twig';
import errorConfig from '../../error-config.json';

/**
 * @sw-package checkout
 */

const { Mixin } = Contena;
const { Criteria } = Contena.Data;
const { ContenaError } = Contena.Classes;
const { mapPageErrors } = Contena.Component.getComponentHelper();
const { CUSTOMER } = Contena.Constants;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'userGroupRegistrationService',
        'acl',
        'userValidationService',
        'feature',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('discard-detail-page-changes')('user'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onAbortButtonClick',
    },

    props: {
        userId: {
            type: String,
            required: true,
        },
    },

    provide() {
        return {
            loadCustomer: this.loadCustomer.bind(this),
        };
    },

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            user: null,
            userAddressCustomFieldSets: [],
            userCustomFieldSets: [],
            errorEmailCustomer: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.user !== null ? this.salutation(this.user) : '';
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        editMode: {
            get() {
                if (typeof this.$route.query.edit === 'boolean') {
                    return this.$route.query.edit;
                }

                return this.$route.query.edit === 'true';
            },
            set(editMode) {
                this.$router.push({
                    name: this.$route.name,
                    query: { edit: editMode },
                });
            },
        },

        defaultCriteria() {
            const criteria = new Criteria(1, 25);
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('channel.domains')
                .addAssociation('boundSalesChannel.domains')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags')
                .addAssociation('requestedGroup')
                .addAssociation('boundSalesChannel');

            criteria
                .getAssociation('addresses')
                .addSorting(Criteria.sort('firstName'), 'ASC', false)
                .setLimit(criteria.limit);

            return criteria;
        },

        generalRoute() {
            return {
                name: 'sw.user.detail.base',
                params: { id: this.userId },
                query: { edit: this.editMode },
            };
        },

        addressesRoute() {
            return {
                name: 'sw.user.detail.addresses',
                params: { id: this.userId },
                query: { edit: this.editMode },
            };
        },

        ordersRoute() {
            return {
                name: 'sw.user.detail.order',
                params: { id: this.userId },
                query: { edit: this.editMode },
            };
        },

        userDetailTabs() {
            return [
                {
                    label: this.$t('sw-user.detail.tabGeneral'),
                    name: this.generalRoute.name,
                    hasError: this.swCustomerDetailBaseError,
                    onClick: () => {
                        void this.$router.push(this.generalRoute);
                    },
                },
                {
                    label: this.$t('sw-user.detail.tabAddresses'),
                    name: this.addressesRoute.name,
                    onClick: () => {
                        void this.$router.push(this.addressesRoute);
                    },
                },
                {
                    label: this.$t('sw-user.detailBase.labelOrderCard'),
                    name: this.ordersRoute.name,
                    onClick: () => {
                        void this.$router.push(this.ordersRoute);
                    },
                },
            ];
        },

        emailHasChanged() {
            const origin = this.user.getOrigin();
            if (this.user.isNew() || !origin.email) {
                return true;
            }

            return origin.email !== this.user.email;
        },

        validCompanyField() {
            return this.user.accountType === CUSTOMER.ACCOUNT_TYPE_BUSINESS
                ? this.user.company?.trim().length
                : true;
        },

        salutationRepository() {
            return this.repositoryFactory.create('salutation');
        },

        salutationCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addFilter(Criteria.equals('salutationKey', 'not_specified'));

            return criteria;
        },

        ...mapPageErrors(errorConfig),
    },

    watch: {
        userId() {
            this.createdComponent();
        },
    },

    beforeRouteLeave() {
        Contena.Store.get('contenaApps').selectedIds = [];
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async loadCustomer() {
            Contena.ExtensionAPI.publishData({
                id: 'sw-user-detail__customer',
                path: 'user',
                scope: this,
            });
            this.isLoading = true;

            try {
                const user = await this.userRepository.get(
                    this.userId,
                    Contena.Context.api,
                    this.defaultCriteria,
                );
                this.user = user;

                if (!this.user) {
                    this.createNotificationError({
                        message: this.$t('sw-user.detail.messageCustomerNotFound'),
                    });
                    void this.$router.push({ name: 'sw.user.index' });

                    return;
                }

                const defaultSalutationId = await this.getDefaultSalutation();

                if (!this.user.salutationId) {
                    this.user.salutationId = defaultSalutationId;
                }

                this.user.addresses?.map((address) => {
                    if (!address.salutationId) {
                        address.salutationId = defaultSalutationId;
                    }

                    return address;
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationLoadingDataErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        async createdComponent() {
            Contena.Store.get('contenaApps').selectedIds = this.userId ? [this.userId] : [];

            await this.loadCustomer();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.editMode = false;
            this.createdComponent();
            this.isLoading = false;
        },

        validateEmail() {
            const { id, email, boundSalesChannelId } = this.user;

            return this.userValidationService
                .checkCustomerEmail({
                    id,
                    email,
                    boundSalesChannelId,
                })
                .then((emailIsValid) => {
                    if (this.errorEmailCustomer) {
                        Contena.Store.get('error').addApiError({
                            expression: `user.${this.user.id}.email`,
                            error: null,
                        });
                    }

                    return emailIsValid;
                })
                .catch((exception) => {
                    this.emailIsValid = false;
                    Contena.Store.get('error').addApiError({
                        expression: `user.${this.user.id}.email`,
                        error: new ContenaError(exception.response.data.errors[0]),
                    });
                });
        },

        async onSave() {
            this.isLoading = true;

            if (!this.editMode) {
                return false;
            }

            let hasError = false;
            if (this.user.email && this.emailHasChanged) {
                const response = await this.validateEmail();

                if (!response || !response.isValid) {
                    hasError = true;
                }
            }

            if (!this.validCompanyField) {
                this.createErrorMessageForCompanyField();
                hasError = true;
            }

            if (!(await this.validPassword(this.user))) {
                hasError = true;
            }

            if (hasError) {
                this.createNotificationError({
                    message: this.$t('sw-user.detail.messageSaveError'),
                });
                this.isLoading = false;
                return false;
            }

            this.isSaveSuccessful = false;

            if (!this.user.birthday) {
                this.user.birthday = null;
            }

            if (this.user.passwordNew) {
                this.user.password = this.user.passwordNew;
            }

            if (this.user.accountType === CUSTOMER.ACCOUNT_TYPE_PRIVATE) {
                this.user.vatIds = [];
            }

            return this.userRepository
                .save(this.user)
                .then(() => {
                    this.isSaveSuccessful = true;
                    this.createNotificationSuccess({
                        message: this.$t(
                            'sw-user.detail.messageSaveSuccess',
                            {
                                name: `${this.user.firstName} ${this.user.lastName}`,
                            },
                            0,
                        ),
                    });
                })
                .catch((exception) => {
                    this.createNotificationError({
                        message: this.$t('sw-user.detail.messageSaveError'),
                    });
                    this.isLoading = false;
                    throw exception;
                });
        },

        async onAbortButtonClick() {
            this.discardChanges();
            this.editMode = false;
            await this.loadCustomer();
        },

        onActivateCustomerEditMode() {
            this.editMode = true;
        },

        abortOnLanguageChange() {
            return this.userRepository.hasChanges(this.user);
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            Contena.Store.get('context').setApiLanguageId(languageId);
            this.createdComponent();
        },

        async validPassword(user) {
            const { passwordNew, passwordConfirm } = user;
            const passwordSet = passwordNew || passwordConfirm;
            const passwordNotEquals = passwordNew !== passwordConfirm;

            if (passwordSet && passwordNotEquals) {
                Contena.Store.get('error').addApiError({
                    expression: `user.${this.user.id}.passwordConfirm`,
                    error: new ContenaError({
                        detail: this.$t('sw-user.error.passwordDoNotMatch'),
                        code: 'password_not_match',
                    }),
                });

                return false;
            }

            return true;
        },

        acceptCustomerGroupRegistration() {
            this.userGroupRegistrationService
                .accept(this.user.id)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-user.userGroupRegistration.acceptMessage'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-user.userGroupRegistration.errorMessage'),
                    });
                })
                .finally(() => {
                    this.createdComponent();
                });
        },

        declineCustomerGroupRegistration() {
            this.userGroupRegistrationService
                .decline(this.user.id)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-user.userGroupRegistration.declineMessage'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-user.userGroupRegistration.errorMessage'),
                    });
                })
                .finally(() => {
                    this.createdComponent();
                });
        },

        createErrorMessageForCompanyField() {
            this.isLoading = false;
            Contena.Store.get('error').addApiError({
                expression: `user.${this.user.id}.company`,
                error: new ContenaError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                }),
            });
        },

        async getDefaultSalutation() {
            const res = await this.salutationRepository.searchIds(this.salutationCriteria);

            return res.data?.[0];
        },
    },
};

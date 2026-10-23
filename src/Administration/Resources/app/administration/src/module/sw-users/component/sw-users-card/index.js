import template from './sw-user-card.html.twig';
import './sw-user-card.scss';
import errorConfig from '../../error-config.json';
import ApiService from '../../../../core/service/api.service';

/**
 * @sw-package checkout
 */

const { Mixin, Defaults } = Contena;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();
const { Criteria } = Contena.Data;
const { CUSTOMER } = Contena.Constants;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'contextStoreService', 'repositoryFactory'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('salutation')],

    props: {
        user: {
            type: Object,
            required: true,
        },
        title: {
            type: String,
            required: true,
        },
        editMode: {
            type: Boolean,
            required: false,
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
            showImitateCustomerModal: false,
            showConvertCustomerModal: false,
        };
    },

    computed: {
        hasActionSlot() {
            return !!this.$slots.actions?.[0];
        },

        hasAdditionalDataSlot() {
            return !!this.$slots['data-additional']?.[0];
        },

        hasSummarySlot() {
            return !!this.$slots.summary?.[0];
        },

        moduleColor() {
            if (!this.$route.meta.$module) {
                return '';
            }
            return this.$route.meta.$module.color;
        },

        fullName() {
            const name = {
                name: this.salutation(this.user),
                company: this.user.company,
            };

            return Object.values(name)
                .filter((item) => item !== null)
                .join(' - ')
                .trim();
        },

        salutationCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.not('or', [Criteria.equals('id', Defaults.defaultSalutationId)]));

            return criteria;
        },

        ...mapPropertyErrors('user', [...errorConfig['sw.user.detail.base'].user]),

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

        canUseCustomerImitation() {
            if (this.user.guest) {
                return false;
            }

            if (!this.user.active) {
                return false;
            }

            if (this.user.boundSalesChannel) {
                if (!this.user.boundSalesChannel.active) {
                    return false;
                }

                if (this.user.boundSalesChannel.typeId !== Defaults.frontendSalesChannelTypeId) {
                    return false;
                }

                if (!this.user.boundSalesChannel.domains?.length) {
                    return false;
                }
            }

            return this.acl.can('api_proxy_imitate-user');
        },

        canUseConvertCustomer() {
            return this.user.guest && this.acl.can('user.editor');
        },

        userImitationWarning() {
            if (this.user.guest) {
                return this.$t('sw-user.card.tooltipImitateCustomerGuest');
            }

            if (!this.user.active) {
                return this.$t('sw-user.card.tooltipImitateCustomerInactive');
            }

            if (this.user.boundSalesChannel) {
                if (!this.user.boundSalesChannel.active) {
                    return this.$t('sw-user.card.tooltipImitateCustomerInactiveSalesChannel');
                }

                if (this.user.boundSalesChannel.typeId !== Defaults.frontendSalesChannelTypeId) {
                    return this.$t('sw-user.card.tooltipImitateCustomerNoFrontend');
                }

                if (!this.user.boundSalesChannel.domains?.length) {
                    return this.$t('sw-user.card.tooltipImitateCustomerNoDomain');
                }
            }

            return this.$t('sw-privileges.tooltip.warning');
        },

        hasSingleBoundSalesChannelUrl() {
            return this.user.boundSalesChannel?.domains?.length === 1;
        },

        currentUser() {
            return Contena.Store.get('session').currentUser;
        },

        emailIdnFilter() {
            return Contena.Filter.getByName('decode-idn-email');
        },
    },

    watch: {
        'user.accountType'(value) {
            if (value === CUSTOMER.ACCOUNT_TYPE_BUSINESS || !this.userCompanyError) {
                return;
            }

            Contena.Store.get('error').removeApiError(`user.${this.user.id}.company`);
        },
    },

    methods: {
        getMailTo(mail) {
            return `mailto:${mail}`;
        },

        async onImitateCustomer() {
            if (this.hasSingleBoundSalesChannelUrl) {
                this.contextStoreService
                    .generateImitateCustomerToken(this.user.id, this.user.boundSalesChannel.id)
                    .then((response) => {
                        const handledResponse = ApiService.handleResponse(response);

                        this.contextStoreService.redirectToSalesChannelUrl(
                            this.user.boundSalesChannel.domains.first().url,
                            handledResponse.token,
                            this.user.id,
                            this.currentUser?.id,
                        );
                    })
                    .catch(() => {
                        this.createNotificationError({
                            message: this.$t('sw-user.detail.notificationImitateCustomerErrorMessage'),
                        });
                    });
                return;
            }

            this.showImitateCustomerModal = true;
        },

        onCloseImitateCustomerModal() {
            this.showImitateCustomerModal = false;
        },

        onOpenConvertCustomerModal() {
            this.showConvertCustomerModal = true;
        },

        onCloseConvertCustomerModal() {
            this.showConvertCustomerModal = false;
        },
    },
};

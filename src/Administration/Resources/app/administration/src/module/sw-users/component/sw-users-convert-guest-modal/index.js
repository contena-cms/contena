import './sw-user-convert-guest-modal.scss';
import template from './sw-user-convert-guest-modal.html.twig';
import errorConfig from '../../error-config.json';

/**
 * @sw-package checkout
 */

const { Mixin } = Contena;
const { ContenaError } = Contena.Classes;
const { mapPropertyErrors } = Contena.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['modal-close'],

    inject: ['guestCustomerConvertService', 'loadCustomer'],

    mixins: [Mixin.getByName('notification')],

    props: {
        user: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            password: '',
        };
    },

    computed: {
        ...mapPropertyErrors('user', [...errorConfig['sw.user.detail.base'].user]),
    },

    methods: {
        async sendRecoveryEmail() {
            try {
                await this.guestCustomerConvertService.convert(this.user.id);
                await this.loadCustomer();

                this.createNotificationSuccess({
                    message: this.$t('sw-user.detail.messageSaveSuccess', {
                        name: `${this.user.firstName} ${this.user.lastName}`,
                    }),
                });
            } catch (error) {
                this.handleConvertErrors(error);
            } finally {
                this.onCancel();
            }
        },

        onCancel() {
            Contena.Store.get('error').removeApiError(`user.${this.user.id}.convert`);

            this.$emit('modal-close');
        },

        async convert() {
            try {
                await this.guestCustomerConvertService.convert(this.user.id, { password: this.password });
                await this.loadCustomer();

                this.onCancel();

                this.createNotificationSuccess({
                    message: this.$t('sw-user.detail.messageSaveSuccess', {
                        name: `${this.user.firstName} ${this.user.lastName}`,
                    }),
                });
            } catch (error) {
                this.handleConvertErrors(error);
            }
        },

        handleConvertErrors(error) {
            const errors = error?.response?.data?.errors ?? [];
            const errorStore = Contena.Store.get('error');
            const expression = `user.${this.user.id}.convert`;

            const errorMap = {
                'VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE': {
                    message: this.$t('sw-user.error.VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE'),
                    apiError: false,
                },

                'VIOLATION::TOO_LONG_ERROR': {
                    message: this.$t('sw-user.error.VIOLATION::PASSWORD_IS_TOO_LONG'),
                    apiError: true,
                },

                'VIOLATION::TOO_SHORT_ERROR': {
                    message: this.$t('sw-user.error.VIOLATION::PASSWORD_IS_TOO_SHORT'),
                    apiError: true,
                },
            };

            if (!errors.length) {
                this.createNotificationError({
                    message: this.$t('sw-user.detail.messageSaveError'),
                });

                return;
            }

            errors.forEach((e) => {
                const mappedError = errorMap[e?.code];

                const message = mappedError?.message || e?.detail || this.$t('sw-user.detail.messageSaveError');

                this.createNotificationError({
                    message,
                });

                if (mappedError?.apiError) {
                    errorStore.addApiError({
                        expression,
                        error: new ContenaError({
                            code: e?.code || 'user_convert',
                            detail: message,
                        }),
                    });
                }
            });
        },
    },
};

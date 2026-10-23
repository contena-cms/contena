import type { AxiosError } from 'axios';
import template from './sw-extension-my-extensions-account.html.twig';
import './sw-extension-my-extensions-account.scss';
import extensionErrorHandler from '../../service/extension-error-handler.service';
import type { MappedError } from '../../service/extension-error-handler.service';
import type { AdminUserInfo } from '../../../../core/service/api/store.api.service';

const { Store, Mixin, Filter } = Contena;

/**
 * @sw-package checkout
 * @private
 */
export default Contena.Component.wrapComponentConfig({
    template,

    inject: ['systemConfigApiService', 'contenaExtensionService', 'storeService'],

    mixins: [Mixin.getByName('notification')],

    data(): {
        isLoading: boolean;
        unsubscribeStore: (() => void) | null;
        form: {
            password: string;
            contenaId: string;
        };
    } {
        return {
            isLoading: true,
            unsubscribeStore: null,
            form: {
                password: '',
                contenaId: '',
            },
        };
    },

    computed: {
        admin_userInfo(): AdminUserInfo | null {
            return Store.get('contenaExtensions').admin_userInfo;
        },

        isLoggedIn(): boolean {
            return Store.get('contenaExtensions').admin_userInfo !== null;
        },

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    created() {
        this.createdComponent()
            .then(() => {
                this.unsubscribeStore = Store.get('contenaExtensions').$onAction(({ name, args }) =>
                    this.showErrorNotification({ type: name, payload: args as MappedError[][] }),
                );
            })
            .catch(() => {});
    },

    beforeUnmount() {
        if (this.unsubscribeStore !== null) {
            this.unsubscribeStore();
        }
    },

    methods: {
        async createdComponent() {
            try {
                this.isLoading = true;
                await this.contenaExtensionService.checkLogin();
            } finally {
                this.isLoading = false;
            }
        },

        async logout() {
            try {
                await this.storeService.logout();
                this.$emit('logout-success');
            } catch (errorResponse) {
                this.commitErrors(
                    errorResponse as AxiosError<{
                        errors: StoreApiException[];
                    }>,
                );
            } finally {
                await this.contenaExtensionService.checkLogin();
            }
        },

        async login() {
            this.isLoading = true;

            try {
                await this.storeService.login(this.form.contenaId, this.form.password);

                this.$emit('login-success');

                this.createNotificationSuccess({
                    message: this.$t('sw-extension.my-extensions.account.loginNotificationMessage'),
                });
            } catch (errorResponse) {
                this.commitErrors(
                    errorResponse as AxiosError<{
                        errors: StoreApiException[];
                    }>,
                );
            } finally {
                await this.contenaExtensionService.checkLogin();
                this.isLoading = false;
            }
        },

        showErrorNotification({ type, payload }: { type: string; payload: MappedError[][] }) {
            if (type !== 'pluginErrorsMapped') {
                return;
            }

            payload.forEach((errors) => {
                errors.forEach((error) => {
                    if (error.parameters) {
                        this.showApiNotification(error);
                        return;
                    }

                    // Methods from mixins are not recognized
                    this.createNotificationError({
                        message: this.$t(error.message),
                    });
                });
            });
        },

        showApiNotification(error: MappedError) {
            // @ts-expect-error
            const docLink = this.$t('sw-extension.errors.messageToTheContenaDocumentation', error.parameters, 0);

            // Methods from mixins are not recognized
            this.createNotificationError({
                title: error.title,
                message: `${error.message} ${docLink}`,
                autoClose: false,
            });
        },

        commitErrors(errorResponse: AxiosError<{ errors: StoreApiException[] }>): never {
            if (errorResponse.response) {
                const mappedErrors = extensionErrorHandler.mapErrors(errorResponse.response.data.errors);
                Contena.Store.get('contenaExtensions').pluginErrorsMapped(mappedErrors);
            }

            throw errorResponse;
        },
    },
});

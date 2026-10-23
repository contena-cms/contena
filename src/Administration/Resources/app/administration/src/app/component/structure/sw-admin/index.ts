import type { Toast } from '@contena-ag/meteor-component-library/dist/esm/MtToast';
import template from './sw-admin.html.twig';

const { Component } = Contena;

/**
 * @sw-package framework
 *
 * @private
 */
export default Contena.Component.wrapComponentConfig({
    template,

    inject: ['admin_userActivityService', 'loginService', 'feature'],

    metaInfo() {
        return {
            title: this.$t('global.sw-admin-menu.textContenaAdmin'),
        };
    },

    data(): {
        channel: BroadcastChannel | null;
        toasts: Toast[];
    } {
        return {
            channel: null,
            toasts: [],
        };
    },

    computed: {
        isLoggedIn() {
            return this.loginService.isLoggedIn();
        },

        /**
         * @private
         *
         * Generated override components, rendered once in a hidden container so their setup bodies run and
         * register their override callbacks. Internal to the composition extension system.
         */
        overrideComponents() {
            return Component.getOverrideComponents();
        },
    },

    created() {
        Contena.ExtensionAPI.handle('toastDispatch', (toast) => {
            this.toasts = [
                {
                    id: Contena.Utils.createId(),
                    ...toast,
                },
                ...this.toasts,
            ];
        });

        this.channel = new BroadcastChannel('session_channel');
        this.channel.onmessage = (event) => {
            const data = event.data as { inactive?: boolean };

            if (!data || !Contena.Utils.object.hasOwnProperty(data, 'inactive')) {
                return;
            }

            const currentRouteName = this.$router.currentRoute.value.name as string;
            const routeBlocklist = ['sw.inactivity.login.index', 'sw.login.index.login'];
            if (!data.inactive || routeBlocklist.includes(currentRouteName || '')) {
                return;
            }

            this.loginService.forwardLogout(true, true);
        };
    },

    beforeUnmount() {
        this.channel?.close();
    },

    methods: {
        onUserActivity() {
            this.admin_userActivityService.updateLastUserActivity();
        },

        onRemoveToast(id: number) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    },
});

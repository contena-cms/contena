import './acl';
import enGB from './snippet/en.json';
import zhCN from './snippet/zh.json';

/** @private */
Contena.Component.register('ct-payment-order-list', () => import('./page/ct-payment-order-list'));
/** @private */
Contena.Component.register('ct-payment-refund-list', () => import('./page/ct-payment-refund-list'));
/** @private */
Contena.Component.register('ct-payment-app-list', () => import('./page/ct-payment-app-list'));
/** @private */
Contena.Component.register('ct-payment-channel-list', () => import('./page/ct-payment-channel-list'));
/** @private */
Contena.Component.register('ct-payment-method-list', () => import('./page/ct-payment-method-list'));

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-payment', {
    type: 'core',
    name: 'payment',
    title: 'ct-payment.general.mainMenuItemGeneral',
    description: 'ct-payment.general.description',
    color: 'var(--color-module-blue-500)',
    icon: 'regular-credit-card',
    entity: 'payment_order',
    snippets: {
        'en-GB': enGB,
        'zh-CN': zhCN,
    },

    routes: {
        order: {
            component: 'ct-payment-order-list',
            path: 'order',
            meta: { privilege: 'payment.viewer' },
        },
        refund: {
            component: 'ct-payment-refund-list',
            path: 'refund',
            meta: {
                parentPath: 'ct.payment.order',
                privilege: 'payment.viewer',
            },
        },
        apps: {
            component: 'ct-payment-app-list',
            path: 'settings/apps',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'payment.settings',
            },
        },
        channels: {
            component: 'ct-payment-channel-list',
            path: 'settings/channels',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'payment.settings',
            },
        },
        methods: {
            component: 'ct-payment-method-list',
            path: 'settings/methods',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'payment.settings',
            },
        },
    },

    navigation: [
        {
            id: 'ct-payment',
            label: 'ct-payment.general.mainMenuItemGeneral',
            color: 'var(--color-module-blue-500)',
            icon: 'regular-credit-card',
            position: 50,
            privilege: 'payment.viewer',
        },
        {
            id: 'ct-payment-order',
            label: 'ct-payment.navigation.orders',
            path: 'ct.payment.order',
            parent: 'ct-payment',
            position: 10,
            privilege: 'payment.viewer',
        },
        {
            id: 'ct-payment-refund',
            label: 'ct-payment.navigation.refunds',
            path: 'ct.payment.refund',
            parent: 'ct-payment',
            position: 20,
            privilege: 'payment.viewer',
        },
    ],

    settingsItem: [
        {
            id: 'ct-payment-app-settings',
            name: 'payment-app-settings',
            label: 'ct-payment.settings.appsTitle',
            group: 'payment',
            to: 'ct.payment.apps',
            icon: 'regular-storefront',
            privilege: 'payment.settings',
        },
        {
            id: 'ct-payment-channel-settings',
            name: 'payment-channel-settings',
            label: 'ct-payment.settings.channelsTitle',
            group: 'payment',
            to: 'ct.payment.channels',
            icon: 'regular-plug',
            privilege: 'payment.settings',
        },
        {
            id: 'ct-payment-method-settings',
            name: 'payment-method-settings',
            label: 'ct-payment.settings.methodsTitle',
            group: 'payment',
            to: 'ct.payment.methods',
            icon: 'regular-credit-card',
            privilege: 'payment.settings',
        },
    ],
});

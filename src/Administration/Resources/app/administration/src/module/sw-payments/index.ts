import './acl';

/**
 * @sw-package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Contena.Module.register('sw-payments', {
    type: 'core',
    name: 'contena-payments',
    title: 'sw-payments.general.mainMenuItemGeneral',
    description: 'sw-payments.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#FFBC51',
    icon: 'solid-contena-payments',

    // stub route, otherwise the module will not show
    routes: {
        index: {
            path: 'index',
        },
    },

    navigation: [
        {
            id: 'sw-payments',
            label: 'global.sw-admin-menu.navigation.mainMenuItemContenaPayments',
            color: '#FFBC51',
            icon: 'regular-contena-payments',
            position: 35,
            privilege: 'sw-payments.viewer',
        },
    ],
});

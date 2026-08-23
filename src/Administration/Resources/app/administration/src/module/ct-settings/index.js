import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-item', () => import('./component/ct-settings-item'));
Contena.Component.register('ct-system-config', () => import('./component/ct-system-config'));
Contena.Component.register('ct-system-config-media-upload', () => import('./component/ct-system-config-media-upload'));
Contena.Component.register('ct-settings-index', () => import('./page/ct-settings-index'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings', {
    type: 'core',
    name: 'settings',
    title: 'ct-settings.general.mainMenuItemGeneral',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'ct-settings-index',
            path: 'index',
            icon: 'regular-cog',
            redirect: {
                name: 'ct.settings.index.general',
            },
            children: {
                general: {
                    path: 'general',
                    meta: {
                        component: 'ct-settings-index',
                        parentPath: 'ct.settings.index',
                    },
                },
                system: {
                    path: 'system',
                    meta: {
                        component: 'ct-settings-index',
                        parentPath: 'ct.settings.index',
                    },
                },
                plugins: {
                    path: 'plugins',
                    meta: {
                        component: 'ct-settings-index',
                        parentPath: 'ct.settings.index',
                    },
                },
            },
        },
    },

    navigation: [
        {
            id: 'ct-settings',
            label: 'global.ct-admin-menu.navigation.mainMenuItemSettings',
            color: '#9AA8B5',
            icon: 'regular-cog',
            path: 'ct.settings.index',
            position: 90,
        },
    ],
});

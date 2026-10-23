/**
 * @sw-package framework
 */

/**
 * @deprecated tag:v6.8.0 - Will be @private
 */
Contena.Component.register('sw-settings-frontend-index', () => import('./page/sw-settings-frontend-index'));

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement.
 */
Contena.Component.register(
    'sw-settings-frontend-configuration',
    () => import('./component/sw-settings-frontend-configuration'),
);

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Contena.Module.register('sw-settings-frontend', {
    type: 'core',
    name: 'sw-settings-frontend',
    title: 'sw-settings-frontend.general.mainMenuItemGeneral',
    description: 'sw-settings-frontend.general.description',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.svg',

    routes: {
        index: {
            component: 'sw-settings-frontend-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.frontend.index',
        icon: 'regular-frontend',
        privilege: 'system.system_config',
    },
});

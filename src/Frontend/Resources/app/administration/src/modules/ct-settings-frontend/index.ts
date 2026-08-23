/** @private */
Contena.Component.register(
    'ct-settings-frontend-index',
    () => import('./page/ct-settings-frontend-index/ct-settings-frontend-index.vue'),
);

/** @private */
Contena.Module.register('ct-settings-frontend', {
    type: 'core',
    name: 'ct-settings-frontend',
    title: 'ct-settings-frontend.general.mainMenuItemGeneral',
    description: 'ct-settings-frontend.general.description',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.svg',

    routes: {
        index: {
            component: 'ct-settings-frontend-index',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index.system',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'ct.settings.frontend.index',
        icon: 'regular-storefront',
        privilege: 'system.system_config',
    },
});

/** @private */
export {};

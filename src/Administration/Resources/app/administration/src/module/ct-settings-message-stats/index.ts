/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-message-stats', () => import('./page/ct-settings-message-stats/index'));

Contena.Module.register('ct-settings-message-stats', {
    type: 'core',
    name: 'settings-message-stats',
    title: 'ct-settings-message-stats.general.mainMenuItemGeneral',
    description: 'ct-settings-message-stats.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'ct-settings-message-stats',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index.system',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'ct.settings.message.stats.index',
        icon: 'regular-bars-square',
        privilege: 'system.system_config',
    },
});

export {};

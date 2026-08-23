/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-sitemap', () => import('./page/ct-settings-sitemap'));

/** @private */
Contena.Module.register('ct-settings-sitemap', {
    type: 'core',
    name: 'settings-sitemap',
    title: 'ct-settings-sitemap.general.mainMenuItemGeneral',
    description: 'ct-settings-sitemap.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--color-icon-secondary-default)',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.svg',

    routes: {
        index: {
            component: 'ct-settings-sitemap',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'content',
        to: 'ct.settings.sitemap.index',
        icon: 'regular-map',
        privilege: 'system.system_config',
    },
});
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-region-list', () => import('./page/ct-settings-region-list'));
Contena.Component.register('ct-region-form', () => import('./component/ct-region-form'));
Contena.Component.register('ct-region-tree', () => import('./component/ct-region-tree'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-region', {
    type: 'core',
    name: 'settings-region',
    title: 'ct-settings-region.general.mainMenuItemGeneral',
    description: 'ct-settings-region.general.description',
    color: '#9AA8B5',
    icon: 'regular-sitemap',
    favicon: 'icon-module-settings.png',
    entity: 'region',

    routes: {
        index: {
            component: 'ct-settings-region-list',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'region.viewer',
            },
        },
    },

    settingsItem: {
        group: 'localization',
        to: 'ct.settings.region.index',
        icon: 'regular-sitemap',
        privilege: 'region.viewer',
    },
});

import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-cache-index', () => import('./page/ct-settings-cache-index'));
Contena.Component.register('ct-settings-cache-modal', () => import('./component/ct-settings-cache-modal'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-cache', {
    type: 'core',
    name: 'settings-cache',
    title: 'ct-settings-cache.general.mainMenuItemGeneral',
    description: 'ct-settings-cache.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'ct-settings-cache-index',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index.system',
                privilege: 'system.clear_cache',
            },
        },
    },

    settingsItem: {
        privilege: 'system.clear_cache',
        group: 'system',
        to: 'ct.settings.cache.index',
        icon: 'regular-files',
    },
});

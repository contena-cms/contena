import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-snippet-set-list', () => import('./page/ct-settings-snippet-set-list'));
Contena.Component.register('ct-settings-snippet-list', () => import('./page/ct-settings-snippet-list'));
Contena.Component.register('ct-settings-snippet-detail', () => import('./page/ct-settings-snippet-detail'));
Contena.Component.register('ct-settings-snippet-create', () => import('./page/ct-settings-snippet-create'));
Contena.Component.register('ct-settings-snippet-sidebar', () => import('./component/sidebar/ct-settings-snippet-sidebar'));
Contena.Component.register(
    'ct-settings-snippet-filter-switch',
    () => import('./component/sidebar/ct-settings-snippet-filter-switch'),
);
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

/** @private */
Module.register('ct-settings-snippet', {
    type: 'core',
    name: 'settings-snippet',
    title: 'ct-settings-snippet.general.mainMenuItemGeneral',
    description: 'ct-settings-snippet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--color-icon-secondary-default)',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.svg',
    entity: 'snippet',

    routes: {
        index: {
            component: 'ct-settings-snippet-set-list',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'snippet.viewer',
            },
        },
        list: {
            component: 'ct-settings-snippet-list',
            path: 'list',
            meta: {
                parentPath: 'ct.settings.snippet.index',
                privilege: 'snippet.viewer',
            },
        },
        detail: {
            component: 'ct-settings-snippet-detail',
            path: 'detail/:key',
            meta: {
                parentPath: 'ct.settings.snippet.list',
                privilege: 'snippet.viewer',
            },
        },
        create: {
            component: 'ct-settings-snippet-create',
            path: 'create',
            meta: {
                parentPath: 'ct.settings.snippet.list',
                privilege: 'snippet.creator',
            },
        },
    },

    settingsItem: {
        group: 'localization',
        to: 'ct.settings.snippet.index',
        icon: 'regular-globe-stand',
        privilege: 'snippet.viewer',
    },
});

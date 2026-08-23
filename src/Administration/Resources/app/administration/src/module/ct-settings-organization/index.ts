import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-organization-list', () => import('./page/ct-settings-organization-list'));
Contena.Component.register('mt-organization-form', () => import('./component/mt-organization-form'));
Contena.Component.register('mt-organization-tree', () => import('./component/mt-organization-tree'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-organization', {
    type: 'core',
    name: 'settings-organization',
    title: 'ct-settings-organization.general.mainMenuItemGeneral',
    description: 'ct-settings-organization.general.description',
    color: '#758CA3',
    icon: 'regular-user',
    favicon: 'icon-module-settings.png',
    entity: 'organization',

    routes: {
        index: {
            component: 'ct-settings-organization-list',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'organization.viewer',
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'ct.settings.organization.index',
        icon: 'regular-user',
        privilege: 'organization.viewer',
    },
});

import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-position-list', () => import('./page/ct-settings-position-list'));
Contena.Component.register('ct-settings-position-detail', () => import('./page/ct-settings-position-detail'));
Contena.Component.register('ct-settings-position-create', () => import('./page/ct-settings-position-create'));
Contena.Component.register('mt-position-form', () => import('./component/mt-position-form'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-position', {
    type: 'core',
    name: 'settings-position',
    title: 'ct-settings-position.general.mainMenuItemGeneral',
    description: 'ct-settings-position.general.description',
    color: '#758CA3',
    icon: 'regular-medal',
    favicon: 'icon-module-settings.png',
    entity: 'position',

    routes: {
        index: {
            component: 'ct-settings-position-list',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'position.viewer',
            },
        },
        detail: {
            component: 'ct-settings-position-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'ct.settings.position.index',
                privilege: 'position.viewer',
            },
        },
        create: {
            component: 'ct-settings-position-create',
            path: 'create',
            meta: {
                parentPath: 'ct.settings.position.index',
                privilege: 'position.creator',
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'ct.settings.position.index',
        icon: 'regular-medal',
        privilege: 'position.viewer',
    },
});

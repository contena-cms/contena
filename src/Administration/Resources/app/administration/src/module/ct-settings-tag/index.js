import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-tag-list', () => import('./page/ct-settings-tag-list'));
Contena.Component.register('ct-settings-tag-detail-modal', () => import('./component/ct-settings-tag-detail-modal'));
Contena.Component.register(
    'ct-settings-tag-detail-assignments',
    () => import('./component/ct-settings-tag-detail-assignments'),
);
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-tag', {
    type: 'core',
    name: 'settings-tag',
    title: 'ct-settings-tag.general.mainMenuItemGeneral',
    description: 'Tag section in the settings module',
    color: '#9AA8B5',
    icon: 'solid-cog',
    favicon: 'icon-module-settings.png',
    entity: 'tag',

    routes: {
        index: {
            component: 'ct-settings-tag-list',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'tag.viewer',
            },
        },
    },

    settingsItem: {
        group: 'content',
        to: 'ct.settings.tag.index',
        icon: 'regular-tag',
        privilege: 'tag.viewer',
    },
});

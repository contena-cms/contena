import './acl';
import defaultSearchConfiguration from './default-search-configuration';

/**
 * @sw-package discovery
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-settings-user-group-list', () => import('./page/sw-settings-user-group-list'));
Contena.Component.register('sw-settings-user-group-detail', () => import('./page/sw-settings-user-group-detail'));
Contena.Component.extend(
    'sw-settings-user-group-create',
    'sw-settings-user-group-detail',
    () => import('./page/sw-settings-user-group-create'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Contena;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-user-group', {
    type: 'core',
    name: 'settings-user-group',
    title: 'sw-settings-user-group.general.mainMenuItemGeneral',
    description: 'sw-settings-user-group.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-users',
    favicon: 'icon-module-settings.svg',
    entity: 'user_group',

    routes: {
        index: {
            component: 'sw-settings-user-group-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'user_groups.viewer',
            },
        },
        detail: {
            component: 'sw-settings-user-group-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.user.group.index',
                privilege: 'user_groups.viewer',
            },
            props: {
                default(route) {
                    return {
                        userGroupId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
        create: {
            component: 'sw-settings-user-group-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.user.group.index',
                privilege: 'user_groups.creator',
            },
        },
    },

    settingsItem: {
        group: 'user',
        to: 'sw.settings.user.group.index',
        icon: 'regular-users',
        privilege: 'user_groups.viewer',
    },

    defaultSearchConfiguration,
});

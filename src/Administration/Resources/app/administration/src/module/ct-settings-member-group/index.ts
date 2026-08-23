import './acl';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import defaultSearchConfiguration from './default-search-configuration';

/** @private */
Contena.Component.register('ct-settings-member-group-list', () => import('./page/ct-settings-member-group-list'));
/** @private */
Contena.Component.register('ct-settings-member-group-detail', () => import('./page/ct-settings-member-group-detail'));
/** @private */
Contena.Component.register('ct-settings-member-group-create', () => import('./page/ct-settings-member-group-create'));

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-settings-member-group', {
    type: 'core',
    name: 'settings-member-group',
    title: 'ct-settings-member-group.general.mainMenuItemGeneral',
    description: 'ct-settings-member-group.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--color-icon-secondary-default)',
    icon: 'regular-users',
    favicon: 'icon-module-settings.png',
    entity: 'member_group',
    routes: {
        index: {
            component: 'ct-settings-member-group-list',
            path: 'index',
            meta: { parentPath: 'ct.settings.index', privilege: 'member_groups.viewer' },
        },
        detail: {
            component: 'ct-settings-member-group-detail',
            path: 'detail/:id',
            meta: { parentPath: 'ct.settings.member.group.index', privilege: 'member_groups.viewer' },
            props: {
                default: (route: RouteLocationNormalizedLoaded) => ({
                    memberGroupId: String(route.params.id).toLowerCase(),
                }),
            },
        },
        create: {
            component: 'ct-settings-member-group-create',
            path: 'create',
            meta: { parentPath: 'ct.settings.member.group.index', privilege: 'member_groups.creator' },
        },
    },
    settingsItem: {
        group: 'member',
        to: 'ct.settings.member.group.index',
        icon: 'regular-users',
        privilege: 'member_groups.viewer',
    },
    defaultSearchConfiguration,
});

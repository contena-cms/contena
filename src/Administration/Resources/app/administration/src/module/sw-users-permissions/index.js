/**
 * @sw-package fundamentals@framework
 */
import './acl';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-users-permissions', () => import('./page/sw-users-permissions'));
Contena.Component.register(
    'sw-users-permissions-admin_user-listing',
    () => import('./components/sw-users-permissions-admin_user-listing'),
);
Contena.Component.register(
    'sw-users-permissions-role-listing',
    () => import('./components/sw-users-permissions-role-listing'),
);
Contena.Component.register(
    'sw-users-permissions-configuration',
    () => import('./components/sw-users-permissions-configuration'),
);
Contena.Component.register(
    'sw-users-permissions-additional-permissions',
    () => import('./components/sw-users-permissions-additional-permissions'),
);
Contena.Component.register(
    'sw-users-permissions-permissions-grid',
    () => import('./components/sw-users-permissions-permissions-grid'),
);
Contena.Component.register(
    'sw-users-permissions-detailed-permissions-grid',
    () => import('./components/sw-users-permissions-detailed-permissions-grid'),
);
Contena.Component.register(
    'sw-users-permissions-detailed-additional-permissions',
    () => import('./components/sw-users-permissions-detailed-additional-permissions'),
);
Contena.Component.register('sw-users-permissions-admin_user-detail', () => import('./page/sw-users-permissions-admin_user-detail'));
Contena.Component.extend(
    'sw-users-permissions-admin_user-create',
    'sw-users-permissions-admin_user-detail',
    () => import('./page/sw-users-permissions-admin_user-create'),
);
Contena.Component.register('sw-users-permissions-role-detail', () => import('./page/sw-users-permissions-role-detail'));
Contena.Component.register(
    'sw-users-permissions-role-view-general',
    () => import('./view/sw-users-permissions-role-view-general'),
);
Contena.Component.register(
    'sw-users-permissions-role-view-detailed',
    () => import('./view/sw-users-permissions-role-view-detailed'),
);
Contena.Component.register(
    'sw-sso-users-permission-admin_user-detail',
    () => import('./page/sw-sso-users-permission-admin_user-detail'),
);
Contena.Component.register('sw-admin_user-sso-invitation-modal', () => import('./components/sw-admin_user-sso-invitation-modal'));
Contena.Component.register('sw-admin_user-sso-status-label', () => import('./components/sw-admin_user-sso-status-label'));
Contena.Component.register(
    'sw-admin_user-sso-access-key-create-modal',
    () => import('./components/sw-admin_user-sso-access-key-create-modal'),
);
Contena.Component.register(
    'sw-users-permissions-role-mcp-reference-modal',
    () => import('./component/sw-users-permissions-role-mcp-reference-modal'),
);

/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Contena.Module.register('sw-users-permissions', {
    type: 'core',
    name: 'users-permissions',
    title: 'sw-users-permissions.general.label',
    description: 'sw-users-permissions.general.label',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-admin_user',
    favicon: 'icon-module-settings.svg',
    entity: 'admin_user',

    routes: {
        index: {
            component: 'sw-users-permissions',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'users_and_permissions.viewer',
            },
        },
        'admin_user.detail': {
            component: 'sw-users-permissions-admin_user-detail',
            path: 'admin_user.detail/:id?',
            meta: {
                parentPath: 'sw.users.permissions.index',
                privilege: 'users_and_permissions.viewer',
            },
        },
        'admin_user.sso.detail': {
            component: 'sw-sso-users-permission-admin_user-detail',
            path: 'admin_user.sso.detail/:id?',
            meta: {
                parentPath: 'sw.users.permissions.index',
                privilege: 'users_and_permissions.viewer',
            },
        },
        'admin_user.create': {
            component: 'sw-users-permissions-admin_user-create',
            path: 'admin_user.create',
            meta: {
                parentPath: 'sw.users.permissions.index',
                privilege: 'users_and_permissions.creator',
            },
        },
        'role.detail': {
            component: 'sw-users-permissions-role-detail',
            path: 'role.detail/:id?',
            meta: {
                parentPath: 'sw.users.permissions.index',
                privilege: 'users_and_permissions.viewer',
            },
            redirect: {
                name: 'sw.users.permissions.role.detail.general',
            },
            children: {
                general: {
                    component: 'sw-users-permissions-role-view-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.users.permissions.index',
                        privilege: 'users_and_permissions.viewer',
                    },
                },
                'detailed-privileges': {
                    component: 'sw-users-permissions-role-view-detailed',
                    path: 'detailed-privileges',
                    meta: {
                        parentPath: 'sw.users.permissions.index',
                        privilege: 'users_and_permissions.viewer',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.users.permissions.index',
        icon: 'regular-admin_user',
        privilege: 'users_and_permissions.viewer',
    },
});

import './acl';
import { ADMIN_MENU_ROOTS } from 'src/core/constant/admin-menu.constant';

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-permissions', () => import('./page/ct-permissions'));
Contena.Component.register('ct-permissions-role-listing', () => import('./component/ct-permissions-role-listing'));
Contena.Component.register('ct-permissions-role-form-modal', () => import('./component/ct-permissions-role-form-modal'));
Contena.Component.register(
    'ct-permissions-role-permissions-modal',
    () => import('./component/ct-permissions-role-permissions-modal'),
);
Contena.Component.register(
    'ct-permissions-additional-permissions',
    () => import('./component/ct-permissions-additional-permissions'),
);
Contena.Component.register('ct-permissions-permissions-grid', () => import('./component/ct-permissions-permissions-grid'));
Contena.Component.register('ct-permissions-role-access', () => import('./component/ct-permissions-role-access'));
Contena.Component.register(
    'ct-permissions-detailed-permissions-grid',
    () => import('./component/ct-permissions-detailed-permissions-grid'),
);
Contena.Component.register(
    'ct-permissions-detailed-additional-permissions',
    () => import('./component/ct-permissions-detailed-additional-permissions'),
);
Contena.Component.register('ct-permissions-role-detail', () => import('./page/ct-permissions-role-detail'));
Contena.Component.register('ct-permissions-role-view-general', () => import('./view/ct-permissions-role-view-general'));
Contena.Component.register(
    'ct-permissions-role-view-additional',
    () => import('./view/ct-permissions-role-view-additional'),
);
Contena.Component.register('ct-permissions-role-view-detailed', () => import('./view/ct-permissions-role-view-detailed'));
Contena.Component.register(
    'ct-permissions-role-mcp-reference-modal',
    () => import('./component/ct-permissions-role-mcp-reference-modal'),
);
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-permissions', {
    type: 'core',
    name: 'permissions',
    title: 'ct-permissions.roles.grid.title',
    description: 'ct-permissions.roles.grid.title',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-user',
    favicon: 'icon-module-settings.png',
    entity: 'acl_role',

    routes: {
        index: {
            component: 'ct-permissions',
            path: 'index',
            meta: {
                privilege: 'users_and_permissions.viewer',
            },
        },
        'role.detail': {
            component: 'ct-permissions-role-detail',
            path: 'role.detail/:id?',
            meta: {
                parentPath: 'ct.permissions.index',
                privilege: 'users_and_permissions.viewer',
            },
            redirect: {
                name: 'ct.permissions.role.detail.general',
            },
            children: {
                general: {
                    component: 'ct-permissions-role-view-general',
                    path: 'general',
                    meta: {
                        parentPath: 'ct.permissions.index',
                        privilege: 'users_and_permissions.viewer',
                    },
                },
                'additional-permissions': {
                    component: 'ct-permissions-role-view-additional',
                    path: 'additional-permissions',
                    meta: {
                        parentPath: 'ct.permissions.index',
                        privilege: 'users_and_permissions.viewer',
                    },
                },
                'detailed-privileges': {
                    component: 'ct-permissions-role-view-detailed',
                    path: 'detailed-privileges',
                    meta: {
                        parentPath: 'ct.permissions.index',
                        privilege: 'users_and_permissions.viewer',
                    },
                },
            },
        },
    },

    navigation: [
        {
            id: 'ct-permissions',
            label: 'ct-permissions.roles.grid.title',
            path: 'ct.permissions.index',
            parent: ADMIN_MENU_ROOTS.system,
            icon: 'regular-lock',
            position: 20,
            privilege: 'users_and_permissions.viewer',
        },
    ],
});

import { ADMIN_MENU_ROOTS } from 'src/core/constant/admin-menu.constant';

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-users', () => import('./page/ct-users'));
Contena.Component.register('ct-users-user-listing', () => import('./component/ct-users-user-listing'));
Contena.Component.register('ct-users-user-detail', () => import('./page/ct-users-user-detail'));
Contena.Component.extend('ct-users-user-create', 'ct-users-user-detail', () => import('./page/ct-users-user-create'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-users', {
    type: 'core',
    name: 'users',
    title: 'ct-users.general.cardLabel',
    description: 'ct-users.general.cardLabel',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-user',
    favicon: 'icon-module-settings.png',
    entity: 'user',
    routes: {
        index: {
            component: 'ct-users',
            path: 'index',
            meta: {
                privilege: 'users_and_permissions.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'ct-users',
            label: 'ct-users.general.cardLabel',
            path: 'ct.users.index',
            parent: ADMIN_MENU_ROOTS.system,
            icon: 'regular-user',
            position: 10,
            privilege: 'users_and_permissions.viewer',
        },
    ],
});

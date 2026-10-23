import './acl';
import defaultSearchConfiguration from './default-search-configuration';

/**
 * @sw-package checkout
 */

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-user-list', () => import('./page/sw-user-list'));
Contena.Component.register('sw-user-detail', () => import('./page/sw-user-detail'));
Contena.Component.register('sw-user-create', () => import('./page/sw-user-create'));
Contena.Component.register('sw-user-detail-base', () => import('./view/sw-user-detail-base'));
Contena.Component.register('sw-user-detail-addresses', () => import('./view/sw-user-detail-addresses'));
Contena.Component.register('sw-user-detail-order', () => import('./view/sw-user-detail-order'));
Contena.Component.register('sw-user-base-form', () => import('./component/sw-user-base-form'));
Contena.Component.register('sw-user-base-info', () => import('./component/sw-user-base-info'));
Contena.Component.register('sw-user-address-form', () => import('./component/sw-user-address-form'));
Contena.Component.register(
    'sw-user-address-form-options',
    () => import('./component/sw-user-address-form-options'),
);
Contena.Component.register('sw-user-default-addresses', () => import('./component/sw-user-default-addresses'));
Contena.Component.register(
    'sw-user-imitate-user-modal',
    () => import('./component/sw-user-imitate-user-modal'),
);
Contena.Component.register('sw-user-card', () => import('./component/sw-user-card'));
Contena.Component.register('sw-user-convert-guest-modal', () => import('./component/sw-user-convert-guest-modal'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-user', {
    type: 'core',
    name: 'users',
    title: 'sw-user.general.mainMenuItemGeneral',
    description: 'sw-user.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-orange-default)',
    icon: 'regular-users',
    favicon: 'icon-module-users.svg',
    entity: 'user',

    routes: {
        index: {
            components: {
                default: 'sw-user-list',
            },
            path: 'index',
            meta: {
                privilege: 'user.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'sw-user-create',
            path: 'create',
            meta: {
                parentPath: 'sw.user.index',
                privilege: 'user.creator',
            },
        },

        detail: {
            component: 'sw-user-detail',
            path: 'detail/:id',
            redirect: {
                name: 'sw.user.detail.base',
            },
            children: {
                base: {
                    component: 'sw-user-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.user.index',
                        privilege: 'user.viewer',
                    },
                },
                addresses: {
                    component: 'sw-user-detail-addresses',
                    path: 'addresses',
                    meta: {
                        parentPath: 'sw.user.index',
                        privilege: 'user.viewer',
                    },
                },
                order: {
                    component: 'sw-user-detail-order',
                    path: 'order',
                    meta: {
                        parentPath: 'sw.user.index',
                        privilege: 'user.viewer',
                    },
                },
            },
            meta: {
                privilege: 'user.viewer',
                appSystem: {
                    view: 'detail',
                },
            },

            props: {
                default(route) {
                    return {
                        userId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-user',
            label: 'sw-user.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-orange-default)',
            icon: 'regular-users',
            position: 40,
            privilege: 'user.viewer',
        },
        {
            path: 'sw.user.index',
            label: 'sw-user.general.mainMenuItemList',
            color: 'var(--sw-color-module-orange-default)',
            icon: 'regular-users',
            parent: 'sw-user',
            privilege: 'user.viewer',
        },
    ],

    defaultSearchConfiguration,
});

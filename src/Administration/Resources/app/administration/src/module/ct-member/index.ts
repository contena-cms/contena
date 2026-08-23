import './acl';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import defaultSearchConfiguration from './default-search-configuration';

/** @private */
Contena.Component.register('ct-member-list', () => import('./page/ct-member-list'));
/** @private */
Contena.Component.register('ct-member-detail', () => import('./page/ct-member-detail'));
/** @private */
Contena.Component.register('ct-member-create', () => import('./page/ct-member-create'));
/** @private */
Contena.Component.register('ct-member-detail-base', () => import('./view/ct-member-detail-base'));
/** @private */
Contena.Component.register('ct-member-detail-addresses', () => import('./view/ct-member-detail-addresses'));
/** @private */
Contena.Component.register('ct-member-base-form', () => import('./component/ct-member-base-form'));
/** @private */
Contena.Component.register('ct-member-base-info', () => import('./component/ct-member-base-info'));
/** @private */
Contena.Component.register('ct-member-address-form', () => import('./component/ct-member-address-form'));
/** @private */
Contena.Component.register('ct-member-card', () => import('./component/ct-member-card'));

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-member', {
    type: 'core',
    name: 'members',
    title: 'ct-member.general.mainMenuItemGeneral',
    description: 'ct-member.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--color-pumpkin-500)',
    icon: 'regular-users',
    favicon: 'icon-module-customers.png',
    entity: 'member',

    routes: {
        index: {
            component: 'ct-member-list',
            path: 'index',
            meta: { privilege: 'member.viewer' },
        },
        create: {
            component: 'ct-member-create',
            path: 'create',
            meta: {
                parentPath: 'ct.member.index',
                privilege: 'member.creator',
            },
        },
        detail: {
            component: 'ct-member-detail',
            path: 'detail/:id',
            redirect: { name: 'ct.member.detail.base' },
            meta: { privilege: 'member.viewer' },
            props: {
                default: (route: RouteLocationNormalizedLoaded) => ({
                    memberId: String(route.params.id).toLowerCase(),
                }),
            },
            children: {
                base: {
                    component: 'ct-member-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'ct.member.index',
                        privilege: 'member.viewer',
                    },
                },
                addresses: {
                    component: 'ct-member-detail-addresses',
                    path: 'addresses',
                    meta: {
                        parentPath: 'ct.member.index',
                        privilege: 'member.viewer',
                    },
                },
            },
        },
    },

    navigation: [
        {
            id: 'ct-member',
            label: 'ct-member.general.mainMenuItemGeneral',
            color: 'var(--color-pumpkin-500)',
            icon: 'regular-users',
            position: 40,
            privilege: 'member.viewer',
        },
        {
            id: 'ct-member-list',
            path: 'ct.member.index',
            label: 'ct-member.general.mainMenuItemList',
            parent: 'ct-member',
            position: 10,
            privilege: 'member.viewer',
        },
    ],

    defaultSearchConfiguration,
});

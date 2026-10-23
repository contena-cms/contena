/**
 * @sw-package fundamentals@framework
 */
import './extension/sw-admin-menu';
import './acl';

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-profile-index', () => import('./page/sw-profile-index'));
Contena.Component.register('sw-profile-index-general', () => import('./view/sw-profile-index-general'));
Contena.Component.register(
    'sw-profile-index-search-preferences',
    () => import('./view/sw-profile-index-search-preferences'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

/**
 * @private
 */
Contena.Component.register(
    'sw-profile-index-privacy-preferences',
    () => import('./view/sw-profile-index-privacy-preferences'),
);

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-profile', {
    type: 'core',
    name: 'profile',
    title: 'sw-profile.general.headlineProfile',
    description: 'sw-profile.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-admin_user',
    entity: 'admin_user',

    routes: {
        index: {
            component: 'sw-profile-index',
            path: 'index',
            redirect: {
                name: 'sw.profile.index.general',
            },
            meta: {
                privilege: 'admin_user.update_profile',
            },
            children: {
                general: {
                    component: 'sw-profile-index-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.profile.index',
                        privilege: 'admin_user.update_profile',
                    },
                },
                searchPreferences: {
                    component: 'sw-profile-index-search-preferences',
                    path: 'search-preferences',
                    meta: {
                        parentPath: 'sw.profile.index',
                        privilege: 'admin_user.update_profile',
                    },
                },
                privacyPreferences: {
                    component: 'sw-profile-index-privacy-preferences',
                    path: 'privacy-preferences',
                    meta: {
                        parentPath: 'sw.profile.index',
                        privilege: 'admin_user.update_profile',
                    },
                },
            },
        },
    },
});

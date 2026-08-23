import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-profile-index', () => import('./page/ct-profile-index'));
Contena.Component.register('ct-profile-index-general', () => import('./view/ct-profile-index-general'));
Contena.Component.register(
    'ct-profile-index-search-preferences',
    () => import('./view/ct-profile-index-search-preferences'),
);
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-profile', {
    type: 'core',
    name: 'profile',
    title: 'ct-profile.general.headlineProfile',
    description: 'ct-profile.general.description',
    color: '#9AA8B5',
    icon: 'regular-user',
    entity: 'user',

    routes: {
        index: {
            component: 'ct-profile-index',
            path: 'index',
            redirect: {
                name: 'ct.profile.index.general',
            },
            meta: {
                privilege: 'user.update_profile',
            },
            children: {
                general: {
                    component: 'ct-profile-index-general',
                    path: 'general',
                    meta: {
                        parentPath: 'ct.profile.index',
                        privilege: 'user.update_profile',
                    },
                },
                searchPreferences: {
                    component: 'ct-profile-index-search-preferences',
                    path: 'search-preferences',
                    meta: {
                        parentPath: 'ct.profile.index',
                        privilege: 'user.update_profile',
                    },
                },
            },
        },
    },
});

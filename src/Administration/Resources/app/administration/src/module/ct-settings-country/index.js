import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-country-list', () => import('./page/ct-settings-country-list'));
Contena.Component.register('ct-settings-country-detail', () => import('./page/ct-settings-country-detail'));
Contena.Component.extend(
    'ct-settings-country-create',
    'ct-settings-country-detail',
    () => import('./page/ct-settings-country-create'),
);
Contena.Component.register('ct-settings-country-general', () => import('./component/ct-settings-country-general'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-country', {
    type: 'core',
    name: 'settings-country',
    title: 'ct-settings-country.general.mainMenuItemGeneral',
    description: 'Country section in the settings module',
    color: '#9AA8B5',
    icon: 'solid-cog',
    favicon: 'icon-module-settings.png',
    entity: 'country',

    routes: {
        index: {
            component: 'ct-settings-country-list',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'country.viewer',
            },
        },
        detail: {
            component: 'ct-settings-country-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'ct.settings.country.index',
                privileges: [
                    'country.viewer',
                    'country.editor',
                ],
            },

            redirect: {
                name: 'ct.settings.country.detail.general',
            },

            children: {
                general: {
                    component: 'ct-settings-country-general',
                    path: 'general',
                    meta: {
                        parentPath: 'ct.settings.country.index',
                        privileges: [
                            'country.editor',
                            'country.creator',
                        ],
                    },
                },
            },
        },
        create: {
            component: 'ct-settings-country-create',
            path: 'create',
            meta: {
                parentPath: 'ct.settings.country.index',
                privilege: 'country.creator',
            },

            redirect: {
                name: 'ct.settings.country.create.general',
            },

            children: {
                general: {
                    component: 'ct-settings-country-general',
                    path: 'general',
                    meta: {
                        parentPath: 'ct.settings.country.index',
                        privilege: 'country.creator',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'localization',
        to: 'ct.settings.country.index',
        icon: 'regular-map',
        privilege: 'country.viewer',
    },
});

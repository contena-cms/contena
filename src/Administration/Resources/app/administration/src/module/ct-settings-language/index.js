import './acl';

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-language-list', () => import('./page/ct-settings-language-list'));
Contena.Component.register('ct-settings-language-detail', () => import('./page/ct-settings-language-detail'));
Contena.Component.register('ct-settings-language-add-modal', () => import('./component/ct-settings-language-add-modal'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

const { Module } = Contena;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-language', {
    type: 'core',
    name: 'settings-language',
    title: 'ct-settings-language.general.mainMenuItemGeneral',
    description: 'Language section in the settings module',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'language',

    routes: {
        index: {
            component: 'ct-settings-language-list',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'language.viewer',
            },
        },
        detail: {
            component: 'ct-settings-language-detail',
            path: 'detail/:id?',
            meta: {
                parentPath: 'ct.settings.language.index',
                privilege: 'language.viewer',
            },
            props: {
                default: (route) => ({ languageId: route.params.id?.toLowerCase() }),
            },
        },
        create: {
            component: 'ct-settings-language-detail',
            path: 'create',
            meta: {
                parentPath: 'ct.settings.language.index',
                privilege: 'language.creator',
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'ct.settings.language.index',
        icon: 'regular-flag',
        privilege: 'language.viewer',
    },
});

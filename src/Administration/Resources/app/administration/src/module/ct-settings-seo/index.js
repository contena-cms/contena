/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-seo-url-template-card', () => import('./component/ct-seo-url-template-card'));
Contena.Component.register('ct-seo-url', () => import('./component/ct-seo-url'));
Contena.Component.register('ct-seo-main-category', () => import('./component/ct-seo-main-category'));
Contena.Component.register('ct-settings-seo', () => import('./page/ct-settings-seo'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

const { Module } = Contena;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-seo', {
    type: 'core',
    name: 'settings-seo',
    title: 'ct-settings-seo.general.mainMenuItemGeneral',
    description: 'SEO section in the settings module',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'seo',

    routes: {
        index: {
            component: 'ct-settings-seo',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'content',
        to: 'ct.settings.seo.index',
        icon: 'regular-search',
        privilege: 'system.system_config',
    },
});

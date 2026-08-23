const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-listing', () => import('./page/ct-settings-listing'));
Contena.Component.register('ct-settings-listing-option-base', () => import('./page/ct-settings-listing-option-base'));
Contena.Component.register('ct-settings-listing-option-create', () => import('./page/ct-settings-listing-option-create'));
Contena.Component.register('ct-settings-listing-delete-modal', () => import('./component/ct-settings-listing-delete-modal'));
Contena.Component.register(
    'ct-settings-listing-option-general-info',
    () => import('./component/ct-settings-listing-option-general-info'),
);
Contena.Component.register(
    'ct-settings-listing-option-criteria-grid',
    () => import('./component/ct-settings-listing-option-criteria-grid'),
);
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-listing', {
    type: 'core',
    name: 'settings-listing',
    title: 'ct-settings-listing.general.mainMenuItemGeneral',
    description: 'ct-settings-listing.general.description',
    color: '#9AA8B5',
    icon: 'regular-sort',
    favicon: 'icon-module-settings.png',
    entity: 'blog_sorting',

    routes: {
        index: {
            component: 'ct-settings-listing',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'system.system_config',
            },
        },
        edit: {
            component: 'ct-settings-listing-option-base',
            path: 'edit/:id',
            meta: {
                parentPath: 'ct.settings.listing.index',
                privilege: 'system.system_config',
            },
        },
        create: {
            component: 'ct-settings-listing-option-create',
            path: 'create',
            meta: {
                parentPath: 'ct.settings.listing.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'content',
        to: 'ct.settings.listing.index',
        icon: 'regular-sort',
        privilege: 'system.system_config',
    },
});

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export {};

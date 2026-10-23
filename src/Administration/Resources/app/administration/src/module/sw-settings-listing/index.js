/**
 * @sw-package inventory
 */
const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-settings-listing', () => import('./page/sw-settings-listing'));
Contena.Component.register('sw-settings-listing-option-base', () => import('./page/sw-settings-listing-option-base'));
Contena.Component.extend(
    'sw-settings-listing-option-create',
    'sw-settings-listing-option-base',
    () => import('./page/sw-settings-listing-option-create'),
);
Contena.Component.register(
    'sw-settings-listing-default-sales-channel',
    () => import('./component/sw-settings-listing-default-sales-channel'),
);
Contena.Component.register(
    'sw-settings-listing-delete-modal',
    () => import('./component/sw-settings-listing-delete-modal'),
);
Contena.Component.register(
    'sw-settings-listing-option-general-info',
    () => import('./component/sw-settings-listing-option-general-info'),
);
Contena.Component.register(
    'sw-settings-listing-option-criteria-grid',
    () => import('./component/sw-settings-listing-option-criteria-grid'),
);
Contena.Component.register(
    'sw-settings-listing-visibility-detail',
    () => import('./component/sw-settings-listing-visibility-detail'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-listing', {
    type: 'core',
    name: 'settings-listing',
    title: 'sw-settings-listing.general.mainMenuItemGeneral',
    description: 'sw-settings-listing.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-blogs',
    favicon: 'icon-module-settings.svg',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-listing',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },

        edit: {
            component: 'sw-settings-listing-option-base',
            path: 'edit/:id',
            meta: {
                parentPath: 'sw.settings.listing.index',
                privilege: 'system.system_config',
            },
        },

        create: {
            component: 'sw-settings-listing-option-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.listing.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'commerce',
        to: 'sw.settings.listing.index',
        icon: 'regular-blogs',
        privilege: 'system.system_config',
    },
});

import './acl';

const { Module } = Contena;

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.extend(
    'ct-settings-custom-field-set-create',
    'ct-settings-custom-field-set-detail',
    () => import('./page/ct-settings-custom-field-set-create'),
);
Contena.Component.register('ct-settings-custom-field-set-list', () => import('./page/ct-settings-custom-field-set-list'));
Contena.Component.register(
    'ct-settings-custom-field-set-detail',
    () => import('./page/ct-settings-custom-field-set-detail'),
);
Contena.Component.register(
    'ct-custom-field-translated-labels',
    () => import('./component/ct-custom-field-translated-labels'),
);
Contena.Component.register('ct-custom-field-set-detail-base', () => import('./component/ct-custom-field-set-detail-base'));
Contena.Component.register('ct-custom-field-list', () => import('./component/ct-custom-field-list'));
Contena.Component.register('ct-custom-field-detail', () => import('./component/ct-custom-field-detail'));
Contena.Component.register('ct-custom-field-type-base', () => import('./component/ct-custom-field-type-base'));
Contena.Component.register('ct-custom-field-type-select', () => import('./component/ct-custom-field-type-select'));
Contena.Component.register('ct-custom-field-type-entity', () => import('./component/ct-custom-field-type-entity'));
Contena.Component.register('ct-custom-field-type-text', () => import('./component/ct-custom-field-type-text'));
Contena.Component.register('ct-custom-field-type-number', () => import('./component/ct-custom-field-type-number'));
Contena.Component.register('ct-custom-field-type-date', () => import('./component/ct-custom-field-type-date'));
Contena.Component.register('ct-custom-field-type-checkbox', () => import('./component/ct-custom-field-type-checkbox'));
Contena.Component.register('ct-custom-field-type-text-editor', () => import('./component/ct-custom-field-type-text-editor'));
Contena.Component.register('ct-custom-field-type-colorpicker', () => import('./component/ct-custom-field-type-colorpicker'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-custom-field', {
    type: 'core',
    name: 'settings-custom-field',
    title: 'ct-settings-custom-field.general.mainMenuItemGeneral',
    description: 'ct-settings-custom-field.general.description',
    color: '#9AA8B5',
    icon: 'solid-cog',
    favicon: 'icon-module-settings.png',
    entity: 'custom-field-set',

    routes: {
        index: {
            component: 'ct-settings-custom-field-set-list',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index.system',
                privilege: 'custom_field.viewer',
            },
        },
        detail: {
            component: 'ct-settings-custom-field-set-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'ct.settings.custom.field.index',
                privilege: 'custom_field.viewer',
            },
        },
        create: {
            component: 'ct-settings-custom-field-set-create',
            path: 'create',
            meta: {
                parentPath: 'ct.settings.custom.field.index',
                privilege: 'custom_field.creator',
            },
        },
    },

    settingsItem: {
        group: 'content',
        to: 'ct.settings.custom.field.index',
        icon: 'regular-bars-square',
        privilege: 'custom_field.viewer',
    },
});

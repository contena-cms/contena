// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Component.register('ct-settings-media', () => import('./page/ct-settings-media'));

const { Module } = Contena;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-media', {
    type: 'core',
    name: 'settings-media',
    title: 'ct-settings-media.general.title',
    description: 'ct-settings-media.general.description',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',

    routes: {
        index: {
            component: 'ct-settings-media',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'content',
        to: 'ct.settings.media.index',
        icon: 'regular-image',
        privilege: 'system.system_config',
    },
});

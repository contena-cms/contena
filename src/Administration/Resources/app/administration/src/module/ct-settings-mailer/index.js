/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-mailer', () => import('./page/ct-settings-mailer'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Module.register('ct-settings-mailer', {
    type: 'core',
    name: 'settings-mailer',
    title: 'ct-settings-mailer.general.title',
    description: 'ct-settings-mailer.general.description',
    color: '#9AA8B5',
    icon: 'regular-envelope',

    routes: {
        index: {
            component: 'ct-settings-mailer',
            path: 'index',
            meta: { parentPath: 'ct.settings.index.system', privilege: 'system.system_config' },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'ct.settings.mailer.index',
        icon: 'regular-envelope',
        privilege: 'system.system_config',
    },
});

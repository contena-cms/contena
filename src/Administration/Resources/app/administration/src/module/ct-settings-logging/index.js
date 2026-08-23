import './acl';

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-logging-list', () => import('./page/ct-settings-logging-list'));
Contena.Component.register('ct-settings-logging-entry-info', () => import('./component/ct-settings-logging-entry-info'));
Contena.Component.register(
    'ct-settings-logging-mail-sent-info',
    () => import('./component/ct-settings-logging-mail-sent-info'),
);
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

const { Module } = Contena;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Module.register('ct-settings-logging', {
    type: 'core',
    name: 'settings-logging',
    title: 'ct-settings-logging.general.mainMenuItemGeneral',
    description: 'Log viewer',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.png',
    entity: 'log_entry',

    routes: {
        index: {
            component: 'ct-settings-logging-list',
            path: 'list',
            meta: {
                parentPath: 'ct.settings.index.system',
                privilege: 'system.logging',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'ct.settings.logging.index',
        icon: 'regular-server',
        privilege: 'system.logging',
    },
});

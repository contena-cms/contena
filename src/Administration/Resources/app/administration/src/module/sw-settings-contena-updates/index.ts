/**
 * @sw-package framework
 */

import './acl';

const { Component, Module } = Contena;

/** @private */
Component.register(
    'sw-settings-contena-updates-extensions',
    () => import('./view/sw-settings-contena-updates-extensions'),
);
/** @private */
Component.register('sw-settings-contena-updates-wizard', () => import('./page/sw-settings-contena-updates-wizard'));

/**
 * @private
 */
Module.register('sw-settings-contena-updates', {
    type: 'core',
    name: 'settings-contena-updates',
    display: !Contena.Context.app.hideUpdateModule,
    title: 'sw-settings-contena-updates.general.menuTitle',
    description: 'sw-settings-contena-updates.general.menuTitle',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-sync',
    favicon: 'icon-module-settings.svg',

    routes: {
        wizard: {
            component: 'sw-settings-contena-updates-wizard',
            path: 'wizard',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.core_update',
            },
        },
    },

    settingsItem: {
        privilege: 'system.core_update',
        group: 'system',
        to: 'sw.settings.contena.updates.wizard',
        icon: 'regular-sync',
    },
});

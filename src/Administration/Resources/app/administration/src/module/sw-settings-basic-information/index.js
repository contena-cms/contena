/**
 * @sw-package fundamentals@framework
 */
import CaptchaService from './service/captcha.service';

const { Module } = Contena;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Contena.Component.register('sw-settings-basic-information', () => import('./page/sw-settings-basic-information'));
Contena.Component.register('sw-settings-captcha-select-v2', () => import('./component/sw-settings-captcha-select-v2'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Contena.Service().register('captchaService', () => {
    return new CaptchaService(Contena.Application.getContainer('init').httpClient, Contena.Service().get('loginService'));
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-basic-information', {
    type: 'core',
    name: 'settings-basic-information',
    title: 'sw-settings-basic-information.general.mainMenuItemGeneral',
    description: 'sw-settings-basic-information.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-bars',
    favicon: 'icon-module-settings.svg',

    routes: {
        index: {
            component: 'sw-settings-basic-information',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'sw.settings.basic.information.index',
        icon: 'regular-bars',
        privilege: 'system.system_config',
    },
});

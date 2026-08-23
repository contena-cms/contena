import CaptchaService from './service/captcha.service';

/* eslint-disable ct-deprecation-rules/private-feature-declarations */
Contena.Component.register('ct-settings-basic-information', () => import('./page/ct-settings-basic-information'));
Contena.Component.register('ct-settings-captcha-select-v2', () => import('./component/ct-settings-captcha-select-v2'));
/* eslint-enable ct-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
Contena.Service().register('captchaService', () => {
    return new CaptchaService(Contena.Application.getContainer('init').httpClient, Contena.Service('loginService'));
});

/** @private */
Contena.Module.register('ct-settings-basic-information', {
    type: 'core',
    name: 'settings-basic-information',
    title: 'ct-settings-basic-information.general.mainMenuItemGeneral',
    description: 'ct-settings-basic-information.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#9AA8B5',
    icon: 'regular-cog',
    favicon: 'icon-module-settings.svg',

    routes: {
        index: {
            component: 'ct-settings-basic-information',
            path: 'index',
            meta: {
                parentPath: 'ct.settings.index',
                privilege: 'system.system_config',
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'ct.settings.basic.information.index',
        icon: 'regular-bars',
        privilege: 'system.system_config',
    },
});

import type { SubContainer } from 'src/global.types';

import type { App } from 'vue';
import ExtensionStoreActionService from './extension-store-action.service';
import ContenaExtensionService from './contena-extension.service';
import ExtensionErrorService from './extension-error.service';

const { Application } = Contena;

/**
 * @sw-package checkout
 */
declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        extensionStoreActionService: ExtensionStoreActionService;
        contenaExtensionService: ContenaExtensionService;
        extensionErrorService: ExtensionErrorService;
    }
}

Application.addServiceProvider('extensionStoreActionService', () => {
    return new ExtensionStoreActionService(
        Contena.Application.getContainer('init').httpClient,
        Contena.Service('loginService'),
    );
});

Application.addServiceProvider('contenaExtensionService', () => {
    return new ContenaExtensionService(
        Contena.Service('appModulesService'),
        Contena.Service('extensionStoreActionService'),
        Contena.Service('contenaDiscountCampaignService'),
        Contena.Service('storeService'),
    );
});

Application.addServiceProvider('extensionErrorService', () => {
    const root = Contena.Application.getApplicationRoot() as App<Element>;

    return new ExtensionErrorService(
        {
            FRAMEWORK__APP_LICENSE_COULD_NOT_BE_VERIFIED: {
                title: 'sw-extension.errors.appLicenseCouldNotBeVerified.title',
                message: 'sw-extension.errors.appLicenseCouldNotBeVerified.message',
                autoClose: false,
                actions: [
                    {
                        label: root.$t('sw-extension.errors.appLicenseCouldNotBeVerified.actionSetLicenseDomain'),
                        method: () => {
                            void root.$router.push({
                                name: 'sw.settings.store.index',
                            });
                        },
                    },
                    {
                        label: root.$t('sw-extension.errors.appLicenseCouldNotBeVerified.actionLogin'),
                        method: () => {
                            void root.$router.push({
                                name: 'sw.extension.my-extensions.account',
                            });
                        },
                    },
                ],
            },
            FRAMEWORK__APP_NOT_COMPATIBLE: {
                title: 'global.default.error',
                message: 'sw-extension.errors.appIsNotCompatible',
            },
        },
        {
            title: 'global.default.error',
            message: 'global.notification.unspecifiedSaveErrorMessage',
        },
    );
});

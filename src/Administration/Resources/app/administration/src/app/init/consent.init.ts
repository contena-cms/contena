/**
 * @sw-package framework:fundamentals
 */
import useConsentStore from 'src/core/consent/consent.store';
import ConsentApiService from 'src/core/consent/consent.api.service';
import broadcastConsentChanges from 'src/core/consent/broadcast-changes';
import { handleConsentRequest, handleConsentStatus } from 'src/core/consent/sdk-handler';

/**
 * @private
 */
export default async function initConsent(): Promise<void> {
    /**
     * @private
     */
    Contena.Service().register('consentApiService', (serviceContainer) => {
        return new ConsentApiService(Contena.Application.getContainer('init').httpClient, serviceContainer.loginService);
    });

    const consentStore = useConsentStore();

    try {
        await consentStore.update();
    } catch {
        // keep empty store and wait for next update interval
    }

    setInterval(() => {
        if (!Contena.Service('loginService').isLoggedIn()) {
            return;
        }

        void consentStore.update();
    }, 300000); // every 5 minutes

    broadcastConsentChanges();

    Contena.ExtensionAPI.handle('consentStatus', handleConsentStatus);
    Contena.ExtensionAPI.handle('consentRequest', handleConsentRequest);
}

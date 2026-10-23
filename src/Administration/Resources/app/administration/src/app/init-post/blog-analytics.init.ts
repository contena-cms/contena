/**
 * @sw-package framework
 */
import { computed, watch, type WatchHandle } from 'vue';
import useConsentStore from 'src/core/consent/consent.store';
import { GatewayClient } from 'src/core/telemetry/blog-analytics/gateway-client';
import createConsentEventHandler from 'src/core/telemetry/blog-analytics/consent-event-handler';
import createTelemetryEventHandler from 'src/core/telemetry/blog-analytics/telemetry-event-handler';
import { trackSessionSnapshot } from 'src/app/service/blog-analytics-session-snapshot.service';

/**
 * @private
 */
export default async function (): Promise<WatchHandle | undefined> {
    const analyticsGatewayUrl = Contena.Store.get('context').app.analyticsGatewayUrl;

    if (!analyticsGatewayUrl) {
        return;
    }

    /*
     * register consent event handler
     */

    const gatewayClient = new GatewayClient(analyticsGatewayUrl, await getDefaultLanguageName());

    const consentEventHandler = createConsentEventHandler(gatewayClient);

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Contena.Utils.EventBus.on('consent', consentEventHandler);

    /*
     * initialize blog analytics
     */
    const consentStore = useConsentStore();
    const isTelemetryConsentAccepted = computed((): boolean => {
        try {
            return consentStore.isAccepted('blog_analytics');
        } catch {
            return false;
        }
    });

    gatewayClient.setOptOut(true);

    const eventHandlers = createTelemetryEventHandler(gatewayClient);

    return watch(
        isTelemetryConsentAccepted,
        (newValue: boolean) => {
            if (newValue) {
                if (!gatewayClient.isInitialized) {
                    gatewayClient.init();
                }

                gatewayClient.setOptOut(false);
                Contena.Utils.EventBus.on('telemetry', eventHandlers);

                Contena.Telemetry.identify();
                void Contena.Application.viewInitialized.then(trackSessionSnapshot);
            } else {
                if (!gatewayClient.isInitialized) {
                    return;
                }

                gatewayClient.setOptOut(true);
                Contena.Utils.EventBus.off('telemetry', eventHandlers);
                void gatewayClient.flushWithoutRetry().finally(() => {
                    deleteUser(gatewayClient);
                    gatewayClient.clearStorage();
                });
            }
        },
        { immediate: true },
    );
}

function deleteUser(client: GatewayClient) {
    const installationId = Contena.Store.get('context').app.config.installationId;
    const admin_userId = Contena.Store.get('session').currentUser?.id ?? null;

    if (typeof installationId === 'string' && typeof admin_userId === 'string') {
        client.deleteUser(installationId, admin_userId);
    }
}

async function getDefaultLanguageName(): Promise<string> {
    const languageRepository = Contena.Service('repositoryFactory').create('language');

    try {
        const defaultLanguage = await languageRepository.get(Contena.Context.api.systemLanguageId!);

        return defaultLanguage!.name;
    } catch {
        return 'N/A';
    }
}

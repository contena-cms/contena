import type { telemetryDispatch } from '@contena-ag/meteor-admin-sdk/es/telemetry';
import type { TrackableType } from '../../core/telemetry/types';

/**
 * @sw-package framework
 * @private
 */
export default function initializeTelemetry(): void {
    Contena.ExtensionAPI.handle('telemetryDispatch', (payload: Omit<telemetryDispatch, 'responseType'>, additionalInfo) => {
        const event = additionalInfo._event_;
        const sourceWindow = event.source != null ? (event.source as Window) : undefined;

        Contena.Telemetry.track({
            eventName: payload.event,
            ...(payload.data as Record<string, TrackableType>),
            source: Contena.Utils.extension.getExtensionNameByOrigin(event.origin, sourceWindow) ?? 'unknown',
        });
    });
}

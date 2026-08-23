import mitt from 'mitt';
import type { TelemetryEvent, EventTypes as TelemetryEventTypes } from '../../telemetry/types';

/**
 * The pattern for event names = component name in kebab case followed by the event
 */
interface Events extends Record<string | symbol, unknown> {
    'ct-language-switch-change-application-language': { languageId: string };
    'ct-media-library-item-updated': string;
    'ct-admin-menu/toggle-offcanvas': boolean;
    telemetry: TelemetryEvent<TelemetryEventTypes>;
}

const emitter = mitt<Events>();

/**
 * @private
 */
export default emitter;

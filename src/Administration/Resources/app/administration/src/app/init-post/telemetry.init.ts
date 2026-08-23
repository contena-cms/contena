/**
 * @private
 */
export default function initializeTracking(): Promise<void> {
    Contena.Telemetry.initialize();

    return Promise.resolve();
}

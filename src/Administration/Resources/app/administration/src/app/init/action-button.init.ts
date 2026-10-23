/**
 * @sw-package framework
 *
 * @private
 */
import '../store/action-buttons.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeActionButtons(): void {
    Contena.ExtensionAPI.handle('actionButtonAdd', (configuration) => {
        Contena.Store.get('actionButtons').add(configuration);
    });
}

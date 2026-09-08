/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import '../store/action-buttons.store';

export default function initializeActionButtons(): void {
    Contena.ExtensionAPI.handle('actionButtonAdd', (configuration) => {
        Contena.Store.get('actionButtons').add(configuration);
    });
}

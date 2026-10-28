/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeMediaModal(): void {
    Contena.ExtensionAPI.handle('uiMediaModalOpen', (modalConfig) => {
        Contena.Store.get('mediaModal').openModal(modalConfig);
    });

    Contena.ExtensionAPI.handle('uiMediaModalOpenSaveMedia', (saveModalConfig) => {
        Contena.Store.get('mediaModal').openSaveModal(saveModalConfig);
    });
}

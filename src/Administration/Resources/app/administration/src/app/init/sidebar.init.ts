/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeSidebar(): void {
    // eslint-disable-next-line @typescript-eslint/require-await
    Contena.ExtensionAPI.handle('uiSidebarAdd', async (sidebarConfig, { _event_ }) => {
        const extension = Object.values(Contena.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        // create sidebar store
        Contena.Store.get('sidebar').addSidebar({
            baseUrl: extension.baseUrl,
            active: false,
            ...sidebarConfig,
        });
    });

    Contena.ExtensionAPI.handle('uiSidebarClose', ({ locationId }) => {
        // Same close path as the panel's close button, so the animation plays too
        Contena.Store.get('sidebar').requestCloseSidebar(locationId);
    });

    Contena.ExtensionAPI.handle('uiSidebarSetActive', ({ locationId }: { locationId: string }) => {
        Contena.Store.get('sidebar').setActiveSidebar(locationId);
    });

    Contena.ExtensionAPI.handle('uiSidebarRemove', ({ locationId }) => {
        Contena.Store.get('sidebar').removeSidebar(locationId);
    });
}

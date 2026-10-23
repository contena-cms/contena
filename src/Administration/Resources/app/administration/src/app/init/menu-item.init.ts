/**
 * @sw-package framework
 *
 * @private
 */
export default function initMenuItems(): void {
    Contena.ExtensionAPI.handle('menuItemAdd', async (menuItemConfig, additionalInformation) => {
        const extension = Object.values(Contena.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        await Contena.Store.get('extensionSdkModules')
            .addModule({
                heading: menuItemConfig.label,
                locationId: menuItemConfig.locationId,
                displaySearchBar: menuItemConfig.displaySearchBar!,
                displaySmartBar: menuItemConfig.displaySmartBar,
                baseUrl: extension.baseUrl,
            })
            .then((moduleId) => {
                if (typeof moduleId !== 'string') {
                    return;
                }

                Contena.Store.get('menuItem').addMenuItem({
                    ...menuItemConfig,
                    moduleId,
                });
            });
    });

    Contena.ExtensionAPI.handle('menuCollapse', () => {
        Contena.Store.get('adminMenu').collapseSidebar();
    });

    Contena.ExtensionAPI.handle('menuExpand', () => {
        Contena.Store.get('adminMenu').expandSidebar();
    });
}

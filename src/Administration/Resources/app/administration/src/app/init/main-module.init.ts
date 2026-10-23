/**
 * @sw-package framework
 *
 * @private
 */
export default function initMainModules(): void {
    Contena.ExtensionAPI.handle('mainModuleAdd', async (mainModuleConfig, additionalInformation) => {
        const extensionName = Object.keys(Contena.Store.get('extensions').extensionsState).find((key) =>
            Contena.Store.get('extensions').extensionsState[key].baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extensionName) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        const extension = Contena.Store.get('extensions').extensionsState?.[extensionName];

        await Contena.Store.get('extensionSdkModules')
            .addModule({
                heading: mainModuleConfig.heading,
                locationId: mainModuleConfig.locationId,
                displaySearchBar: mainModuleConfig.displaySearchBar ?? true,
                baseUrl: extension.baseUrl,
            })
            .then((moduleId) => {
                if (typeof moduleId !== 'string') {
                    return;
                }

                Contena.Store.get('extensionMainModules').addMainModule({
                    extensionName,
                    moduleId,
                });
            });
    });

    Contena.ExtensionAPI.handle('smartBarButtonAdd', (configuration) => {
        Contena.Store.get('extensionSdkModules').addSmartBarButton(configuration);
    });

    Contena.ExtensionAPI.handle('smartBarHide', (configuration) => {
        Contena.Store.get('extensionSdkModules').addHiddenSmartBar(configuration.locationId);
    });
}

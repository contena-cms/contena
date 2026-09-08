/* eslint-disable ct-deprecation-rules/private-feature-declarations */
export type ExtensionSdkModule = {
    id: string;
    heading: string;
    baseUrl: string;
    locationId: string;
    displaySearchBar: boolean;
    displaySmartBar: boolean;
    displayLanguageSwitch: boolean;
};

const extensionSdkModules = Contena.Store.register({
    id: 'extensionSdkModules',
    state: () => ({
        modules: [] as ExtensionSdkModule[],
        smartBarButtons: [] as Record<string, unknown>[],
        hiddenSmartBars: [] as string[],
    }),
    actions: {
        addModule(config: Omit<ExtensionSdkModule, 'id' | 'displaySmartBar' | 'displayLanguageSwitch'> & Partial<Pick<ExtensionSdkModule, 'displaySmartBar' | 'displayLanguageSwitch'>>) {
            const staticElements = {
                ...config,
                displaySmartBar: config.displaySmartBar ?? true,
                displayLanguageSwitch: config.displayLanguageSwitch ?? true,
            };
            const id = Contena.Utils.format.md5(JSON.stringify(staticElements));
            if (!this.modules.some((module) => module.id === id)) this.modules.push({ id, ...staticElements });
            return Promise.resolve(id);
        },
        addSmartBarButton(button: Record<string, unknown>) {
            this.smartBarButtons.push(button);
        },
        addHiddenSmartBar(locationId: string) {
            this.hiddenSmartBars.push(locationId);
        },
    },
    getters: {
        getRegisteredModuleInformation: (state) => (baseUrl: string) => state.modules.filter((module) => module.baseUrl.startsWith(baseUrl)),
    },
});

export type ExtensionSdkModules = ReturnType<typeof extensionSdkModules>;
export default extensionSdkModules;

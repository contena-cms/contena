/* eslint-disable ct-deprecation-rules/private-feature-declarations */
export type ExtensionMainModule = { extensionName: string; moduleId: string };

const extensionMainModules = Contena.Store.register({
    id: 'extensionMainModules',
    state: () => ({ mainModules: [] as ExtensionMainModule[] }),
    actions: {
        addMainModule(module: ExtensionMainModule) {
            this.mainModules.push(module);
        },
    },
});

export type ExtensionMainModules = ReturnType<typeof extensionMainModules>;
export default extensionMainModules;

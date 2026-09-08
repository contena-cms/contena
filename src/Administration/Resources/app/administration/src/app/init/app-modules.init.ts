/* eslint-disable ct-deprecation-rules/private-feature-declarations */
/** Load App-declared administration modules after the API services are ready. */
export default async function initializeAppModules(): Promise<void> {
    const appsStore = Contena.Store.get('contenaApps');

    try {
        const modules = await Contena.Service('appModulesService').fetchAppModules();
        appsStore.setApps(modules);
    } catch {
        // An installation without Apps is a valid state; keep the loaded marker deterministic.
        appsStore.setApps([]);
    }
}

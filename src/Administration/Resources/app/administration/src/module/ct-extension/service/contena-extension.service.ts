import type { RouteLocation } from 'vue-router';
import type { ExtensionStoreActionService, Extension } from './extension-store-action.service';

interface LabeledLocation extends RouteLocation {
    label: string | null;
}

/**
 * Native Plugin subset of Contena's Extension Store service.
 *
 * @private
 */
export default class ContenaExtensionService {
    public constructor(private readonly extensionStoreActionService: ExtensionStoreActionService) {}

    public async installAndActivateExtension(extensionName: string): Promise<void> {
        await this.extensionStoreActionService.installExtension(extensionName, 'plugin');
        await this.extensionStoreActionService.activateExtension(extensionName, 'plugin');
        await this.updateExtensionData();
    }

    public async installExtension(extensionName: string): Promise<void> {
        await this.extensionStoreActionService.installExtension(extensionName, 'plugin');
        await this.updateExtensionData();
    }

    public async updateExtension(extensionName: string, allowNewPrivileges = false): Promise<void> {
        await this.extensionStoreActionService.updateExtension(extensionName, 'plugin', allowNewPrivileges);
        await this.updateExtensionData();
    }

    public async uninstallExtension(extensionName: string, removeData = false): Promise<void> {
        await this.extensionStoreActionService.uninstallExtension(extensionName, 'plugin', removeData);
        await this.updateExtensionData();
    }

    public async removeExtension(extensionName: string, removeData = false): Promise<void> {
        await this.extensionStoreActionService.removeExtension(extensionName, 'plugin', removeData);
        await this.updateExtensionData();
    }

    public async activateExtension(extensionName: string): Promise<void> {
        await this.extensionStoreActionService.activateExtension(extensionName, 'plugin');
    }

    public async deactivateExtension(extensionName: string): Promise<void> {
        await this.extensionStoreActionService.deactivateExtension(extensionName, 'plugin');
    }

    public async updateExtensionData(refreshExtensions = true): Promise<void> {
        Contena.Store.get('contenaExtensions').loadMyExtensions();

        try {
            if (!Contena.Store.get('context').app.config?.settings?.disableExtensionManagement && refreshExtensions) {
                // Discovery is best effort. Loading the local list must still work when a
                // third-party plugin prevents the refresh action from completing.
                try {
                    await this.extensionStoreActionService.refresh();
                } catch {
                    // Keep loading the local list even when discovery is unavailable.
                }
            }

            const myExtensions = await this.extensionStoreActionService.getMyExtensions();
            Contena.Store.get('contenaExtensions').setMyExtensions(
                myExtensions.filter((extension) => extension.type === 'plugin' && !extension.isTheme),
            );
        } finally {
            Contena.Store.get('contenaExtensions').setLoading(false);
        }
    }

    public getOpenLink(extension: Extension): null | LabeledLocation {
        if (!extension.active) {
            return null;
        }

        const entryRoute = Contena.Store.get('extensionEntryRoutes').routes[extension.name];
        if (!entryRoute) {
            return null;
        }

        return {
            name: entryRoute.route,
            label: entryRoute.label ?? null,
        } as LabeledLocation;
    }
}

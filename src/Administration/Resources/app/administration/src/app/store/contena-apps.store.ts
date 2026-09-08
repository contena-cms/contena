/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';

export interface ContenaAppsState {
    apps: AppModuleDefinition[];
    appsLoaded: boolean;
    selectedIds: string[];
}

const contenaApps = Contena.Store.register({
    id: 'contenaApps',
    state: (): ContenaAppsState => ({ apps: [], appsLoaded: false, selectedIds: [] }),
    actions: {
        setApps(apps: AppModuleDefinition[]): void {
            this.apps = apps;
            this.appsLoaded = true;
        },
    },
});

export type ContenaApps = ReturnType<typeof contenaApps>;
export default contenaApps;

/**
 * @sw-package framework
 */
import type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface ContenaAppsState {
    apps: AppModuleDefinition[];
    appsLoaded: boolean;
    selectedIds: string[];
}

const contenaApps = Contena.Store.register({
    id: 'contenaApps',

    state: (): {
        apps: AppModuleDefinition[];
        appsLoaded: boolean;
        selectedIds: string[];
    } => ({
        apps: [],
        /**
         * Whether `apps` reflects a finished fetch — an empty list is ambiguous otherwise
         */
        appsLoaded: false,
        selectedIds: [],
    }),
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type ContenaApps = ReturnType<typeof contenaApps>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default contenaApps;

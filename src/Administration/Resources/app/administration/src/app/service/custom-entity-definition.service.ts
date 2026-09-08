/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import { reactive } from 'vue';

export interface AdminUiFieldsRef { ref: string }
export interface ColumnRef extends AdminUiFieldsRef { hidden?: boolean }
export type AdminUiCardsDefinition = { name: string; fields: AdminUiFieldsRef[] };
export type AdminTabsDefinition = { name: string; cards: AdminUiCardsDefinition[] };
export type AdminUiDetailDefinition = { tabs: AdminTabsDefinition[] };
export type AdminUiListingDefinition = { columns: ColumnRef[] };
export type AdminUiDefinition = {
    navigationParent: string;
    position: number;
    icon: string;
    color: string;
    detail: AdminUiDetailDefinition;
    listing: AdminUiListingDefinition;
};
export type CustomEntityDefinition = {
    entity: string;
    properties: Record<string, { flags: unknown[]; type: string } | undefined>;
    flags: { 'admin-ui': AdminUiDefinition; 'cms-aware': { name: string } };
};

type NavigationMenuEntry = {
    id: string;
    label: string;
    moduleType: string;
    path: string;
    position: number;
    parent: string;
    params: { entityName: string };
    icon: string;
};

export default class CustomEntityDefinitionService {
    #state = reactive({ customEntityDefinitions: [] as CustomEntityDefinition[] });

    addDefinition(customEntityDefinition: CustomEntityDefinition): void {
        this.#state.customEntityDefinitions.push(customEntityDefinition);
    }

    getDefinitionByName(name: string): Readonly<CustomEntityDefinition | undefined> {
        return this.#state.customEntityDefinitions.find((definition) => definition.entity === name);
    }

    getAllDefinitions(): Readonly<CustomEntityDefinition[]> {
        return this.#state.customEntityDefinitions;
    }

    hasDefinitionWithAdminUi(name: string): boolean {
        return this.#state.customEntityDefinitions.some((definition) => definition.entity === name && definition.flags?.['admin-ui']);
    }

    hasDefinitionWithCmsAware(name: string): boolean {
        return this.#state.customEntityDefinitions.some((definition) => definition.entity === name && definition.flags?.['cms-aware']?.name);
    }

    getCmsAwareDefinitions(): Readonly<CustomEntityDefinition[]> {
        return this.#state.customEntityDefinitions.filter((definition) => !!definition.flags?.['cms-aware']?.name);
    }

    getMenuEntries(): Readonly<NavigationMenuEntry[]> {
        return this.#state.customEntityDefinitions.flatMap((definition) => {
            const adminUi = definition.flags?.['admin-ui'];
            if (!adminUi) return [];
            return [{
                id: `custom-entity/${definition.entity}`,
                label: `${definition.entity}.moduleTitle`,
                moduleType: 'plugin',
                path: 'ct.custom.entity.index',
                params: { entityName: definition.entity },
                position: adminUi.position,
                parent: adminUi.navigationParent,
                icon: adminUi.icon,
            }];
        });
    }
}

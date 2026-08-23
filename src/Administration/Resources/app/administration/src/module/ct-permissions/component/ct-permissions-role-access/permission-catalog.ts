/** @private */
export type PermissionRole = {
    dependencies: string[];
    privileges: Array<string | (() => string[])>;
};

/** @private */
export type PermissionMapping = {
    category: 'permissions' | 'additional_permissions';
    key: string | null;
    parent: string | null;
    roles: Record<string, PermissionRole>;
};

type NavigationEntry = {
    id: string;
    parent?: string;
    privilege?: string;
    label?: string;
    icon?: string;
    position?: number;
    moduleType?: 'core' | 'plugin';
};

type SettingsItem = {
    group: string | (() => string);
    privilege?: string;
    label?: string | { label: string; translated?: boolean };
    icon?: string;
};

type SettingsGroups = Record<string, SettingsItem[]>;
type Translate = (key: string) => string;

/** @private */
export type PermissionCatalogResource = PermissionMapping & {
    key: string;
    label: string;
};

/** @private */
export type PermissionCatalogGroup = {
    id: string;
    icon: string;
    label: string;
    resources: PermissionCatalogResource[];
};

type GroupSource = {
    id: string;
    icon: string;
    label: string;
    order: number;
};

function getResourceKey(privilege?: string): string | null {
    return privilege?.split('.')[0] || null;
}

function translateOrFallback(key: string | undefined, fallback: string, translate: Translate): string {
    if (!key) {
        return fallback;
    }

    const translated = translate(key);

    return translated === key ? fallback : translated;
}

function translateRegisteredLabel(label: string | undefined, fallback: string, translate: Translate): string {
    if (!label) {
        return fallback;
    }

    const translated = translate(label);

    if (translated !== label || !label.includes('.')) {
        return translated;
    }

    return fallback;
}

function getSettingsLabel(label: SettingsItem['label']): string | undefined {
    return typeof label === 'string' ? label : label?.label;
}

function getNavigationGroup(entry: NavigationEntry, entriesById: Map<string, NavigationEntry>): NavigationEntry | null {
    const hierarchy: NavigationEntry[] = [];
    const visited = new Set<string>();
    let current: NavigationEntry | undefined = entry;

    while (current && !visited.has(current.id)) {
        hierarchy.push(current);
        visited.add(current.id);
        current = current.parent ? entriesById.get(current.parent) : undefined;
    }

    if (current) {
        return null;
    }

    return hierarchy.filter((item) => item.moduleType === 'plugin').at(-1) ?? hierarchy.at(-1) ?? null;
}

function getSettingsGroupLabel(group: string, translate: Translate): string {
    const snippetKey = `ct-settings.index.tab${group.charAt(0).toUpperCase()}${group.slice(1)}`;
    const groupLabel = translateOrFallback(snippetKey, group, translate);
    const settingsLabel = translate('ct-settings.index.title');

    return settingsLabel === 'ct-settings.index.title' ? groupLabel : `${settingsLabel} · ${groupLabel}`;
}

/**
 * Builds the role editor from the menu and settings registrations that already
 * describe where a function lives. Plugins participate through the same two
 * registrations and do not need a separate permission catalog API.
 *
 * @private
 */
export function buildPermissionCatalog(
    mappings: PermissionMapping[],
    navigation: NavigationEntry[],
    settingsGroups: SettingsGroups,
    translate: Translate,
): PermissionCatalogGroup[] {
    const entriesById = new Map(
        navigation.map((entry) => [
            entry.id,
            entry,
        ]),
    );
    const groupByResource = new Map<string, GroupSource>();
    const orderByGroup = new Map<string, number>();
    const labelByResource = new Map<string, string>();
    const orderedNavigation = [...navigation].sort((first, second) => (first.position ?? 0) - (second.position ?? 0));
    const navigationOrder = new Map(
        orderedNavigation.map((entry, index) => [
            entry.id,
            index,
        ]),
    );

    orderedNavigation.forEach((entry) => {
        const resourceKey = getResourceKey(entry.privilege);
        const group = getNavigationGroup(entry, entriesById);

        if (!resourceKey || !group || groupByResource.has(resourceKey)) {
            return;
        }

        const source = {
            id: `navigation:${group.id}`,
            icon: group.icon ?? 'regular-folder',
            label: translateRegisteredLabel(group.label, group.id, translate),
            order: navigationOrder.get(group.id) ?? 0,
        };

        groupByResource.set(resourceKey, source);
        orderByGroup.set(source.id, source.order);

        if (entry.label) {
            labelByResource.set(resourceKey, entry.label);
        }
    });

    Object.entries(settingsGroups).forEach(
        (
            [
                registeredGroup,
                items,
            ],
            index,
        ) => {
            const group = items.map((item) => (typeof item.group === 'function' ? item.group() : item.group)).find(Boolean);
            const groupName = group || registeredGroup;
            const source: GroupSource = {
                id: `settings:${groupName}`,
                icon: items.find((item) => item.icon)?.icon ?? 'regular-cog',
                label: getSettingsGroupLabel(groupName, translate),
                order: 100_000 + index,
            };

            items.forEach((item) => {
                const resourceKey = getResourceKey(item.privilege);

                if (!resourceKey) {
                    return;
                }

                if (!groupByResource.has(resourceKey)) {
                    groupByResource.set(resourceKey, source);
                    orderByGroup.set(source.id, source.order);
                }

                const label = getSettingsLabel(item.label);
                if (label && !labelByResource.has(resourceKey)) {
                    labelByResource.set(resourceKey, label);
                }
            });
        },
    );

    const groups = new Map<string, PermissionCatalogGroup>();

    mappings
        .filter((mapping): mapping is PermissionMapping & { key: string } => {
            return mapping.category === 'permissions' && typeof mapping.key === 'string';
        })
        .forEach((mapping) => {
            const source = groupByResource.get(mapping.key) ?? {
                id: 'other',
                icon: 'regular-question-circle',
                label: translate('ct-permissions.roles.catalog.groups.other'),
                order: Number.MAX_SAFE_INTEGER,
            };
            const group = groups.get(source.id) ?? {
                id: source.id,
                icon: source.icon,
                label: source.label,
                resources: [],
            };
            orderByGroup.set(source.id, source.order);
            const registeredLabel = translateRegisteredLabel(labelByResource.get(mapping.key), mapping.key, translate);

            group.resources.push({
                ...mapping,
                key: mapping.key,
                label: translateOrFallback(`ct-privileges.permissions.${mapping.key}.label`, registeredLabel, translate),
            });
            groups.set(source.id, group);
        });

    return [...groups.values()]
        .map((group) => ({
            ...group,
            resources: group.resources.sort((first, second) => first.label.localeCompare(second.label)),
        }))
        .sort((first, second) => {
            return (
                (orderByGroup.get(first.id) ?? Number.MAX_SAFE_INTEGER) -
                (orderByGroup.get(second.id) ?? Number.MAX_SAFE_INTEGER)
            );
        });
}

<template>
    <ct-block name="ct_permissions_role_access">
        <mt-card
            class="ct-permissions-role-access"
            position-identifier="ct-permissions-role-access"
            :is-loading="isLoading"
            :title="t('ct-permissions.roles.grid.title')"
        >
            <template v-if="role">
                <ct-block name="ct_permissions_role_access_toolbar">
                    <div class="ct-permissions-role-access__toolbar">
                        <mt-search
                            v-model="searchTerm"
                            size="small"
                            :placeholder="t('ct-permissions.roles.catalog.searchPlaceholder')"
                        />

                        <mt-checkbox
                            :checked="showSelectedOnly"
                            :label="t('ct-permissions.roles.catalog.showSelectedOnly')"
                            @update:checked="showSelectedOnly = $event"
                        />

                        <mt-button
                            variant="secondary"
                            size="small"
                            :disabled="disabled || !selectedGroup || selectedResourceCount(selectedGroup) === 0"
                            @click="clearSelectedGroup"
                        >
                            {{ t('ct-permissions.roles.catalog.clearGroup') }}
                        </mt-button>
                    </div>
                    <div v-if="!acl.isAdmin()" class="ct-permissions-role-access__security-note" role="status">
                        {{ t('ct-permissions.roles.catalog.grantLimitNotice') }}
                    </div>
                    <div v-if="lastChange" class="ct-permissions-role-access__change-summary" role="status">
                        <strong>{{ t('ct-permissions.roles.catalog.changeSummary') }}</strong>
                        <span v-if="lastChange.added.length">
                            {{
                                t('ct-permissions.roles.catalog.dependenciesAdded', {
                                    permissions: lastChange.added.map(permissionLabel).join(', '),
                                })
                            }}
                        </span>
                        <span v-if="lastChange.removed.length">
                            {{
                                t('ct-permissions.roles.catalog.permissionsRemoved', {
                                    permissions: lastChange.removed.map(permissionLabel).join(', '),
                                })
                            }}
                        </span>
                        <span v-if="lastChange.blocked.length">
                            {{
                                t('ct-permissions.roles.catalog.permissionsNotGranted', {
                                    permissions: lastChange.blocked.map(permissionLabel).join(', '),
                                })
                            }}
                        </span>
                    </div>
                </ct-block>

                <ct-block name="ct_permissions_role_access_content">
                    <div v-if="filteredCatalog.length > 0" class="ct-permissions-role-access__layout">
                        <ct-block name="ct_permissions_role_access_navigation">
                            <nav
                                class="ct-permissions-role-access__navigation"
                                :aria-label="t('ct-permissions.roles.catalog.navigationLabel')"
                            >
                                <mt-button
                                    v-for="group in filteredCatalog"
                                    :key="group.id"
                                    class="ct-permissions-role-access__navigation-item"
                                    :class="{ 'is--active': group.id === selectedGroup?.id }"
                                    variant="secondary"
                                    size="small"
                                    @click="selectGroup(group.id)"
                                >
                                    <mt-icon :name="group.icon" size="16px" />
                                    <span>{{ group.label }}</span>
                                    <mt-badge variant="neutral"
                                        >{{ selectedResourceCount(group) }}/{{ group.resources.length }}</mt-badge
                                    >
                                </mt-button>
                            </nav>
                        </ct-block>

                        <ct-block name="ct_permissions_role_access_resources">
                            <section v-if="selectedGroup" class="ct-permissions-role-access__resources">
                                <header class="ct-permissions-role-access__resources-header">
                                    <div>
                                        <h3>{{ selectedGroup.label }}</h3>
                                        <p>{{ t('ct-permissions.roles.catalog.groupDescription') }}</p>
                                    </div>
                                </header>

                                <div class="ct-permissions-role-access__matrix-wrapper">
                                    <table class="ct-permissions-role-access__matrix">
                                        <thead>
                                            <tr>
                                                <th scope="col">{{ t('ct-permissions.roles.catalog.function') }}</th>
                                                <th v-for="action in groupActions" :key="action" scope="col">
                                                    {{ actionLabel(action) }}
                                                </th>
                                                <th scope="col">{{ t('ct-permissions.roles.catalog.quickActions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template v-for="resource in selectedGroup.resources" :key="resource.key">
                                                <tr class="ct-permissions-role-access__resource-row">
                                                    <th scope="row">
                                                        <span class="ct-permissions-role-access__resource-name">
                                                            <strong>{{ resource.label }}</strong>
                                                            <code>{{ resource.key }}</code>
                                                        </span>
                                                    </th>
                                                    <td v-for="action in groupActions" :key="action">
                                                        <span
                                                            v-if="resource.roles[action]"
                                                            class="ct-permissions-role-access__action-cell"
                                                        >
                                                            <mt-checkbox
                                                                v-tooltip="{
                                                                    message: permissionTooltip(resource.key, action),
                                                                    showOnDisabledElements: true,
                                                                }"
                                                                :checked="isPermissionSelected(resource.key, action)"
                                                                :disabled="isPermissionDisabled(resource.key, action)"
                                                                :aria-label="`${resource.label} · ${actionLabel(action)}`"
                                                                @update:checked="togglePermission(resource.key, action)"
                                                            />
                                                            <mt-icon
                                                                v-if="isPermissionRequired(resource.key, action)"
                                                                v-tooltip="{
                                                                    message: requiredByLabel(resource.key, action),
                                                                }"
                                                                class="ct-permissions-role-access__dependency-icon"
                                                                name="regular-link"
                                                                size="14px"
                                                            />
                                                            <mt-icon
                                                                v-else-if="isSelectedButUnavailable(resource.key, action)"
                                                                v-tooltip="{
                                                                    message: t(
                                                                        'ct-permissions.roles.catalog.selectedButUnavailable',
                                                                    ),
                                                                }"
                                                                class="ct-permissions-role-access__unavailable-icon"
                                                                name="regular-lock"
                                                                size="14px"
                                                            />
                                                        </span>
                                                        <span v-else class="ct-permissions-role-access__not-applicable"
                                                            >—</span
                                                        >
                                                    </td>
                                                    <td>
                                                        <span class="ct-permissions-role-access__row-actions">
                                                            <mt-button
                                                                v-if="resource.roles.viewer"
                                                                variant="secondary"
                                                                size="small"
                                                                :disabled="disabled || !canApplyLevel(resource, 'view')"
                                                                @click="setPermissionLevel(resource, 'view')"
                                                            >
                                                                {{ t('ct-permissions.roles.catalog.readOnly') }}
                                                            </mt-button>
                                                            <mt-button
                                                                variant="secondary"
                                                                size="small"
                                                                :disabled="
                                                                    disabled ||
                                                                    (!resourceIsFullySelected(resource) &&
                                                                        !canApplyLevel(resource, 'manage'))
                                                                "
                                                                @click="
                                                                    setPermissionLevel(
                                                                        resource,
                                                                        resourceIsFullySelected(resource)
                                                                            ? 'none'
                                                                            : 'manage',
                                                                    )
                                                                "
                                                            >
                                                                {{
                                                                    t(
                                                                        resourceIsFullySelected(resource)
                                                                            ? 'ct-permissions.roles.catalog.clear'
                                                                            : 'ct-permissions.roles.catalog.selectAll',
                                                                    )
                                                                }}
                                                            </mt-button>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr
                                                    v-if="
                                                        crossResourceDependencies(resource).length > 0 ||
                                                        pendingRemoval?.resourceKey === resource.key
                                                    "
                                                    class="ct-permissions-role-access__resource-details"
                                                >
                                                    <td :colspan="groupActions.length + 2">
                                                        <div
                                                            v-for="relation in crossResourceDependencies(resource)"
                                                            :key="`${resource.key}.${relation.action}`"
                                                            class="ct-permissions-role-access__cross-dependency"
                                                        >
                                                            <mt-icon name="regular-link" size="14px" />
                                                            {{
                                                                t('ct-permissions.roles.catalog.crossResourceDependency', {
                                                                    permission: actionLabel(relation.action),
                                                                    dependencies: relation.dependencies
                                                                        .map(permissionLabel)
                                                                        .join(', '),
                                                                })
                                                            }}
                                                        </div>

                                                        <div
                                                            v-if="pendingRemoval?.resourceKey === resource.key"
                                                            class="ct-permissions-role-access__removal-confirmation"
                                                            role="alert"
                                                        >
                                                            <span>
                                                                {{
                                                                    t(
                                                                        'ct-permissions.roles.catalog.removeDependencyQuestion',
                                                                        {
                                                                            permission: permissionLabel(
                                                                                pendingRemoval.identifier,
                                                                            ),
                                                                            dependents: pendingRemoval.dependents
                                                                                .map(permissionLabel)
                                                                                .join(', '),
                                                                        },
                                                                    )
                                                                }}
                                                            </span>
                                                            <span class="ct-permissions-role-access__confirmation-actions">
                                                                <mt-button
                                                                    variant="critical"
                                                                    size="small"
                                                                    @click="confirmPendingRemoval"
                                                                >
                                                                    {{
                                                                        t(
                                                                            'ct-permissions.roles.catalog.removeDependentPermissions',
                                                                        )
                                                                    }}
                                                                </mt-button>
                                                                <mt-button
                                                                    variant="secondary"
                                                                    size="small"
                                                                    @click="cancelPendingRemoval"
                                                                >
                                                                    {{ t('ct-permissions.roles.catalog.keepPermission') }}
                                                                </mt-button>
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="ct_permissions_role_access_empty_state">
                    <mt-empty-state
                        v-if="filteredCatalog.length === 0"
                        icon="regular-search"
                        :headline="t('ct-permissions.roles.catalog.emptyHeadline')"
                        :description="t('ct-permissions.roles.catalog.emptyDescription')"
                    />
                </ct-block>
            </template>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import type AclService from 'src/app/service/acl.service';
import type PrivilegesService from 'src/app/service/privileges.service';
import {
    buildPermissionCatalog,
    type PermissionCatalogGroup,
    type PermissionCatalogResource,
    type PermissionMapping,
} from './permission-catalog';
import './ct-permissions-role-access.scss';

type RoleEntity = {
    privileges: string[];
};

type PermissionLevel = 'none' | 'view' | 'edit' | 'manage' | 'custom';
type PermissionAction = string;
type PermissionChange = {
    added: string[];
    removed: string[];
    blocked: string[];
};
type PendingRemoval = {
    identifier: string;
    resourceKey: string;
    dependents: string[];
};

type MenuService = {
    getNavigationFromAdminModules(): Array<{
        id: string;
        parent?: string;
        privilege?: string;
        label?: string;
        icon?: string;
        position?: number;
    }>;
};

const STANDARD_ACTIONS = [
    'viewer',
    'editor',
    'creator',
    'deleter',
];

const props = withDefaults(
    defineProps<{
        role?: RoleEntity | null;
        isLoading?: boolean;
        disabled?: boolean;
    }>(),
    {
        role: null,
        isLoading: false,
        disabled: false,
    },
);

const { t } = useI18n();
const role = computed(() => props.role);
const isLoading = computed(() => props.isLoading);
const disabled = computed(() => props.disabled);

const privileges = inject<PrivilegesService>('privileges');
const menuService = inject<MenuService>('menuService');
const acl = inject<AclService>('acl');

if (!privileges || !menuService || !acl) {
    throw new Error('The permissions role access component requires the privileges, menu, and ACL services.');
}

const searchTerm = ref('');
const showSelectedOnly = ref(false);
const selectedGroupId = ref<string | null>(null);
const lastChange = ref<PermissionChange | null>(null);
const pendingRemoval = ref<PendingRemoval | null>(null);

const catalog = computed(() => {
    const mappings = privileges.getPrivilegesMappings() as PermissionMapping[];
    const settingsGroups = Contena.Store.get('settingsItems').settingsGroups;

    return buildPermissionCatalog(mappings, menuService.getNavigationFromAdminModules(), settingsGroups, t);
});
const filteredCatalog = computed(() => {
    const query = searchTerm.value.trim().toLocaleLowerCase();

    return catalog.value
        .map((group) => ({
            ...group,
            resources: group.resources.filter((resource) => {
                const matchesSearch =
                    !query || resource.label.toLocaleLowerCase().includes(query) || resource.key.includes(query);
                const matchesSelection =
                    !showSelectedOnly.value ||
                    availableActions(resource).some((action) => isPermissionSelected(resource.key, action));

                return matchesSearch && matchesSelection;
            }),
        }))
        .filter((group) => group.resources.length > 0);
});
const selectedGroup = computed(() => {
    return filteredCatalog.value.find((group) => group.id === selectedGroupId.value) ?? filteredCatalog.value[0] ?? null;
});
const groupActions = computed(() => {
    if (!selectedGroup.value) {
        return [];
    }

    const actions = selectedGroup.value.resources.flatMap(availableActions);
    const uniqueActions = actions.filter((action, index) => actions.indexOf(action) === index);

    return [
        ...STANDARD_ACTIONS.filter((action) => uniqueActions.includes(action)),
        ...uniqueActions.filter((action) => !STANDARD_ACTIONS.includes(action)).sort(),
    ];
});

const selectGroup = (groupId: string) => {
    selectedGroupId.value = groupId;
    pendingRemoval.value = null;
};
const availableActions = (resource: PermissionCatalogResource): PermissionAction[] => {
    const registeredActions = Object.keys(resource.roles);
    const standardActions = STANDARD_ACTIONS.filter((action) => registeredActions.includes(action));
    const customActions = registeredActions.filter((action) => !STANDARD_ACTIONS.includes(action)).sort();

    return [
        ...standardActions,
        ...customActions,
    ];
};
const actionLabel = (action: string): string => {
    const snippetKey = `ct-privileges.roles.${action}`;
    const translated = t(snippetKey);

    return translated === snippetKey ? action : translated;
};
const isPermissionSelected = (resourceKey: string, action: string) => {
    return role.value?.privileges.includes(`${resourceKey}.${action}`) ?? false;
};
const requiredBy = (resourceKey: string, action: string): string[] => {
    const identifier = `${resourceKey}.${action}`;

    return (
        role.value?.privileges.filter((selectedPrivilege) => {
            return privileges.getPrivilegeRole(selectedPrivilege)?.dependencies.includes(identifier) ?? false;
        }) ?? []
    );
};
const canGrantPermission = (identifier: string, visited = new Set<string>()): boolean => {
    if (visited.has(identifier)) {
        return true;
    }

    visited.add(identifier);
    if (!acl.can(identifier)) {
        return false;
    }

    return (
        privileges.getPrivilegeRole(identifier)?.dependencies.every((dependency) => {
            return canGrantPermission(dependency, visited);
        }) ?? true
    );
};
const permissionLabel = (identifier: string): string => {
    const [
        resourceKey,
        action,
    ] = identifier.split('.');
    const resource = catalog.value.flatMap((group) => group.resources).find((item) => item.key === resourceKey);

    return resource ? `${resource.label} · ${actionLabel(action)}` : identifier;
};
const crossResourceDependencies = (resource: PermissionCatalogResource) => {
    return availableActions(resource)
        .filter((action) => isPermissionSelected(resource.key, action))
        .map((action) => ({
            action,
            dependencies: (privileges.getPrivilegeRole(`${resource.key}.${action}`)?.dependencies ?? []).filter(
                (dependency) => dependency.split('.')[0] !== resource.key,
            ),
        }))
        .filter((relation) => relation.dependencies.length > 0);
};
const selectedResourceCount = (group: PermissionCatalogGroup) => {
    return group.resources.filter((resource) => permissionLevel(resource) !== 'none').length;
};
const isPermissionRequired = (resourceKey: string, action: string) => {
    const identifier = `${resourceKey}.${action}`;

    return (
        role.value?.privileges.some((selectedPrivilege) => {
            if (selectedPrivilege === identifier) {
                return false;
            }

            return privileges.getPrivilegeRole(selectedPrivilege)?.dependencies.includes(identifier) ?? false;
        }) ?? false
    );
};
const requiredByLabel = (resourceKey: string, action: string): string => {
    return t('ct-permissions.roles.catalog.requiredBy', {
        permissions: requiredBy(resourceKey, action).map(permissionLabel).join(', '),
    });
};
const isSelectedButUnavailable = (resourceKey: string, action: string): boolean => {
    const identifier = `${resourceKey}.${action}`;

    return isPermissionSelected(resourceKey, action) && !canGrantPermission(identifier);
};
const isPermissionDisabled = (resourceKey: string, action: string): boolean => {
    if (disabled.value) {
        return true;
    }

    return !isPermissionSelected(resourceKey, action) && !canGrantPermission(`${resourceKey}.${action}`);
};
const addPermission = (identifier: string) => {
    if (!role.value || role.value.privileges.includes(identifier)) {
        return;
    }

    role.value.privileges.push(identifier);
    privileges.getPrivilegeRole(identifier)?.dependencies.forEach(addPermission);
};
const removePermission = (identifier: string, pendingRemovals: string[] = []) => {
    if (!role.value) {
        return;
    }

    const requiredByRemainingPermission = role.value.privileges.some((selectedPrivilege) => {
        if (selectedPrivilege === identifier || pendingRemovals.includes(selectedPrivilege)) {
            return false;
        }

        return privileges.getPrivilegeRole(selectedPrivilege)?.dependencies.includes(identifier) ?? false;
    });

    if (!requiredByRemainingPermission) {
        role.value.privileges = role.value.privileges.filter((privilege) => privilege !== identifier);
    }
};
const permissionLevel = (resource: PermissionCatalogResource): PermissionLevel => {
    const actions = availableActions(resource);
    const selectedActions = actions.filter((action) => isPermissionSelected(resource.key, action));

    if (selectedActions.length === 0) {
        return 'none';
    }

    if (selectedActions.length === 1 && selectedActions[0] === 'viewer') {
        return 'view';
    }

    const editableActions = actions.filter((action) =>
        [
            'viewer',
            'editor',
        ].includes(action),
    );
    if (
        editableActions.includes('editor') &&
        selectedActions.length === editableActions.length &&
        editableActions.every((action) => selectedActions.includes(action))
    ) {
        return 'edit';
    }

    if (selectedActions.length === actions.length) {
        return 'manage';
    }

    return 'custom';
};
const desiredActionsForLevel = (resource: PermissionCatalogResource, level: PermissionLevel): PermissionAction[] => {
    if (level === 'manage') {
        return availableActions(resource);
    }

    if (level === 'edit') {
        return [
            'viewer',
            'editor',
        ].filter((action) => Boolean(resource.roles[action]));
    }

    if (level === 'view' && resource.roles.viewer) {
        return ['viewer'];
    }

    return [];
};
const canApplyLevel = (resource: PermissionCatalogResource, level: PermissionLevel): boolean => {
    return desiredActionsForLevel(resource, level).every((action) => {
        return isPermissionSelected(resource.key, action) || canGrantPermission(`${resource.key}.${action}`);
    });
};
const resourceIsFullySelected = (resource: PermissionCatalogResource): boolean => {
    const actions = availableActions(resource);

    return actions.length > 0 && actions.every((action) => isPermissionSelected(resource.key, action));
};
const setPermissionLevel = (resource: PermissionCatalogResource, level: PermissionLevel) => {
    if (!role.value || disabled.value) {
        return;
    }

    const normalizedLevel = String(level) as PermissionLevel;
    const actions = availableActions(resource);
    const desiredActions = desiredActionsForLevel(resource, normalizedLevel);

    const blocked = desiredActions
        .map((action) => `${resource.key}.${action}`)
        .filter((identifier) => {
            const [
                resourceKey,
                action,
            ] = identifier.split('.');

            return !isPermissionSelected(resourceKey, action) && !canGrantPermission(identifier);
        });

    if (blocked.length > 0) {
        lastChange.value = { added: [], removed: [], blocked };

        return;
    }

    const previousPrivileges = [...role.value.privileges];

    const pendingRemovals = actions
        .filter((action) => !desiredActions.includes(action))
        .map((action) => `${resource.key}.${action}`);

    pendingRemovals.forEach((identifier) => removePermission(identifier, pendingRemovals));
    desiredActions.forEach((action) => {
        if (actions.includes(action)) {
            addPermission(`${resource.key}.${action}`);
        }
    });
    lastChange.value = {
        added: role.value.privileges.filter((privilege) => !previousPrivileges.includes(privilege)),
        removed: previousPrivileges.filter((privilege) => !role.value?.privileges.includes(privilege)),
        blocked: [],
    };
};
const getDependentPrivileges = (identifier: string, visited = new Set<string>()): string[] => {
    if (!role.value || visited.has(identifier)) {
        return [];
    }

    visited.add(identifier);
    const [
        resourceKey,
        action,
    ] = identifier.split('.');
    const directDependents = requiredBy(resourceKey, action);

    const dependents = [
        ...directDependents,
        ...directDependents.flatMap((dependent) => getDependentPrivileges(dependent, visited)),
    ];

    return dependents.filter((dependent, index) => dependents.indexOf(dependent) === index);
};
const togglePermission = (resourceKey: string, action: PermissionAction) => {
    const identifier = `${resourceKey}.${action}`;

    if (!role.value || disabled.value) {
        return;
    }

    if (isPermissionSelected(resourceKey, action)) {
        const directDependents = requiredBy(resourceKey, action);
        const allDependents = directDependents.flatMap((dependent) => [
            dependent,
            ...getDependentPrivileges(dependent),
        ]);
        const dependents = allDependents.filter((dependent, index) => allDependents.indexOf(dependent) === index);
        if (dependents.length > 0) {
            pendingRemoval.value = { identifier, resourceKey, dependents };
            return;
        }

        const previousPrivileges = [...role.value.privileges];
        removePermission(identifier);
        lastChange.value = {
            added: [],
            removed: previousPrivileges.filter((privilege) => !role.value?.privileges.includes(privilege)),
            blocked: [],
        };
        return;
    }

    pendingRemoval.value = null;
    if (!canGrantPermission(identifier)) {
        lastChange.value = { added: [], removed: [], blocked: [identifier] };

        return;
    }

    const previousPrivileges = [...role.value.privileges];
    addPermission(identifier);
    lastChange.value = {
        added: role.value.privileges.filter((privilege) => !previousPrivileges.includes(privilege)),
        removed: [],
        blocked: [],
    };
};
const confirmPendingRemoval = () => {
    if (!role.value || !pendingRemoval.value) {
        return;
    }

    const previousPrivileges = [...role.value.privileges];
    const removals = [
        pendingRemoval.value.identifier,
        ...pendingRemoval.value.dependents,
    ];

    removals.forEach((identifier) => removePermission(identifier, removals));
    lastChange.value = {
        added: [],
        removed: previousPrivileges.filter((privilege) => !role.value?.privileges.includes(privilege)),
        blocked: [],
    };
    pendingRemoval.value = null;
};
const cancelPendingRemoval = () => {
    pendingRemoval.value = null;
};
const clearSelectedGroup = () => {
    if (!role.value || !selectedGroup.value || disabled.value) {
        return;
    }

    const previousPrivileges = [...role.value.privileges];
    const removals = selectedGroup.value.resources.flatMap((resource) => {
        return availableActions(resource).map((action) => `${resource.key}.${action}`);
    });

    removals.forEach((identifier) => removePermission(identifier, removals));
    lastChange.value = {
        added: [],
        removed: previousPrivileges.filter((privilege) => !role.value?.privileges.includes(privilege)),
        blocked: [],
    };
    pendingRemoval.value = null;
};
const permissionTooltip = (resourceKey: string, action: PermissionAction) => {
    const operation =
        {
            viewer: 'read',
            editor: 'update',
            creator: 'create',
            deleter: 'delete',
        }[action] ?? action;

    const technicalPermission = `${resourceKey}:${operation}`;

    if (!isPermissionSelected(resourceKey, action) && !canGrantPermission(`${resourceKey}.${action}`)) {
        return `${technicalPermission} · ${t('ct-permissions.roles.catalog.cannotGrant')}`;
    }

    if (isPermissionRequired(resourceKey, action)) {
        return `${technicalPermission} · ${requiredByLabel(resourceKey, action)}`;
    }

    if (isSelectedButUnavailable(resourceKey, action)) {
        return `${technicalPermission} · ${t('ct-permissions.roles.catalog.selectedButUnavailable')}`;
    }

    return technicalPermission;
};

watch(
    filteredCatalog,
    (groups) => {
        if (!selectedGroupId.value || !groups.some((group) => group.id === selectedGroupId.value)) {
            selectedGroupId.value = groups[0]?.id ?? null;
        }
    },
    { immediate: true },
);

ctDefinePublic({
    privileges,
    menuService,
    acl,
    searchTerm,
    showSelectedOnly,
    selectedGroupId,
    catalog,
    filteredCatalog,
    selectedGroup,
    groupActions,
    selectGroup,
    selectedResourceCount,
    availableActions,
    permissionLevel,
    setPermissionLevel,
    clearSelectedGroup,
    isPermissionSelected,
    isPermissionRequired,
    isPermissionDisabled,
    isSelectedButUnavailable,
    canApplyLevel,
    resourceIsFullySelected,
    togglePermission,
    permissionTooltip,
    actionLabel,
    requiredByLabel,
    crossResourceDependencies,
    requiredBy,
    canGrantPermission,
    permissionLabel,
    lastChange,
    pendingRemoval,
    confirmPendingRemoval,
    cancelPendingRemoval,
});

defineExpose({
    privileges,
    menuService,
    acl,
    searchTerm,
    showSelectedOnly,
    selectedGroupId,
    catalog,
    filteredCatalog,
    selectedGroup,
    groupActions,
    selectGroup,
    selectedResourceCount,
    availableActions,
    permissionLevel,
    setPermissionLevel,
    clearSelectedGroup,
    isPermissionSelected,
    isPermissionRequired,
    isPermissionDisabled,
    isSelectedButUnavailable,
    canApplyLevel,
    resourceIsFullySelected,
    togglePermission,
    permissionTooltip,
    actionLabel,
    requiredByLabel,
    crossResourceDependencies,
    requiredBy,
    canGrantPermission,
    permissionLabel,
    lastChange,
    pendingRemoval,
    confirmPendingRemoval,
    cancelPendingRemoval,
});
</script>

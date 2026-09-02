<template>
    <ct-block name="ct_permissions_permissions_grid">
        <mt-card
            class="ct-permissions-permissions-grid"
            position-identifier="ct-permissions-permissions-grid"
            :is-loading="isLoading"
            :title="$t('ct-permissions.roles.grid.title')"
        >
            <div v-if="role" class="ct-permissions-permissions-grid__grid">
                <ct-block name="ct_permissions_permissions_grid_header">
                    <div class="ct-permissions-permissions-grid__entry ct-permissions-permissions-grid__entry-header">
                        <ct-block name="ct_permissions_permissions_grid_header_title">
                            <div class="ct-permissions-permissions-grid__title">
                                <ct-block name="ct_permissions_permissions_grid_header_title_content"> </ct-block>
                            </div>
                        </ct-block>

                        <ct-block name="ct_permissions_permissions_grid_header_roles">
                            <div v-for="role in roles" :key="role" class="ct-permissions-permissions-grid__checkbox-wrapper">
                                <ct-block name="ct_permissions_permissions_grid_header_roles_name">
                                    {{ $t('ct-privileges.roles.' + role) }}
                                </ct-block>
                            </div>
                        </ct-block>

                        <ct-block name="ct_permissions_permissions_grid_header_all_roles">
                            <div class="ct-permissions-permissions-grid__all">
                                <ct-block name="ct_permissions_permissions_grid_header_all_roles_name">
                                    {{ $t('ct-privileges.roles.all') }}
                                </ct-block>
                            </div>
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="ct_permissions_permissions_grid_permissions">
                    <template v-for="permission in permissionsWithParents" :key="permission.value">
                        <div
                            v-if="permission.type === 'parent'"
                            :class="'ct-permissions-permissions-grid__parent_' + permission.value"
                            class="ct-permissions-permissions-grid__entry ct-permissions-permissions-grid__parent"
                        >
                            <ct-block name="ct_permissions_permissions_grid_parent_title">
                                <div class="ct-permissions-permissions-grid__title">
                                    <ct-block name="ct_permissions_permissions_grid_parent_title_content">
                                        {{ $t('ct-privileges.permissions.parents.' + (permission.value || 'other')) }}
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-block name="ct_permissions_permissions_grid_parent_roles">
                                <div
                                    v-for="role in roles"
                                    :key="`${permission.value}-${role}`"
                                    :class="'ct-permissions-permissions-grid__role_' + role"
                                    class="ct-permissions-permissions-grid__checkbox-wrapper"
                                >
                                    <ct-block name="ct_permissions_permissions_grid_parent_roles_field">
                                        <mt-checkbox
                                            v-if="parentRoleHasChildRoles(permission.value, role)"
                                            v-tooltip="{
                                                message: parentRoleTooltip(permission.value, role),
                                                showOnDisabledElements: true,
                                            }"
                                            :checked="areAllChildrenRolesSelected(permission.value, role)"
                                            :partial="areSomeChildrenRolesSelected(permission.value, role)"
                                            :disabled="isParentRoleDisabled(permission.value, role) || disabled"
                                            @update:checked="toggleAllChildrenWithRole(permission.value, role)"
                                        />
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-block name="ct_permissions_permissions_grid_parent_all_roles">
                                <div class="ct-permissions-permissions-grid__all ct-permissions-permissions-grid__role_all">
                                    <ct-block name="ct_permissions_permissions_grid_parent_all_roles_field">
                                        <mt-checkbox
                                            v-tooltip="{
                                                message: parentAllTooltip(permission.value),
                                                showOnDisabledElements: true,
                                            }"
                                            :checked="areAllChildrenWithAllRolesSelected(permission.value)"
                                            :partial="areSomeChildrenWithAllRolesSelected(permission.value)"
                                            :disabled="disabled"
                                            @update:checked="toggleAllChildrenWithAllRoles(permission.value)"
                                        />
                                    </ct-block>
                                </div>
                            </ct-block>
                        </div>

                        <div
                            v-else
                            :key="`else-${permission.key}`"
                            :class="[
                                'ct-permissions-permissions-grid__entry_' + permission.key,
                                { 'is--even': permission.groupIndex % 2 === 1 },
                            ]"
                            class="ct-permissions-permissions-grid__entry"
                        >
                            <ct-block name="ct_permissions_permissions_grid_permissions_title">
                                <div class="ct-permissions-permissions-grid__title">
                                    <ct-block name="ct_permissions_permissions_grid_permissions_title_content">
                                        {{ $t('ct-privileges.permissions.' + permission.key + '.label') }}
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-block name="ct_permissions_permissions_grid_permissions_roles">
                                <div
                                    v-for="role in roles"
                                    :key="`else-${permission.key}${role}`"
                                    :class="'ct-permissions-permissions-grid__role_' + role"
                                    :data-privilege="privilegeTooltip(permission.key, role)"
                                    class="ct-permissions-permissions-grid__checkbox-wrapper"
                                >
                                    <ct-block name="ct_permissions_permissions_grid_permissions_roles_field">
                                        <mt-checkbox
                                            v-if="permission.roles[role]"
                                            v-tooltip="{
                                                message:
                                                    isPermissionDisabled(permission.key, role) && !disabled
                                                        ? $t('ct-permissions.roles.grid.disabledCheckboxMessage')
                                                        : privilegeTooltip(permission.key, role),
                                                showOnDisabledElements: true,
                                            }"
                                            :checked="isPermissionSelected(permission.key, role)"
                                            :disabled="isPermissionDisabled(permission.key, role) || disabled"
                                            @update:checked="changePermission(permission.key, role)"
                                        />
                                    </ct-block>
                                </div>
                            </ct-block>

                            <ct-block name="ct_permissions_permissions_grid_permissions_all_roles">
                                <div class="ct-permissions-permissions-grid__all ct-permissions-permissions-grid__role_all">
                                    <ct-block name="ct_permissions_permissions_grid_permissions_all_roles_field">
                                        <mt-checkbox
                                            v-if="Object.keys(permission.roles).length > 0"
                                            v-tooltip="{
                                                message: entityAllTooltip(permission.key),
                                                showOnDisabledElements: true,
                                            }"
                                            :checked="allPermissionsForKeySelected(permission.key)"
                                            :disabled="disabled"
                                            @update:checked="changeAllPermissionsForKey(permission.key)"
                                        />
                                    </ct-block>
                                </div>
                            </ct-block>
                        </div>
                    </template>
                </ct-block>
            </div>
        </mt-card>
    </ct-block>
</template>

<script setup>
import './ct-permissions-permissions-grid.scss';

const props = defineProps({
    role: {
        type: Object,
        required: false,
        default: null,
    },
    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const roleEntity = computed(() => props.role);

const privileges = inject('privileges');

const permissionsWithParents = computed(() => {
    const permissionsWithParents = [];

    parents.value.forEach((parent) => {
        permissionsWithParents.push({
            type: 'parent',
            value: parent,
        });

        const children = getPermissionsForParent(parent);

        children.forEach((child, index) => {
            permissionsWithParents.push({ ...child, groupIndex: index });
        });
    });

    return permissionsWithParents;
});
const permissions = computed(() => {
    const privilegeMappings = privileges.getPrivilegesMappings();

    return privilegeMappings
        .filter((privilege) => privilege.category === 'permissions')
        .sort((a, b) => {
            const labelA = t(`ct-privileges.permissions.${a.key}.label`);
            const labelB = t(`ct-privileges.permissions.${b.key}.label`);

            return labelA.localeCompare(labelB);
        });
});
const parents = computed(() => {
    return permissions.value
        .reduce((parents, privilege) => {
            if (parents.includes(privilege.parent)) {
                return parents;
            }

            return [
                ...parents,
                privilege.parent,
            ];
        }, [])
        .sort((a, b) => {
            const labelA = t(`ct-privileges.permissions.parents.${a || 'other'}`);
            const labelB = t(`ct-privileges.permissions.parents.${b || 'other'}`);

            return labelA.localeCompare(labelB);
        });
});
const usedDependencies = computed(() => {
    const dependencies = new Set();

    roleEntity.value.privileges.forEach((privilegeKey) => {
        const privilegeRole = privileges.getPrivilegeRole(privilegeKey);

        if (!privilegeRole) {
            return;
        }

        privilegeRole.dependencies.forEach((dependency) => {
            dependencies.add(dependency);
        });
    });

    return [...dependencies];
});
const roles = computed(() => {
    return [
        'viewer',
        'editor',
        'creator',
        'deleter',
    ];
});

const changePermission = (permissionKey, permissionRole) => {
    const identifier = `${permissionKey}.${permissionRole}`;

    if (roleEntity.value.privileges.includes(identifier)) {
        removePermission(identifier);
    } else {
        addPermission(identifier);
    }
};
const addPermission = (identifier) => {
    if (roleEntity.value.privileges.includes(identifier)) {
        return;
    }

    roleEntity.value.privileges.push(identifier);

    addDependenciesForRole(identifier);
};
const addDependenciesForRole = (identifier) => {
    const privilegeRole = privileges.getPrivilegeRole(identifier);

    if (!privilegeRole) {
        return;
    }

    privilegeRole.dependencies.forEach((dependencyIdentifier) => {
        addPermission(dependencyIdentifier);
    });
};
const removePermission = (identifier) => {
    roleEntity.value.privileges = roleEntity.value.privileges.filter((privilege) => {
        return privilege !== identifier;
    });
};
const isPermissionSelected = (permissionKey, permissionRole) => {
    return roleEntity.value.privileges.some((privilege) => {
        return privilege === `${permissionKey}.${permissionRole}`;
    });
};
const isPermissionDisabled = (permissionKey, permissionRole) => {
    return usedDependencies.value.includes(`${permissionKey}.${permissionRole}`);
};
const privilegeTooltip = (permissionKey, permissionRole) => {
    const operationMap = {
        viewer: 'read',
        editor: 'update',
        creator: 'create',
        deleter: 'delete',
    };
    return `${permissionKey}:${operationMap[permissionRole] ?? permissionRole}`;
};
const parentRoleTooltip = (parentValue, role) => {
    return t('ct-permissions.roles.grid.tooltipParentRole', {
        role: t(`ct-privileges.roles.${role}`),
        parent: t(`ct-privileges.permissions.parents.${parentValue || 'other'}`),
    });
};
const allRolesLabel = () => {
    return roles.value.map((r) => t(`ct-privileges.roles.${r}`)).join(', ');
};
const parentAllTooltip = (parentValue) => {
    return t('ct-permissions.roles.grid.tooltipParentAll', {
        parent: t(`ct-privileges.permissions.parents.${parentValue || 'other'}`),
        roles: allRolesLabel(),
    });
};
const entityAllTooltip = (permissionKey) => {
    return t('ct-permissions.roles.grid.tooltipEntityAll', {
        entity: t(`ct-privileges.permissions.${permissionKey}.label`),
        roles: allRolesLabel(),
    });
};
const changeAllPermissionsForKey = (permissionKey) => {
    const areAllSelected = allPermissionsForKeySelected(permissionKey);

    roles.value.forEach((role) => {
        const identifier = `${permissionKey}.${role}`;
        const privilegeExists = privileges.existsPrivilege(identifier);

        if (!privilegeExists) {
            return;
        }

        if (areAllSelected) {
            removePermission(identifier);
        } else {
            addPermission(identifier);
        }
    });
};
const allPermissionsForKeySelected = (permissionKey) => {
    const containsUnselected = roles.value.some((permissionRole) => {
        const doesExist = privileges.existsPrivilege(`${permissionKey}.${permissionRole}`);

        if (!doesExist) {
            return false;
        }

        return !isPermissionSelected(permissionKey, permissionRole);
    });

    return !containsUnselected;
};
const getPermissionsForParent = (parentKey) => {
    return permissions.value.filter((permission) => {
        return permission.parent === parentKey;
    });
};
const areAllChildrenRolesSelected = (parentKey, roleKey) => {
    const permissionsForParent = getPermissionsForParent(parentKey);

    const hasUnselected = permissionsForParent.some((permission) => {
        if (permission.roles[roleKey] === undefined) {
            return false;
        }

        return !isPermissionSelected(permission.key, roleKey);
    });

    return !hasUnselected;
};
const areAllChildrenWithAllRolesSelected = (parentKey) => {
    return roles.value.every((roleKey) => {
        return areAllChildrenRolesSelected(parentKey, roleKey);
    });
};
const areSomeChildrenRolesSelected = (parentKey, roleKey, ignoreMissingPrivilege = true) => {
    const permissionsForParent = getPermissionsForParent(parentKey);

    return permissionsForParent.some((permission) => {
        if (!ignoreMissingPrivilege) {
            const privilegeExists = privileges.existsPrivilege(`${permission.key}.${roleKey}`);

            if (!privilegeExists) {
                return true;
            }
        }

        return isPermissionSelected(permission.key, roleKey);
    });
};
const areSomeChildrenWithAllRolesSelected = (parentKey) => {
    return roles.value.every((roleKey) => {
        return areSomeChildrenRolesSelected(parentKey, roleKey, false);
    });
};
const isParentRoleDisabled = (parentKey, roleKey) => {
    const permissionsForParent = getPermissionsForParent(parentKey);

    return permissionsForParent.every((permission) => {
        return isPermissionDisabled(permission.key, roleKey);
    });
};
const toggleAllChildrenWithRole = (parentKey, roleKey) => {
    const permissionsForParent = getPermissionsForParent(parentKey);
    const allChildrenRolesSelected = areAllChildrenRolesSelected(parentKey, roleKey);

    permissionsForParent.forEach((permission) => {
        if (!permission.roles[roleKey]) {
            return;
        }

        const identifier = `${permission.key}.${roleKey}`;

        if (isPermissionDisabled(permission.key, roleKey)) {
            return;
        }

        if (allChildrenRolesSelected) {
            removePermission(identifier);
        } else {
            addPermission(identifier);
        }
    });
};
const toggleAllChildrenWithAllRoles = (parentKey) => {
    const permissionsForParent = getPermissionsForParent(parentKey);
    const allChildrenWithAllRolesSelected = areAllChildrenWithAllRolesSelected(parentKey);

    return roles.value.forEach((roleKey) => {
        permissionsForParent.forEach((permission) => {
            const identifier = `${permission.key}.${roleKey}`;

            if (allChildrenWithAllRolesSelected) {
                removePermission(identifier);
            } else {
                addPermission(identifier);
            }
        });
    });
};
const parentRoleHasChildRoles = (parentKey, roleKey) => {
    return getPermissionsForParent(parentKey).some((currentRole) => {
        return currentRole.roles[roleKey] !== undefined;
    });
};

ctDefinePublic({
    privileges,
    permissionsWithParents,
    permissions,
    parents,
    usedDependencies,
    roles,
    changePermission,
    addPermission,
    addDependenciesForRole,
    removePermission,
    isPermissionSelected,
    isPermissionDisabled,
    privilegeTooltip,
    parentRoleTooltip,
    allRolesLabel,
    parentAllTooltip,
    entityAllTooltip,
    changeAllPermissionsForKey,
    allPermissionsForKeySelected,
    getPermissionsForParent,
    areAllChildrenRolesSelected,
    areAllChildrenWithAllRolesSelected,
    areSomeChildrenRolesSelected,
    areSomeChildrenWithAllRolesSelected,
    isParentRoleDisabled,
    toggleAllChildrenWithRole,
    toggleAllChildrenWithAllRoles,
    parentRoleHasChildRoles,
});

defineExpose({
    privileges,
    permissionsWithParents,
    permissions,
    parents,
    usedDependencies,
    roles,
    changePermission,
    addPermission,
    addDependenciesForRole,
    removePermission,
    isPermissionSelected,
    isPermissionDisabled,
    privilegeTooltip,
    parentRoleTooltip,
    allRolesLabel,
    parentAllTooltip,
    entityAllTooltip,
    changeAllPermissionsForKey,
    allPermissionsForKeySelected,
    getPermissionsForParent,
    areAllChildrenRolesSelected,
    areAllChildrenWithAllRolesSelected,
    areSomeChildrenRolesSelected,
    areSomeChildrenWithAllRolesSelected,
    isParentRoleDisabled,
    toggleAllChildrenWithRole,
    toggleAllChildrenWithAllRoles,
    parentRoleHasChildRoles,
});
</script>

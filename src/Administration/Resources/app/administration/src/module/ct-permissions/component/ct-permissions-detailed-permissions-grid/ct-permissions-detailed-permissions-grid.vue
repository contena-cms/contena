<template>
    <ct-block name="ct_permissions_detailed_permissions_grid">
        <mt-card
            class="ct-permissions-detailed-permissions-grid"
            position-identifier="ct-permissions-detailed-permissions-grid"
            :is-loading="isLoading"
            :title="$t('ct-permissions.roles.grid.title')"
        >
            <div v-if="role" class="ct-permissions-detailed-permissions-grid__grid">
                <ct-block name="ct_permissions_detailed_permissions_grid_header">
                    <div
                        class="ct-permissions-detailed-permissions-grid__entry ct-permissions-detailed-permissions-grid__entry-header"
                    >
                        <ct-block name="ct_permissions_detailed_permissions_grid_header_title">
                            <div class="ct-permissions-detailed-permissions-grid__title"></div>
                        </ct-block>

                        <ct-block name="ct_permissions_detailed_permissions_grid_header_roles">
                            <div
                                v-for="permissionType in permissionTypes"
                                :key="permissionType"
                                class="ct-permissions-detailed-permissions-grid__checkbox-wrapper"
                            >
                                <ct-block name="ct_permissions_detailed_permissions_grid_header_roles_name">
                                    {{ $t('ct-privileges.permissionType.' + permissionType) }}
                                </ct-block>
                            </div>
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="ct_permissions_detailed_permissions_grid_permissions">
                    <div
                        v-for="entity in allEntities"
                        :key="entity"
                        :class="'ct-permissions-detailed-permissions-grid__entry_' + entity"
                        class="ct-permissions-detailed-permissions-grid__entry"
                    >
                        <ct-block name="ct_permissions_detailed_permissions_grid_permissions_title">
                            <div class="ct-permissions-detailed-permissions-grid__title">
                                {{ entity }}
                            </div>
                        </ct-block>

                        <ct-block name="ct_permissions_detailed_permissions_grid_permissions_roles">
                            <div
                                v-for="permissionType in permissionTypes"
                                :key="permissionType"
                                :class="'ct-permissions-detailed-permissions-grid__role_' + permissionType"
                                :data-privilege="`${entity}:${permissionType}`"
                                class="ct-permissions-detailed-permissions-grid__checkbox-wrapper"
                            >
                                <ct-block name="ct_permissions_detailed_permissions_grid_permissions_roles_field">
                                    <mt-checkbox
                                        v-tooltip="{
                                            message:
                                                isEntityDisabled(entity, permissionType) && !disabled
                                                    ? $t('ct-permissions.roles.detailed-grid.coveredByGeneralMessage')
                                                    : `${entity}:${permissionType}`,
                                            showOnDisabledElements: true,
                                        }"
                                        :checked="isEntitySelected(entity, permissionType)"
                                        :disabled="isEntityDisabled(entity, permissionType)"
                                        @update:checked="changePermissionForEntity(entity, permissionType)"
                                    />
                                </ct-block>
                            </div>
                        </ct-block>
                    </div>
                </ct-block>
            </div>
        </mt-card>
    </ct-block>
</template>

<script setup>
import './ct-permissions-detailed-permissions-grid.scss';

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
    detailedPrivileges: {
        type: Array,
        required: true,
    },
});

import { computed, inject } from 'vue';

const selectedDetailedPrivileges = computed(() => props.detailedPrivileges);

const privileges = inject('privileges');
const acl = inject('acl', { can: () => true });

const allEntities = computed(() => {
    const entitiesMap = Contena.Application.getContainer('factory').entityDefinition.getDefinitionRegistry();

    return [...entitiesMap.keys()];
});
const allGeneralSelectedPrivileges = computed(() => {
    return [
        ...new Set([
            ...privileges.getPrivilegesForAdminPrivilegeKeys(props.role.privileges),
            ...privileges.getDefaultUserPrivileges(),
        ]),
    ];
});
const permissionTypes = computed(() => {
    return [
        'read',
        'update',
        'create',
        'delete',
    ];
});

const isEntitySelected = (entity, role) => {
    const identifier = `${entity}:${role}`;

    const allPrivileges = [
        ...allGeneralSelectedPrivileges.value,
        ...selectedDetailedPrivileges.value,
    ];

    return allPrivileges.includes(identifier);
};
const isEntityDisabled = (entity, role) => {
    if (props.disabled) {
        return true;
    }

    const identifier = `${entity}:${role}`;

    return (
        allGeneralSelectedPrivileges.value.includes(identifier) || (!isEntitySelected(entity, role) && !acl.can(identifier))
    );
};
const changePermissionForEntity = (entity, role) => {
    const identifier = `${entity}:${role}`;

    const privilegeIndex = selectedDetailedPrivileges.value.indexOf(identifier);

    if (privilegeIndex >= 0) {
        selectedDetailedPrivileges.value.splice(privilegeIndex, 1);
        return;
    }

    if (!acl.can(identifier)) {
        return;
    }

    selectedDetailedPrivileges.value.push(identifier);
};

ctDefinePublic({
    privileges,
    allEntities,
    allGeneralSelectedPrivileges,
    permissionTypes,
    isEntitySelected,
    isEntityDisabled,
    changePermissionForEntity,
});

defineExpose({
    privileges,
    allEntities,
    allGeneralSelectedPrivileges,
    permissionTypes,
    isEntitySelected,
    isEntityDisabled,
    changePermissionForEntity,
});
</script>

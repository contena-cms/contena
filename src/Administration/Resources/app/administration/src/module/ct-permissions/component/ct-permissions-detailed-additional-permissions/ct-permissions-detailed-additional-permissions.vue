<template>
    <ct-block name="ct_permissions_detailed_additional_permissions">
        <mt-card
            class="ct-permissions-detailed-additional-permissions"
            position-identifier="ct-permissions-detailed-additional-permissions"
            :is-loading="isLoading"
            :title="$t('ct-permissions.roles.detailed-additional-permissions.title')"
        >
            <ct-block name="ct_permissions_detailed_additional_permissions_additional_privileges">
                <template v-if="role">
                    <template v-for="privilege in detailedAdditionalPermissions" :key="privilege.key">
                        <ct-block name="ct_permissions_detailed_additional_permissions_additional_privileges_headline">
                            <h4
                                class="ct-permissions-detailed-additional-permissions__headline"
                                :class="'ct-permissions-additional-permissions_' + privilege.key"
                            >
                                <ct-block
                                    name="ct_permissions_detailed_additional_permissions_additional_privileges_headline_content"
                                >
                                    {{ $t('ct-privileges.additional_permissions.' + privilege.key + '.label') }}
                                </ct-block>
                            </h4>
                        </ct-block>

                        <ct-block name="ct_permissions_detailed_additional_permissions_additional_privileges_switches">
                            <div class="ct-permissions-detailed-additional-permissions__switches">
                                <ct-block
                                    name="ct_permissions_detailed_additional_permissions_additional_privileges_switches_content"
                                >
                                    <template v-for="(value, roleName) in privilege.roles" :key="roleName">
                                        <ct-block
                                            name="ct_permissions_detailed_additional_permissions_additional_privileges_switches_content_switch"
                                        >
                                            <mt-switch
                                                :class="
                                                    'ct_permissions_detailed_additional_permissions_' +
                                                    privilege.key +
                                                    '_' +
                                                    roleName
                                                "
                                                :model-value="isEntitySelected(roleName)"
                                                :disabled="isEntityDisabled(roleName) || disabled"
                                                :label="roleName"
                                                :bordered="true"
                                                @update:model-value="changePermissionForEntity(roleName)"
                                            />
                                        </ct-block>
                                    </template>
                                </ct-block>
                            </div>
                        </ct-block>
                    </template>
                </template>
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup>
import './ct-permissions-detailed-additional-permissions.scss';

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

import { ref, computed, inject } from 'vue';

const selectedDetailedPrivileges = computed(() => props.detailedPrivileges);

const privileges = inject('privileges');
const acl = inject('acl', { can: () => true });
const aclApiService = inject('aclApiService');

const detailedAdditionalPermissions = ref([]);

const allGeneralSelectedPrivileges = computed(() => {
    return privileges.getPrivilegesForAdminPrivilegeKeys(props.role.privileges);
});

const createdComponent = () => {
    setDetailedAdditionalPermissions();
};
const setDetailedAdditionalPermissions = () => {
    aclApiService.additionalPrivileges().then((additionalPrivileges) => {
        const roles = {};
        additionalPrivileges.forEach((privilege) => {
            roles[privilege] = {
                privileges: [privilege],
                dependencies: [],
            };
        });

        detailedAdditionalPermissions.value.push({
            category: 'additional_permissions',
            parent: null,
            key: 'routes',
            roles: roles,
        });
    });
};
const isEntitySelected = (identifier) => {
    const allPrivileges = [
        ...allGeneralSelectedPrivileges.value,
        ...selectedDetailedPrivileges.value,
    ];

    return allPrivileges.includes(identifier);
};
const isEntityDisabled = (identifier) => {
    if (props.disabled) {
        return true;
    }

    return (
        allGeneralSelectedPrivileges.value.includes(identifier) || (!isEntitySelected(identifier) && !acl.can(identifier))
    );
};
const changePermissionForEntity = (identifier) => {
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

createdComponent();

ctDefinePublic({
    privileges,
    aclApiService,
    detailedAdditionalPermissions,
    allGeneralSelectedPrivileges,
    createdComponent,
    setDetailedAdditionalPermissions,
    isEntitySelected,
    isEntityDisabled,
    changePermissionForEntity,
});

defineExpose({
    privileges,
    aclApiService,
    detailedAdditionalPermissions,
    allGeneralSelectedPrivileges,
    createdComponent,
    setDetailedAdditionalPermissions,
    isEntitySelected,
    isEntityDisabled,
    changePermissionForEntity,
});
</script>

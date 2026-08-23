<template>
    <ct-block name="sw_permissions_additional_permissions">
        <mt-card
            class="ct-permissions-additional-permissions"
            position-identifier="ct-permissions-additional-permissions"
            :is-loading="isLoading"
            :title="$t('ct-permissions.roles.additional-permissions.title')"
        >
            <template v-if="role">
                <ct-block name="sw_permissions_additional_permissions_additional_privileges">
                    <template v-for="privilege in additionalPermissions" :key="`head-${privilege.key}`">
                        <ct-block name="sw_permissions_additional_permissions_additional_privileges_headline">
                            <h4
                                class="ct-permissions-additional-permissions__headline"
                                :class="' ct-permissions-additional-permissions_' + privilege.key"
                            >
                                <ct-block
                                    name="sw_permissions_additional_permissions_additional_privileges_headline_content"
                                >
                                    {{ $t('ct-privileges.additional_permissions.' + privilege.key + '.label') }}
                                </ct-block>
                            </h4>
                        </ct-block>

                        <ct-block name="sw_permissions_additional_permissions_additional_privileges_switches">
                            <div class="ct-permissions-additional-permissions__switches">
                                <ct-block
                                    name="sw_permissions_additional_permissions_additional_privileges_switches_content"
                                >
                                    <template v-for="(value, roleName) in privilege.roles" :key="roleName">
                                        <ct-block
                                            name="sw_permissions_additional_permissions_additional_privileges_switches_content_switch"
                                        >
                                            <mt-switch
                                                :disabled="
                                                    disabled ||
                                                    (!isPrivilegeSelected(privilege.key + '.' + roleName) &&
                                                        !acl.can(privilege.key + '.' + roleName))
                                                "
                                                :class="
                                                    'sw_permissions_additional_permissions_' + privilege.key + '_' + roleName
                                                "
                                                :model-value="isPrivilegeSelected(privilege.key + '.' + roleName)"
                                                :label="
                                                    $t(
                                                        'ct-privileges.additional_permissions.' +
                                                            privilege.key +
                                                            '.' +
                                                            roleName,
                                                    )
                                                "
                                                :bordered="true"
                                                @update:model-value="
                                                    onSelectPrivilege(privilege.key + '.' + roleName, $event)
                                                "
                                            />
                                        </ct-block>
                                    </template>
                                </ct-block>
                            </div>
                        </ct-block>
                    </template>
                </ct-block>
            </template>
        </mt-card>
    </ct-block>
</template>

<script setup>
import './ct-permissions-additional-permissions.scss';

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

const roleEntity = computed(() => props.role);

const privileges = inject('privileges');
const acl = inject('acl', { can: () => true });

const additionalPermissions = computed(() => {
    const privilegeMappings = privileges.getPrivilegesMappings();

    return privilegeMappings.filter((privilege) => privilege.category === 'additional_permissions');
});

const isPrivilegeSelected = (privilegeKey) => {
    if (!roleEntity.value.privileges) {
        return false;
    }

    return roleEntity.value.privileges.includes(privilegeKey);
};
const onSelectPrivilege = (privilegeKey, isSelected) => {
    if (isSelected && !acl.can(privilegeKey)) {
        return;
    }

    if (isSelected) {
        roleEntity.value.privileges.push(privilegeKey);
    } else {
        roleEntity.value.privileges = roleEntity.value.privileges.filter((p) => p !== privilegeKey);
    }
};

swDefinePublic({
    privileges,
    acl,
    additionalPermissions,
    isPrivilegeSelected,
    onSelectPrivilege,
});

defineExpose({
    privileges,
    acl,
    additionalPermissions,
    isPrivilegeSelected,
    onSelectPrivilege,
});
</script>

<template>
    <div class="ct-permissions-role-view-additional">
        <ct-block name="sw_permissions_role_view_additional_information">
            <mt-banner variant="info">
                {{ $t('ct-permissions.roles.view.additional.alertText') }}
            </mt-banner>
        </ct-block>

        <ct-block name="sw_permissions_role_view_additional_permissions">
            <ct-permissions-additional-permissions
                v-if="role || isLoading"
                :role="role"
                :is-loading="isLoading"
                :disabled="!acl.can('users_and_permissions.editor') || undefined"
            />
        </ct-block>

        <ct-block name="sw_permissions_role_view_additional_routes">
            <ct-permissions-detailed-additional-permissions
                v-if="role || isLoading"
                :role="role"
                :detailed-privileges="detailedPrivileges"
                :is-loading="isLoading"
                :disabled="!acl.can('users_and_permissions.editor') || undefined"
            />
        </ct-block>
    </div>
</template>

<script setup lang="ts">
import { inject } from 'vue';

import type AclService from 'src/app/service/acl.service';

type RoleEntity = {
    privileges: string[];
};

withDefaults(
    defineProps<{
        role?: RoleEntity | null;
        isLoading?: boolean;
        detailedPrivileges?: string[];
    }>(),
    {
        role: null,
        isLoading: false,
        detailedPrivileges: () => [],
    },
);

const acl = inject<AclService>('acl');

if (!acl) {
    throw new Error('The additional permissions view requires the ACL service.');
}

swDefinePublic({
    acl,
});

defineExpose({ acl });
</script>

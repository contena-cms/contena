<template>
    <div class="ct-permissions-role-view-detailed">
        <mt-banner variant="info" class="ct-permissions-role-view-detailed__banner">
            {{ $t('ct-permissions.roles.view.detailed.alertText') }}
        </mt-banner>

        <ct-permissions-detailed-permissions-grid
            v-if="role || isLoading"
            :role="role"
            :detailed-privileges="detailedPrivileges"
            :is-loading="isLoading"
            :disabled="!acl.can('users_and_permissions.editor') || undefined"
        />
    </div>
</template>

<script setup>
defineProps({
    role: {
        type: Object,
        required: false,
        default: null,
    },
    detailedPrivileges: {
        type: Array,
        required: true,
    },
    isLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { inject } from 'vue';

const acl = inject('acl');

ctDefinePublic({
    acl,
});

defineExpose({
    acl,
});
</script>

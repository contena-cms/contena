<template>
    <ct-block extends="ct_admin_menu_navigation_main">
        <ct-block-parent />

        <ct-channel-menu v-if="canViewChannels" />
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue';
import type AclService from 'src/app/service/acl.service';

const acl = inject<AclService>('acl');
if (!acl) {
    throw new Error('The ACL service is required by the Channel menu extension.');
}

const canViewChannels = computed(() => acl.can('channel.viewer'));

ctDefineOverride({});
</script>

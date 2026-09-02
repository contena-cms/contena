<template>
    <ct-block name="ct_channel_create_base">
        <ct-channel-detail-base :channel="channel" :is-loading="isLoading" create-mode />
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';

defineProps({
    channel: { type: Object as PropType<Entity<'channel'>>, required: true },
    isLoading: { type: Boolean, default: false },
});
ctDefinePublic({});
</script>

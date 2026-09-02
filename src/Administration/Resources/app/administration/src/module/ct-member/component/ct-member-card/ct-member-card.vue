<template>
    <ct-block name="ct_member_card">
        <mt-card :title="title" :is-loading="isLoading" position-identifier="ct-member-card">
            <slot />
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type { PropType } from 'vue';

defineProps({
    title: { type: String, required: true },
    isLoading: { type: Boolean, default: false },
    member: { type: Object as PropType<Entity<'member'>>, required: true },
    editMode: { type: Boolean, default: false },
});

ctDefinePublic({});
</script>

<template>
    <!-- v-on="$listeners" needed becaues vue/compat removes them from $attrs -->
    <!-- New mt-skeleton-bar component -->
    <ct-block name="sw_skeleton_bar">
        <mt-skeleton-bar v-bind="$attrs" />
    </ct-block>
</template>

<script setup lang="ts">
defineProps({});

swDefinePublic({});
</script>

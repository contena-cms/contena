<template>
    <ct-block name="mt_tabs">
        <MtTabs v-bind="$attrs" ref="mtTabsOriginal" :items="items" />
    </ct-block>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';
import MtTabs from '@contena/meteor-component-library/dist/esm/MtTabs';
import type { TabItem } from '@contena/meteor-component-library/dist/esm/MtTabs';

defineProps({
    positionIdentifier: {
        type: String,
        required: true,
        default: null,
    },

    items: {
        type: Array as PropType<TabItem[]>,
        required: true,
    },
});

swDefinePublic({});
</script>

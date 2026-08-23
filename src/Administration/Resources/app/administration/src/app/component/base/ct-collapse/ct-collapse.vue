<template>
    <ct-block name="sw_collapse_panel">
        <div class="ct-collapse">
            <ct-block name="sw_collapse_panel_header">
                <div
                    class="ct-collapse__header"
                    role="button"
                    tabindex="0"
                    @click="collapseItem"
                    @keydown.enter="collapseItem"
                >
                    <slot name="header" v-bind="{ expanded }">
                        <ct-block name="sw_collapse_panel_header_slot"></ct-block>
                    </slot>
                </div>
            </ct-block>

            <ct-block name="sw_collapse_panel_content">
                <div v-if="expanded" class="ct-collapse__content">
                    <slot name="content">
                        <ct-block name="sw_collapse_panel_content_slot"></ct-block>
                    </slot>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
const props = defineProps({
    expandOnLoading: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { ref } from 'vue';

const expanded = ref(props.expandOnLoading);

const collapseItem = () => {
    expanded.value = !expanded.value;
};

swDefinePublic({
    expanded,
    collapseItem,
});

defineExpose({
    expanded,
    collapseItem,
});
</script>

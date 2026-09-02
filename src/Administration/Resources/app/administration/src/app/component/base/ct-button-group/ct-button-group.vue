<template>
    <ct-block name="ct_button_group">
        <span class="ct-button-group" :class="buttonGroupClasses">
            <slot>
                <ct-block name="ct_button_group_slot_default"></ct-block>
            </slot>
        </span>
    </ct-block>
</template>

<script setup>
import './ct-button-group.scss';

const props = defineProps({
    block: {
        type: Boolean,
        required: false,
        default: false,
    },

    splitButton: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { computed } from 'vue';

const buttonGroupClasses = computed(() => {
    return {
        'ct-button-group--block': props.block,
        'ct-button-group--split': props.splitButton,
    };
});

ctDefinePublic({
    buttonGroupClasses,
});

defineExpose({
    buttonGroupClasses,
});
</script>

<template>
    <ct-block name="sw_color_badge">
        <span class="ct-color-badge" :class="variantClass" v-bind="$attrs" :style="colorStyle">
            <slot>
                <ct-block name="sw_color_badfge_slot_default"></ct-block>
            </slot>
        </span>
    </ct-block>
</template>

<script setup>
import './ct-color-badge.scss';

const props = defineProps({
    variant: {
        type: String,
        required: false,
        default: 'default',
    },
    color: {
        type: String,
        required: false,
        default: '',
    },
    rounded: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { computed } from 'vue';

const colorStyle = computed(() => {
    if (!props.color.length) {
        return '';
    }
    return `background:${props.color}`;
});
const variantClass = computed(() => {
    return {
        [`is--${props.variant}`]: true,
        'is--rounded': props.rounded,
    };
});

swDefinePublic({
    colorStyle,
    variantClass,
});

defineExpose({
    colorStyle,
    variantClass,
});
</script>

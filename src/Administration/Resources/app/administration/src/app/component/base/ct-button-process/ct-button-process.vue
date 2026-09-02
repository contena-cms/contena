<template>
    <ct-block name="ct_button_process">
        <mt-button class="ct-button-process" size="default" v-bind="$attrs" :variant="$attrs.variant || 'secondary'">
            <mt-icon v-if="processSuccess" class="ct-button-process__status-indicator" name="regular-checkmark-xs" />
            <span class="ct-button-process__content" :class="contentVisibilityClass">
                <slot></slot>
            </span>
        </mt-button>
    </ct-block>
</template>

<script setup>
import './ct-button-process.scss';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    processSuccess: {
        type: Boolean,
        required: true,
    },

    animationTimeout: {
        type: Number,
        required: false,
        default: 1250,
    },
});
const emit = defineEmits(['update:processSuccess']);

import { computed, inject, watch } from 'vue';

const feature = inject('feature');

const contentVisibilityClass = computed(() => {
    return {
        'is--hidden': props.processSuccess,
    };
});

watch(
    () => props.processSuccess,
    (value) => {
        if (!value) {
            return;
        }

        setTimeout(() => {
            emit('update:processSuccess', false);
        }, props.animationTimeout);
    },
);

ctDefinePublic({
    feature,
    contentVisibilityClass,
});

defineExpose({
    feature,
    contentVisibilityClass,
});
</script>

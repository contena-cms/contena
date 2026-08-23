<template>
    <ct-block name="sw_internal_link">
        <component
            :is="elementType"
            v-if="!disabled"
            :to="routerLink"
            :target="target"
            class="ct-internal-link"
            :class="componentClasses"
            @click="$emit('click')"
        >
            <ct-block name="sw_internal_link__slot">
                <slot></slot>
            </ct-block>

            <ct-block name="sw_internal_link__icon">
                <mt-icon v-if="!hideIcon" :name="icon" size="16px" />
            </ct-block>
        </component>

        <span v-else class="ct-internal-link ct-internal-link--disabled">
            <ct-block name="sw_internal_link__slot">
                <slot></slot>
            </ct-block>

            <ct-block name="sw_internal_link__icon">
                <mt-icon v-if="!hideIcon" :name="icon" size="16px" />
            </ct-block>
        </span>
    </ct-block>
</template>

<script setup>
import './ct-internal-link.scss';

const props = defineProps({
    routerLink: {
        type: Object,
        required: false,
        default: undefined,
    },

    target: {
        type: String,
        required: false,
        default: null,
    },

    icon: {
        type: String,
        required: false,
        default: 'regular-long-arrow-right',
    },

    inline: {
        type: Boolean,
        required: false,
        default: false,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    hideIcon: {
        type: Boolean,
        required: false,
        default: false,
    },
});
defineEmits(['click']);

import { computed } from 'vue';

const elementType = computed(() => {
    return props.routerLink ? 'router-link' : 'a';
});
const componentClasses = computed(() => {
    return {
        'ct-internal-link--inline': props.inline,
    };
});

swDefinePublic({
    elementType,
    componentClasses,
});

defineExpose({
    elementType,
    componentClasses,
});
</script>

<template>
    <ct-block name="ct_external_link">
        <a
            v-if="$attrs.hasOwnProperty('href')"
            v-bind="$attrs"
            target="_blank"
            :rel="rel"
            class="ct-external-link"
            :class="classes"
        >
            <slot></slot>
            <mt-icon class="ct-external-link__icon" :size="iconSize" :name="icon" />
        </a>

        <span
            v-else
            class="ct-external-link"
            :class="classes"
            role="button"
            tabindex="0"
            @click="onClick"
            @keydown.enter="onClick"
        >
            <slot></slot>
            <mt-icon class="ct-external-link__icon" :size="iconSize" :name="icon" />
        </span>
    </ct-block>
</template>

<script setup>
import './ct-external-link.scss';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    small: {
        type: Boolean,
        required: false,
        default: false,
    },

    icon: {
        type: String,
        required: false,
        default: 'regular-external-link-s',
    },

    rel: {
        type: String,
        required: false,
        default: 'noopener',
    },
});
const emit = defineEmits(['click']);

import { computed } from 'vue';

const classes = computed(() => {
    return {
        'ct-external-link--small': props.small,
    };
});
const iconSize = computed(() => {
    if (props.small) {
        return '8px';
    }

    return '10px';
});

const onClick = (event) => {
    emit('click', event);
};

ctDefinePublic({
    classes,
    iconSize,
    onClick,
});

defineExpose({
    classes,
    iconSize,
    onClick,
});
</script>

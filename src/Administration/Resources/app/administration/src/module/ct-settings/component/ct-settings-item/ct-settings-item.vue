<template>
    <ct-block name="sw_settings_item">
        <router-link :to="to" class="ct-settings-item" :class="classes">
            <ct-block name="sw_settings_item_icon">
                <div class="ct-settings-item__icon">
                    <slot name="icon"></slot>
                </div>
            </ct-block>

            <ct-block name="sw_settings_item_label">
                <div class="ct-settings-item__label">
                    <slot name="label">
                        {{ label }}
                    </slot>
                </div>
            </ct-block>
        </router-link>
    </ct-block>
</template>

<script setup>
import './ct-settings-item.scss';

const props = defineProps({
    label: {
        required: true,
        type: String,
    },
    to: {
        required: true,
        type: Object,
        default() {
            return {};
        },
    },
    disabled: {
        required: false,
        type: Boolean,
        default: false,
    },
});

import { computed } from 'vue';

const classes = computed(() => {
    return {
        'is--disabled': props.disabled,
    };
});

swDefinePublic({
    classes,
});

defineExpose({
    classes,
});
</script>

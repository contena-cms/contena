<template>
    <ct-block name="ct_label">
        <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
        <span class="ct-label" :class="labelClasses" @click.stop="$emit('selected')">
            <ct-block name="ct_label_status_color_badge">
                <ct-color-badge v-if="appearance === 'badged'" :variant="variant" :rounded="true" />
            </ct-block>

            <ct-block name="ct_label_text_holder">
                <span class="ct-label__caption">
                    <slot>
                        <ct-block name="ct_label_slot_default"></ct-block>
                    </slot>
                </span>
            </ct-block>

            <ct-block name="ct_label_dismiss">
                <button
                    v-if="showDismissable"
                    class="ct-label__dismiss"
                    :title="$t('global.default.remove')"
                    @mousedown.prevent
                    @click.prevent.stop="$emit('dismiss')"
                >
                    <ct-block name="ct_select_selection_dismiss_icon">
                        <slot name="dismiss-icon">
                            <mt-icon name="regular-times-s" size="8" />
                        </slot>
                    </ct-block>
                </button>
            </ct-block>
        </span>
    </ct-block>
</template>

<script setup>
import './ct-label.scss';

const props = defineProps({
    variant: {
        type: String,
        required: false,
        default: '',
        validValues: [
            'info',
            'danger',
            'success',
            'warning',
            'neutral',
            'neutral-reversed',
            'primary',
        ],
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'info',
                'danger',
                'success',
                'warning',
                'neutral',
                'neutral-reversed',
                'primary',
            ].includes(value);
        },
    },
    size: {
        type: String,
        required: false,
        default: 'default',
        validValues: [
            'small',
            'medium',
            'default',
        ],
        validator(value) {
            return [
                'small',
                'medium',
                'default',
            ].includes(value);
        },
    },
    appearance: {
        type: String,
        required: false,
        default: 'default',
        validValues: [
            'default',
            'pill',
            'circle',
            'badged',
        ],
        validator(value) {
            return [
                'default',
                'pill',
                'circle',
                'badged',
            ].includes(value);
        },
    },
    ghost: {
        type: Boolean,
        required: false,
        default: false,
    },
    caps: {
        type: Boolean,
        required: false,
        default: false,
    },
    dismissable: {
        type: Boolean,
        required: false,
        default: true,
    },
    light: {
        type: Boolean,
        required: false,
        default: false,
    },
    onDismiss: {
        type: Function,
        required: false,
        default: null,
    },
});
defineEmits([
    'selected',
    'dismiss',
]);

import { computed } from 'vue';

const labelClasses = computed(() => {
    return [
        `ct-label--appearance-${props.appearance}`,
        `ct-label--size-${props.size}`,
        {
            [`ct-label--${props.variant}`]: props.variant,
            'ct-label--dismissable': showDismissable.value,
            'ct-label--ghost': props.ghost,
            'ct-label--caps': props.caps,
            'ct-label--light': props.light,
        },
    ];
});
const showDismissable = computed(() => {
    return !!props.onDismiss && props.dismissable;
});

ctDefinePublic({
    labelClasses,
    showDismissable,
});

defineExpose({
    labelClasses,
    showDismissable,
});
</script>

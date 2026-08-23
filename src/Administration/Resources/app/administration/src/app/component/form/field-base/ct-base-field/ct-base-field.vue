<template>
    <ct-block name="sw_base_field">
        <div class="ct-field" :class="swFieldClasses" v-bind="$attrs" :label="label">
            <div v-if="hasLabel" class="ct-field__label">
                <ct-inheritance-switch
                    v-if="isInheritanceField"
                    :disabled="disableInheritanceToggle"
                    class="ct-field__inheritance-icon"
                    :is-inherited="isInherited"
                    @inheritance-remove="$emit('inheritance-remove')"
                    @inheritance-restore="$emit('inheritance-restore')"
                />

                <!-- eslint-disable-next-line vuejs-accessibility/label-has-for -->
                <label v-if="showLabel" :for="identification" :class="swFieldLabelClasses">
                    <slot name="label">
                        {{ label }}
                    </slot>

                    <ct-ai-copilot-badge v-if="aiBadge" />
                </label>

                <ct-help-text v-if="helpText" class="ct-field__help-text" :text="helpText" />
            </div>
            <slot name="ct-field-input" v-bind="{ identification, error, disabled }"></slot>

            <ct-field-error :error="error" />

            <div v-if="hasHint" class="ct-field__hint">
                <slot name="hint">
                    {{ $t(hint) }}
                </slot>
            </div>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-base-field.scss';
const utils = Contena.Utils;

defineOptions({ inheritAttrs: false });

const props = defineProps({
    name: {
        type: String,
        required: false,
        default: null,
    },

    label: {
        type: String,
        required: false,
        default: null,
    },

    helpText: {
        type: String,
        required: false,
        default: null,
    },

    hint: {
        type: String,
        required: false,
        default: null,
    },

    isInvalid: {
        type: Boolean,
        required: false,
        default: false,
    },

    aiBadge: {
        type: Boolean,
        required: false,
        default: false,
    },

    error: {
        type: [Object],
        required: false,
        default() {
            return null;
        },
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    required: {
        type: Boolean,
        required: false,
        default: false,
    },

    isInherited: {
        type: Boolean,
        required: false,
        default: false,
    },

    isInheritanceField: {
        type: Boolean,
        required: false,
        default: false,
    },

    disableInheritanceToggle: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'base-field-mounted',
    'inheritance-restore',
    'inheritance-remove',
]);

import { ref, computed, inject, useSlots, onMounted } from 'vue';

const slots = useSlots();

const feature = inject('feature');

const id = ref(utils.createId());

const identification = computed(() => {
    if (props.name) {
        return props.name;
    }

    return `ct-field--${id.value}`;
});
const hasLabel = computed(() => {
    return !!props.helpText || props.isInheritanceField || showLabel.value;
});
const hasError = computed(() => {
    return props.isInvalid || !!props.error;
});
const hasHint = computed(() => {
    return !!props.hint || slots.hint?.()[0]?.children.length > 0;
});
const swFieldClasses = computed(() => {
    return {
        'has--error': hasError.value,
        'has--hint': hasHint.value,
        'is--disabled': props.disabled,
        'is--inherited': props.isInherited,
    };
});
const swFieldLabelClasses = computed(() => {
    return {
        'is--required': props.required,
    };
});
const showLabel = computed(() => {
    return !!props.label || slots.label?.()[0]?.children.length > 0;
});

onMounted(() => {
    emit('base-field-mounted');
});

swDefinePublic({
    feature,
    id,
    identification,
    hasLabel,
    hasError,
    hasHint,
    swFieldClasses,
    swFieldLabelClasses,
    showLabel,
});

defineExpose({
    feature,
    id,
    identification,
    hasLabel,
    hasError,
    hasHint,
    swFieldClasses,
    swFieldLabelClasses,
    showLabel,
});
</script>

<template>
    <ct-block name="sw_block_field">
        <ct-base-field class="ct-block-field" :class="swBlockFieldClasses" v-bind="$attrs">
            <template #ct-field-input="{ identification, error, disabled }">
                <div class="ct-block-field__block">
                    <slot
                        name="ct-field-input"
                        v-bind="{ identification, error, disabled, size, setFocusClass, removeFocusClass }"
                    ></slot>
                </div>
            </template>

            <template #hint>
                <slot name="hint"></slot>
            </template>

            <template #label>
                <slot name="label"></slot>
            </template>
        </ct-base-field>
    </ct-block>
</template>

<script setup>
import './ct-block-field.scss';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    size: {
        type: String,
        required: false,
        default: 'default',
        validValues: [
            'small',
            'medium',
            'default',
        ],
        validator(val) {
            return [
                'small',
                'medium',
                'default',
            ].includes(val);
        },
    },
});

import { ref, computed } from 'vue';

const hasFocus = ref(false);

const swBlockSize = computed(() => {
    return `ct-field--${props.size}`;
});
const swBlockFieldClasses = computed(() => {
    return [
        {
            'has--focus': hasFocus.value,
        },
        swBlockSize.value,
    ];
});

const setFocusClass = () => {
    hasFocus.value = true;
};
const removeFocusClass = () => {
    hasFocus.value = false;
};

swDefinePublic({
    hasFocus,
    swBlockSize,
    swBlockFieldClasses,
    setFocusClass,
    removeFocusClass,
});

defineExpose({
    hasFocus,
    swBlockSize,
    swBlockFieldClasses,
    setFocusClass,
    removeFocusClass,
});
</script>

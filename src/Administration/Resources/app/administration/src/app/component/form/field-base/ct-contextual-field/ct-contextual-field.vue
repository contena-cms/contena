<template>
    <ct-block name="ct_contextual_field">
        <ct-block-field class="ct-contextual-field" v-bind="$attrs">
            <template #ct-field-input="{ identification, error, disabled, size, setFocusClass, removeFocusClass }">
                <div v-if="hasPrefix" class="ct-field__addition is--prefix">
                    <slot name="ct-contextual-field-prefix" v-bind="{ disabled, identification }"></slot>
                </div>

                <slot
                    name="ct-field-input"
                    v-bind="{ identification, error, disabled, size, setFocusClass, removeFocusClass, hasSuffix, hasPrefix }"
                ></slot>

                <div v-if="hasSuffix" class="ct-field__addition">
                    <slot name="ct-contextual-field-suffix" v-bind="{ disabled, identification }"></slot>
                </div>
            </template>

            <template #hint>
                <slot name="hint"></slot>
            </template>

            <template #label>
                <slot name="label"></slot>
            </template>
        </ct-block-field>
    </ct-block>
</template>

<script setup>
import './ct-contextual-field.scss';

defineOptions({ inheritAttrs: false });

defineProps({});

import { computed, useSlots } from 'vue';

const slots = useSlots();

const hasPrefix = computed(() => {
    return slots.hasOwnProperty('ct-contextual-field-prefix') && slots['ct-contextual-field-prefix']({}) !== undefined;
});
const hasSuffix = computed(() => {
    return slots.hasOwnProperty('ct-contextual-field-suffix') && slots['ct-contextual-field-suffix']({}) !== undefined;
});

ctDefinePublic({
    hasPrefix,
    hasSuffix,
});

defineExpose({
    hasPrefix,
    hasSuffix,
});
</script>

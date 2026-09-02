<template>
    <ct-block name="ct_data_grid_inline_edit">
        <div class="ct-data-grid-inline-edit" :class="classes">
            <ct-block name="ct_data_grid_inline_edit_type_string">
                <mt-text-field
                    v-if="column.inlineEdit === 'string'"
                    key="string"
                    v-model="currentValue"
                    name="ct-field--currentValue"
                    :size="inputFieldSize"
                    @update:model-value="emitInput"
                />
            </ct-block>

            <ct-block name="ct_data_grid_inline_edit_type_number">
                <template v-if="column.inlineEdit === 'string'"
                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                >
                <mt-number-field
                    v-else-if="column.inlineEdit === 'number'"
                    key="number"
                    v-model="currentValue"
                    name="ct-field--currentValue"
                    :size="inputFieldSize"
                    :number-align-end="true"
                    @update:model-value="emitInput"
                />
            </ct-block>

            <ct-block name="ct_data_grid_inline_edit_type_boolean">
                <template v-if="column.inlineEdit === 'string' || column.inlineEdit === 'number'"
                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                >
                <mt-checkbox
                    v-else-if="column.inlineEdit === 'boolean'"
                    key="boolean"
                    v-model:checked="currentValue"
                    name="ct-field--currentValue"
                    @update:checked="emitInput"
                />
            </ct-block>

            <ct-block name="ct_data_grid_inline_edit_type_unknown">
                <template
                    v-if="
                        column.inlineEdit === 'string' || column.inlineEdit === 'number' || column.inlineEdit === 'boolean'
                    "
                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                >
                <span v-else key="unknown" class="ct-data-grid-inline-edit__placeholder">
                    Unknown type {{ column.inlineEdit }}
                </span>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-data-grid-inline-edit.scss';

const props = defineProps({
    column: {
        type: Object,
        required: true,
        default() {
            return {};
        },
    },
    value: {
        required: true,
    },
    compact: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits(['update:value']);

import { ref, computed, inject } from 'vue';

const feature = inject('feature');

const currentValue = ref(null);

const classes = computed(() => {
    return {
        'is--compact': props.compact,
    };
});
const inputFieldSize = computed(() => {
    return props.compact ? 'small' : 'default';
});

const createdComponent = () => {
    currentValue.value = props.value;
};
const emitInput = () => {
    emit('update:value', currentValue.value);
};

createdComponent();

ctDefinePublic({
    feature,
    currentValue,
    classes,
    inputFieldSize,
    createdComponent,
    emitInput,
});

defineExpose({
    feature,
    currentValue,
    classes,
    inputFieldSize,
    createdComponent,
    emitInput,
});
</script>

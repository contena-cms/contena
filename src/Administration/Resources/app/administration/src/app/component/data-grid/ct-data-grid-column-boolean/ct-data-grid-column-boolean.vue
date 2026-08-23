<template>
    <ct-block name="sw_data_grid_column_boolean">
        <div class="ct-data-grid-column-boolean">
            <mt-checkbox v-if="isInlineEdit" v-model:checked="currentValue" :disabled="disabled" />

            <template v-else>
                <mt-icon v-if="currentValue" name="regular-checkmark-xs" size="16px" class="is--active" />
                <mt-icon v-else name="regular-times-s" class="is--inactive" />
            </template>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-data-grid-column-boolean.scss';

const props = defineProps({
    isInlineEdit: {
        type: Boolean,
        required: false,
        default: false,
    },
    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },
    value: {
        required: true,
    },
});
const emit = defineEmits(['update:value']);

import { computed } from 'vue';

const currentValue = computed({
    get: () => {
        return props.value;
    },
    set: (newValue) => {
        emit('update:value', newValue);
    },
});

swDefinePublic({
    currentValue,
});

defineExpose({
    currentValue,
});
</script>

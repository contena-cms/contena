<template>
    <ct-block name="ct_grid_column">
        <div class="ct-grid-column ct-grid__cell" :class="'ct-grid-column--' + align">
            <ct-block name="ct_grid_column_content">
                <div class="ct-grid__cell-content" :class="{ 'is--truncate': truncate }">
                    <slot>
                        <ct-block name="ct_grid_column_slot_default"></ct-block>
                    </slot>
                </div>
            </ct-block>

            <ct-block name="ct_grid_column_editing">
                <div class="ct-grid__cell-inline-editing">
                    <slot name="inline-edit">
                        <ct-block name="ct_grid_column_slot_inline_edit"></ct-block>
                    </slot>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-grid-column.scss';

const props = defineProps({
    label: {
        type: String,
        required: false,
        default: null,
    },
    iconLabel: {
        type: String,
        required: false,
        default: null,
    },
    align: {
        type: String,
        default: 'left',
    },
    flex: {
        required: false,
        default: 1,
    },
    sortable: {
        type: Boolean,
        required: false,
        default: false,
    },
    dataIndex: {
        type: String,
        required: false,
        default: '',
    },
    editable: {
        type: Boolean,
        required: false,
        default: false,
    },
    truncate: {
        type: Boolean,
        required: false,
        default: false,
    },
});

import { computed, inject, watch } from 'vue';

const feature = inject('feature', null);
const ctGridColumns = inject('ctGridColumns', null);

const parentGrid = computed(() => {
    return undefined;
});

const createdComponent = () => {
    registerColumn();
};
function registerColumn() {
    const parentGridColumns = ctGridColumns;
    const hasColumn = parentGridColumns.some((column) => {
        return column.label === props.label;
    });
    if (!hasColumn) {
        parentGridColumns.push({
            label: props.label,
            iconLabel: props.iconLabel,
            flex: props.flex,
            sortable: props.sortable,
            dataIndex: props.dataIndex,
            align: props.align,
            editable: props.editable,
            truncate: props.truncate,
        });
    }
}

watch(
    () => props.label,
    (newLabel, oldLabel) => {
        const parentGridColumns = ctGridColumns;

        const index = parentGridColumns.findIndex((col) => col.label === oldLabel);

        if (index === -1 || !newLabel) {
            return;
        }

        if (parentGrid.value) {
            parentGridColumns[index].label = newLabel;
        }
    },
);

createdComponent();

ctDefinePublic({
    feature,
    ctGridColumns,
    parentGrid,
    createdComponent,
    registerColumn,
});

defineExpose({
    feature,
    ctGridColumns,
    parentGrid,
    createdComponent,
    registerColumn,
});
</script>

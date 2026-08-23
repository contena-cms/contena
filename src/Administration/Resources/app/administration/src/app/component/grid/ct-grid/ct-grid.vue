<template>
    <ct-block name="sw_grid">
        <div ref="gridRoot" class="ct-grid" :class="gridClasses">
            <slot name="content">
                <ct-block name="sw_grid_slot_content">
                    <div class="ct-grid__content" :class="gridContentClasses">
                        <slot v-if="header" name="header">
                            <ct-block name="sw_grid_slot_header">
                                <div class="ct-grid__header" :style="[columnFlex, { paddingRight: `${scrollbarOffset}px` }]">
                                    <ct-block name="sw_grid_slot_header_cell_selectable">
                                        <div v-if="selectable" class="ct-grid-column">
                                            <div class="ct-grid__cell-content">
                                                <mt-checkbox
                                                    v-model:checked="allSelectedChecked"
                                                    @update:checked="selectAll"
                                                />
                                            </div>
                                        </div>
                                    </ct-block>

                                    <ct-block name="sw_grid_slot_header_cell">
                                        <div
                                            v-for="(column, columnIndex) in columns"
                                            :key="columnIndex"
                                            :class="[
                                                {
                                                    'ct-grid-column': true,
                                                    'is--sortable': column.sortable,
                                                    'is--sorted': sort === column.dataIndex,
                                                },
                                                `ct-grid-column--${column.align}`,
                                            ]"
                                            role="gridcell"
                                            tabindex="0"
                                            @click="onGridCellClick($event, column)"
                                            @keydown.enter="onGridCellClick($event, column)"
                                        >
                                            <ct-block name="sw_grid_slot_header_cell_content">
                                                <div class="ct-grid__cell-content">
                                                    <ct-block name="sw_grid_slot_header_cell_icon_label">
                                                        <span v-if="column.iconLabel" class="ct-grid__cell-label">
                                                            <mt-icon :name="column.iconLabel" :title="column.label" />
                                                        </span>
                                                    </ct-block>

                                                    <ct-block name="sw_grid_slot_header_cell_label">
                                                        <template v-if="column.iconLabel"
                                                            ><!-- Keeps the conditional chain connected across ct-block. --></template
                                                        >
                                                        <span v-else-if="column.label" class="ct-grid__cell-label">{{
                                                            column.label
                                                        }}</span>
                                                    </ct-block>

                                                    <ct-block name="sw_grid_slot_header_cell_sortable">
                                                        <span
                                                            v-if="column.sortable && sort === column.dataIndex"
                                                            class="ct-grid_cell-sortable"
                                                        >
                                                            <mt-icon
                                                                v-if="sortDir === 'ASC'"
                                                                name="regular-chevron-down-xxs"
                                                                size="16px"
                                                            />
                                                            <mt-icon v-else name="regular-chevron-up-xxs" size="16px" />
                                                        </span>
                                                    </ct-block>
                                                </div>
                                            </ct-block>
                                        </div>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </slot>

                        <slot name="body">
                            <ct-block name="sw_grid_slot_body">
                                <div ref="swGridBody" class="ct-grid__body">
                                    <slot v-for="(item, index) in items" :key="getKey(item)" name="items">
                                        <ct-block name="sw_grid_body_slot_items">
                                            <ct-grid-row
                                                ref="rowRefs"
                                                :style="columnFlex"
                                                :item="item"
                                                :index="index"
                                                :allow-inline-edit="allowInlineEdit"
                                                :class="[
                                                    'ct-grid__row--' + index,
                                                    {
                                                        'is--selected': isSelected(item.id),
                                                        'is--deleted': item.isDeleted,
                                                        'is--new': item.isLocal,
                                                    },
                                                ]"
                                                @inline-edit-finish="onInlineEditFinish"
                                                @inline-edit-start="onInlineEditStart"
                                            >
                                                <ct-block name="sw_grid_body_item_selectable">
                                                    <div v-if="selectable" class="ct-grid-column">
                                                        <div class="ct-grid__cell-content">
                                                            <mt-checkbox
                                                                :checked="isSelected(item.id)"
                                                                @update:checked="selectItem($event, item)"
                                                            />
                                                        </div>
                                                    </div>
                                                </ct-block>

                                                <slot name="columns" :item="item">
                                                    <ct-block name="sw_grid_slot_columns"></ct-block>
                                                </slot>
                                            </ct-grid-row>
                                        </ct-block>
                                    </slot>

                                    <ct-block name="sw_grid_slot_empty_columns">
                                        <slot v-if="!items.length" name="empty">
                                            <ct-block name="sw_grid_slot_empty_columns_content"></ct-block>
                                        </slot>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </slot>

                        <ct-block name="sw_grid_pagination">
                            <div v-if="hasPaginationSlot && items.length" class="ct-grid__pagination">
                                <slot name="pagination">
                                    <ct-block name="sw_grid_slot_pagination"></ct-block>
                                </slot>
                            </div>
                        </ct-block>
                    </div>
                </ct-block>
            </slot>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-grid.scss';
const { dom } = Contena.Utils;

const props = defineProps({
    items: {
        type: Array,
        required: false,
        default: null,
    },

    selectable: {
        type: Boolean,
        required: false,
        default: true,
    },

    variant: {
        type: String,
        required: false,
        default: 'normal',
    },

    header: {
        type: Boolean,
        required: false,
        default: true,
    },

    sortBy: {
        type: String,
        required: false,
        default: null,
    },

    sortDirection: {
        type: String,
        required: false,
        default: 'ASC',
    },

    isFullpage: {
        type: Boolean,
        required: false,
        default: false,
    },

    table: {
        type: Boolean,
        required: false,
        default: false,
    },

    allowInlineEdit: {
        type: Boolean,
        required: false,
        default: true,
    },
});
const emit = defineEmits([
    'inline-edit-finish',
    'inline-edit-start',
    'ct-grid-disable-inline-editing',
    'inline-edit-cancel',
    'ct-grid-select-all',
    'ct-grid-select-item',
    'sort-column',
]);

import { ref, computed, provide, useSlots, getCurrentInstance, onMounted, onUpdated } from 'vue';

const slots = useSlots();

const swGridBody = ref(null);
const gridRoot = ref(null);

const columns = ref([]);
const selection = ref({});
const scrollbarOffset = ref(0);
const editing = ref(null);
const allSelectedChecked = ref(false);
const swGridDisableInlineEditListener = ref([]);
const rowRefs = ref([]);

const sort = computed(() => {
    return props.sortBy;
});
const sortDir = computed(() => {
    return props.sortDirection;
});
const sizeClass = computed(() => {
    return `ct-grid--${props.variant}`;
});
const hasPaginationSlot = computed(() => {
    return !!slots.pagination;
});
const gridClasses = computed(() => {
    return {
        'ct-grid--fullpage': props.isFullpage,
        'ct-grid--table': props.table,
        [sizeClass.value]: true,
    };
});
const gridContentClasses = computed(() => {
    return {
        'ct-grid__content--header': props.header,
        'ct-grid__content--pagination': hasPaginationSlot.value,
    };
});
const columnFlex = computed(() => {
    let flex = props.selectable === true ? '50px ' : '';

    columns.value.forEach((column) => {
        if (`${parseInt(column.flex, 10)}` === column.flex) {
            flex += `${column.flex}fr `;
        } else {
            flex += `${column.flex} `;
        }
    });

    return {
        'grid-template-columns': flex.trim(),
    };
});

const createdComponent = () => {
    registerInlineEditingEvents();

    getCurrentInstance()?.proxy?.$device.onResize({
        listener: setScrollbarOffset,
        component: getCurrentInstance()?.proxy,
    });
};

const updatedComponent = () => {
    setScrollbarOffset();
};
const registerGridDisableInlineEditListener = (listener) => {
    swGridDisableInlineEditListener.value.push(listener);
};
const unregisterGridDisableInlineEditListener = (listener) => {
    swGridDisableInlineEditListener.value = swGridDisableInlineEditListener.value.filter((l) => l !== listener);
};
const onInlineEditFinish = (item) => {
    editing.value = null;
    emit('inline-edit-finish', item);
};
const onInlineEditStart = (item) => {
    emit('inline-edit-start', item);
};
function registerInlineEditingEvents() {}
const inlineEditingStart = (id) => {
    if (editing.value != null) {
        emit('ct-grid-disable-inline-editing', editing.value);
    }

    editing.value = id;
};
const disableActiveInlineEditing = (item, index) => {
    editing.value = null;
    emit('inline-edit-cancel', item, index);
};
const selectAll = (selected) => {
    selection.value = {};

    props.items.forEach((item) => {
        if (isSelected(item.id) !== selected) {
            selectItem(selected, item);
        }
    });

    allSelectedChecked.value = selected;
    emit('ct-grid-select-all', selection.value);
};
const getSelection = () => {
    return selection.value;
};
function selectItem(selected, item) {
    const selectionValue = selection.value;
    if (selected === true) {
        selectionValue[item.id] = item;
    } else if (!selected && selectionValue[item.id]) {
        delete selection.value[item.id];
    }
    selection.value = {};
    selection.value = selectionValue;
    checkSelection();
    emit('ct-grid-select-item', selection.value, item, selected);
}
function isSelected(itemId) {
    return typeof selection.value[itemId] !== 'undefined';
}
function checkSelection() {
    allSelectedChecked.value = !props.items.some((item) => {
        return selection.value[item.id] === undefined;
    });
}
const getScrollBarWidth = () => {
    if (!gridRoot.value) {
        return 0;
    }

    const gridBody = gridRoot.value.getElementsByClassName('ct-grid--body')[0];

    if (gridBody.offsetWidth && gridBody.clientWidth) {
        return gridBody.offsetWidth - gridBody.clientWidth;
    }

    return 0;
};
const onGridCellClick = (event, column) => {
    if (!column.sortable) {
        return;
    }

    emit('ct-grid-disable-inline-editing');
    emit('sort-column', column);
};
function setScrollbarOffset() {
    scrollbarOffset.value = dom.getScrollbarWidth(swGridBody.value);
}
const setColumns = (columnsValue) => {
    columns.value = columnsValue;
};
const getKey = (item) => {
    if (item.id === undefined || item.id === null) {
        // see https://vuejs.org/api/built-in-special-attributes.html#key
        // we use child components with state
        // (at least ct-grid-row, maybe even form elements, depending on the slot usage)
        // means not having a proper unique identifier for each row likely causes issues.
        // For example the child components may not be properly destroyed and created and just
        // "patched" in place with a completely different item / row
        Contena.Utils.debug.error(
            'ct-grid item without `id` property',
            item,
            'more info here: https://vuejs.org/api/built-in-special-attributes.html#key',
        );
        return undefined;
    }

    return item.id;
};
const startInlineEditing = () => {
    rowRefs.value.at(-1).startInlineEditing();
};

provide('swGridInlineEditStart', inlineEditingStart);
provide('swGridInlineEditCancel', disableActiveInlineEditing);
provide('swOnInlineEditStart', onInlineEditStart);
provide('swRegisterGridDisableInlineEditListener', registerGridDisableInlineEditListener);
provide('swUnregisterGridDisableInlineEditListener', unregisterGridDisableInlineEditListener);
provide('swGridSetColumns', setColumns);
provide(
    'swGridColumns',
    computed(() => columns.value),
);

onMounted(() => {
    createdComponent();
});

onUpdated(() => {
    updatedComponent();
});

swDefinePublic({
    columns,
    selection,
    scrollbarOffset,
    editing,
    allSelectedChecked,
    swGridDisableInlineEditListener,
    rowRefs,
    sort,
    sortDir,
    sizeClass,
    hasPaginationSlot,
    gridClasses,
    gridContentClasses,
    columnFlex,
    createdComponent,
    updatedComponent,
    registerGridDisableInlineEditListener,
    unregisterGridDisableInlineEditListener,
    onInlineEditFinish,
    onInlineEditStart,
    registerInlineEditingEvents,
    inlineEditingStart,
    disableActiveInlineEditing,
    selectAll,
    getSelection,
    selectItem,
    isSelected,
    checkSelection,
    getScrollBarWidth,
    onGridCellClick,
    setScrollbarOffset,
    setColumns,
    getKey,
    startInlineEditing,
});

defineExpose({
    columns,
    selection,
    scrollbarOffset,
    editing,
    allSelectedChecked,
    swGridDisableInlineEditListener,
    rowRefs,
    sort,
    sortDir,
    sizeClass,
    hasPaginationSlot,
    gridClasses,
    gridContentClasses,
    columnFlex,
    createdComponent,
    updatedComponent,
    registerGridDisableInlineEditListener,
    unregisterGridDisableInlineEditListener,
    onInlineEditFinish,
    onInlineEditStart,
    registerInlineEditingEvents,
    inlineEditingStart,
    disableActiveInlineEditing,
    selectAll,
    getSelection,
    selectItem,
    isSelected,
    checkSelection,
    getScrollBarWidth,
    onGridCellClick,
    setScrollbarOffset,
    setColumns,
    getKey,
    startInlineEditing,
});
</script>

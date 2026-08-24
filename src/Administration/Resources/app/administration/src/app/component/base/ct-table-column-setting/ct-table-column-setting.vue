<template>
    <a-tooltip :title="title">
        <a-popover
            v-model:open="isOpen"
            placement="bottomRight"
            trigger="click"
            overlay-class-name="ct-table-column-setting-popover"
            @open-change="onOpenChange"
        >
            <a-button class="ct-table-column-setting__trigger" :aria-label="title">
                <template #icon><ct-icon name="SettingOutlined" /></template>
            </a-button>

            <template #title>
                <div class="ct-table-column-setting__header">
                    <span>{{ title }}</span>
                    <a-button type="link" size="small" @click="resetDraft">{{ resetLabel }}</a-button>
                </div>
            </template>

            <template #content>
                <div class="ct-table-column-setting__options">
                    <a-checkbox
                        :checked="allOptionalColumnsVisible"
                        :indeterminate="someOptionalColumnsVisible"
                        @change="toggleAll($event.target.checked)"
                    >
                        {{ allLabel }}
                    </a-checkbox>
                </div>

                <a-divider />

                <div ref="columnList" class="ct-table-column-setting__list">
                    <div
                        v-for="column in draftColumns"
                        :key="column.key"
                        class="ct-table-column-setting__item"
                        :class="{ 'is--required': column.required }"
                    >
                        <ct-icon class="ct-table-column-setting__drag" name="HolderOutlined" :size="16" />
                        <a-checkbox
                            class="ct-table-column-setting__checkbox"
                            :checked="column.checked"
                            :disabled="column.required"
                            @change="toggleColumn(column.key, $event.target.checked)"
                        >
                            {{ column.title }}
                        </a-checkbox>

                        <div class="ct-table-column-setting__fixed-actions">
                            <a-tooltip :title="fixedLeftLabel">
                                <a-button
                                    type="text"
                                    size="small"
                                    :class="{ 'is--active': column.fixed === 'left' }"
                                    :aria-label="fixedLeftLabel"
                                    @click="toggleFixed(column.key, 'left')"
                                >
                                    <template #icon><ct-icon name="VerticalRightOutlined" :size="14" /></template>
                                </a-button>
                            </a-tooltip>
                            <a-tooltip :title="fixedRightLabel">
                                <a-button
                                    type="text"
                                    size="small"
                                    :class="{ 'is--active': column.fixed === 'right' }"
                                    :aria-label="fixedRightLabel"
                                    @click="toggleFixed(column.key, 'right')"
                                >
                                    <template #icon><ct-icon name="VerticalLeftOutlined" :size="14" /></template>
                                </a-button>
                            </a-tooltip>
                        </div>
                    </div>
                </div>

                <a-divider />

                <div class="ct-table-column-setting__footer">
                    <a-button type="text" size="small" @click="cancel">{{ cancelLabel }}</a-button>
                    <a-button type="primary" size="small" @click="apply">{{ applyLabel }}</a-button>
                </div>
            </template>
        </a-popover>
    </a-tooltip>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import Sortable from 'sortablejs';
import type { TableColumnSetting } from './ct-table-column-setting.types';

interface Props {
    columns: TableColumnSetting[];
    defaultColumns?: TableColumnSetting[];
    title: string;
    allLabel: string;
    resetLabel: string;
    cancelLabel: string;
    applyLabel: string;
    fixedLeftLabel: string;
    fixedRightLabel: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    apply: [columns: TableColumnSetting[]];
}>();

const isOpen = ref(false);
const draftColumns = ref<TableColumnSetting[]>([]);
const columnList = ref<HTMLElement>();
let sortable: Sortable | undefined;

const optionalColumns = computed(() => draftColumns.value.filter((column) => !column.required));
const allOptionalColumnsVisible = computed(
    () => optionalColumns.value.length > 0 && optionalColumns.value.every((column) => column.checked),
);
const someOptionalColumnsVisible = computed(
    () => optionalColumns.value.some((column) => column.checked) && !allOptionalColumnsVisible.value,
);

const cloneColumns = (columns: TableColumnSetting[]): TableColumnSetting[] => columns.map((column) => ({ ...column }));

const onOpenChange = async (open: boolean): Promise<void> => {
    isOpen.value = open;
    if (!open) {
        sortable?.destroy();
        sortable = undefined;
        return;
    }

    draftColumns.value = cloneColumns(props.columns);
    await nextTick();
    if (!columnList.value) {
        return;
    }

    sortable = Sortable.create(columnList.value, {
        animation: 150,
        handle: '.ct-table-column-setting__drag',
        onEnd: ({ oldIndex, newIndex }) => {
            if (oldIndex === undefined || newIndex === undefined || oldIndex === newIndex) {
                return;
            }

            const [moved] = draftColumns.value.splice(oldIndex, 1);
            if (moved) {
                draftColumns.value.splice(newIndex, 0, moved);
            }
        },
    });
};

const toggleColumn = (key: string, checked: boolean): void => {
    const column = draftColumns.value.find((item) => item.key === key);
    if (column && !column.required) {
        column.checked = checked;
    }
};
const toggleAll = (checked: boolean): void => {
    optionalColumns.value.forEach((column) => {
        column.checked = checked;
    });
};
const toggleFixed = (key: string, direction: 'left' | 'right'): void => {
    const column = draftColumns.value.find((item) => item.key === key);
    if (!column) {
        return;
    }

    column.fixed = column.fixed === direction ? false : direction;
};
const resetDraft = (): void => {
    draftColumns.value = cloneColumns(props.defaultColumns ?? props.columns);
};
const cancel = (): void => {
    isOpen.value = false;
};
const apply = (): void => {
    emit('apply', cloneColumns(draftColumns.value));
    isOpen.value = false;
};

onBeforeUnmount(() => sortable?.destroy());

swDefinePublic({
    isOpen,
    draftColumns,
    allOptionalColumnsVisible,
    someOptionalColumnsVisible,
    onOpenChange,
    toggleColumn,
    toggleAll,
    toggleFixed,
    resetDraft,
    cancel,
    apply,
});

defineExpose({
    isOpen,
    draftColumns,
    allOptionalColumnsVisible,
    someOptionalColumnsVisible,
    onOpenChange,
    toggleColumn,
    toggleAll,
    toggleFixed,
    resetDraft,
    cancel,
    apply,
});
</script>

<style lang="scss">
.ct-table-column-setting-popover {
    .ant-popover-inner {
        width: 280px;
        padding: 0;
    }

    .ant-popover-title {
        min-width: 0;
        min-height: 44px;
        margin: 0;
        padding: 6px 8px 6px var(--ct-spacing-md);
        border-bottom: 1px solid var(--ct-color-border-secondary);
    }

    .ant-popover-inner-content {
        padding: 12px var(--ct-spacing-md) var(--ct-spacing-sm);
    }

    .ant-divider {
        margin: 10px 0;
    }
}

.ct-table-column-setting {
    &__header,
    &__footer,
    &__item,
    &__fixed-actions {
        display: flex;
        align-items: center;
    }

    &__header,
    &__footer {
        justify-content: space-between;
    }

    &__options {
        min-height: 24px;
    }

    &__list {
        max-height: 280px;
        overflow-y: auto;
    }

    &__item {
        min-height: 36px;
        padding: 2px 0;
        border-radius: var(--ct-border-radius);

        &:hover {
            background: var(--ct-color-fill-tertiary);
        }
    }

    &__drag {
        flex: 0 0 24px;
        color: var(--ct-color-text-tertiary);
        cursor: grab;

        &:active {
            cursor: grabbing;
        }
    }

    &__checkbox {
        min-width: 0;
        flex: 1;
        overflow: hidden;

        .ant-checkbox + span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    }

    &__fixed-actions {
        flex: 0 0 auto;

        .ant-btn {
            width: 24px;
            height: 24px;
            padding: 0;
            color: var(--ct-color-text-tertiary);

            &.is--active,
            &:hover {
                color: var(--ct-color-primary);
                background: var(--ct-color-primary-bg);
            }
        }
    }
}
</style>

<template>
    <ct-block name="sw_data_dictionary_tree">
        <div class="ct-data-dictionary-tree">
            <ct-tree
                v-if="!isLoading"
                ref="dictionaryTree"
                class="ct-data-dictionary-tree__inner"
                after-id-property="afterId"
                :items="treeItems"
                :sortable="sortable"
                :searchable="false"
                :initially-expanded-root="true"
                @drag-end="onDragEnd"
            >
                <template #headline>
                    <span />
                </template>

                <template
                    #items="{
                        treeItems: visibleTreeItems,
                        selectedItemsPathIds,
                        checkedItemIds,
                        sortable: treeSortable,
                        draggedItem,
                    }"
                >
                    <ct-tree-item
                        v-for="treeItem in visibleTreeItems"
                        :key="treeItem.id"
                        :item="treeItem"
                        :sortable="treeSortable"
                        :dragged-item="draggedItem"
                        :active="treeItem.id === activeItemId"
                        :should-show-active-state="true"
                        :display-checkbox="false"
                        :allow-new-categories="canCreate || undefined"
                        :allow-delete-categories="canDelete || undefined"
                        :active-parent-ids="selectedItemsPathIds"
                        :active-item-ids="[...checkedItemIds, ...(activeItemId ? [activeItemId] : [])]"
                    >
                        <template #content="{ item: currentTreeItem }">
                            <ct-block name="sw_data_dictionary_tree_item_content">
                                <button
                                    class="ct-data-dictionary-tree__item"
                                    type="button"
                                    :class="{
                                        'ct-data-dictionary-tree__item--root': currentTreeItem.data.isDictionaryRoot,
                                    }"
                                    @click="onSelectTreeItem(currentTreeItem)"
                                >
                                    <span class="ct-data-dictionary-tree__item-label">
                                        {{
                                            currentTreeItem.data.entity.label || $t('ct-data-dictionary.detail.untitledItem')
                                        }}
                                    </span>
                                </button>
                            </ct-block>
                        </template>

                        <template #actions="{ item: currentTreeItem }">
                            <ct-block name="sw_data_dictionary_tree_item_actions">
                                <ct-context-button>
                                    <ct-block name="sw_data_dictionary_tree_item_add_child">
                                        <ct-context-menu-item
                                            :disabled="!canCreate || undefined"
                                            @click="onAddChildTreeItem(currentTreeItem)"
                                        >
                                            <ct-block name="sw_data_dictionary_tree_item_add_child_label">
                                                {{ $t('ct-data-dictionary.detail.addChildItem') }}
                                            </ct-block>
                                        </ct-context-menu-item>
                                    </ct-block>
                                    <ct-block name="sw_data_dictionary_tree_item_edit">
                                        <template v-if="!currentTreeItem.data.isDictionaryRoot">
                                            <ct-context-menu-item
                                                :disabled="!canEdit || undefined"
                                                @click="onSelect(currentTreeItem.data.entity)"
                                            >
                                                <ct-block name="sw_data_dictionary_tree_item_edit_label">
                                                    {{ $t('global.default.edit') }}
                                                </ct-block>
                                            </ct-context-menu-item>
                                        </template>
                                    </ct-block>

                                    <ct-block name="sw_data_dictionary_tree_item_delete">
                                        <template v-if="!currentTreeItem.data.isDictionaryRoot">
                                            <ct-context-menu-item
                                                variant="danger"
                                                :disabled="!canDelete || undefined"
                                                @click="onDelete(currentTreeItem.data.entity)"
                                            >
                                                <ct-block name="sw_data_dictionary_tree_item_delete_label">
                                                    {{ $t('global.default.delete') }}
                                                </ct-block>
                                            </ct-context-menu-item>
                                        </template>
                                    </ct-block>
                                </ct-context-button>
                            </ct-block>
                        </template>
                    </ct-tree-item>
                </template>
            </ct-tree>

            <div v-else>
                <ct-skeleton variant="tree-item" />
                <ct-skeleton variant="tree-item-nested" />
                <ct-skeleton variant="tree-item-nested" />
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import { computed } from 'vue';

type DictionaryItem = Entity<'data_dictionary_item'>;
type TreeItem = {
    id: string;
    parentId?: string | null;
    entity: DictionaryItem;
    name?: string;
    isDictionaryRoot?: boolean;
};
type RenderedTreeItem = {
    id: string;
    data: TreeItem;
};

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    rootId: {
        type: String,
        required: true,
    },
    rootLabel: {
        type: String,
        required: true,
    },
    activeItemId: {
        type: String,
        default: null,
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    canCreate: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits([
    'select-item',
    'add-child',
    'delete-item',
    'drag-end',
]);
const items = computed(() => props.items as TreeItem[]);
const rootId = computed(() => props.rootId);
const rootLabel = computed(() => props.rootLabel);
const treeItems = computed<TreeItem[]>(() => {
    const root = {
        id: rootId.value,
        parentId: null,
        afterId: null,
        active: true,
        childCount: items.value.filter((item) => (item.parentId ?? null) === null).length,
        entity: {
            id: rootId.value,
            label: rootLabel.value,
            active: true,
        } as unknown as DictionaryItem,
        name: rootLabel.value,
        isDictionaryRoot: true,
    } as TreeItem;

    return [
        root,
        ...items.value.map((item) => ({
            ...item,
            active: item.entity?.active ?? (item as unknown as DictionaryItem).active,
            parentId: item.parentId ?? rootId.value,
        })),
    ];
});
const activeItemId = computed(() => props.activeItemId);
const isLoading = computed(() => props.isLoading);
const canEdit = computed(() => props.canEdit);
const canCreate = computed(() => props.canCreate);
const canDelete = computed(() => props.canDelete);
const sortable = computed(() => props.canEdit);

const onSelect = (item: DictionaryItem): void => emit('select-item', item);
const onAddChild = (item: DictionaryItem | null): void => emit('add-child', item);
const onDelete = (item: DictionaryItem): void => emit('delete-item', item);
const onSelectTreeItem = (item: RenderedTreeItem): void => {
    if (!item.data.isDictionaryRoot) {
        onSelect(item.data.entity);
    }
};
const onAddChildTreeItem = (item: RenderedTreeItem): void => {
    onAddChild(item.data.isDictionaryRoot ? null : item.data.entity);
};
const onDragEnd = (payload: unknown): void => {
    if (payload && typeof payload === 'object') {
        const dragPayload = payload as {
            draggedItem?: { data?: { id?: string } };
            oldParentId?: string | null;
            newParentId?: string | null;
        };
        if (dragPayload.draggedItem?.data?.id === rootId.value) {
            return;
        }

        emit('drag-end', {
            ...dragPayload,
            oldParentId: dragPayload.oldParentId === rootId.value ? null : dragPayload.oldParentId,
            newParentId: dragPayload.newParentId === rootId.value ? null : dragPayload.newParentId,
        });

        return;
    }

    emit('drag-end', payload);
};

swDefinePublic({
    onSelect,
    onAddChild,
    onAddChildTreeItem,
    onSelectTreeItem,
    onDelete,
    onDragEnd,
});

defineExpose({
    items,
    treeItems,
    rootId,
    rootLabel,
    activeItemId,
    isLoading,
    canEdit,
    canCreate,
    canDelete,
    sortable,
    onSelect,
    onAddChild,
    onAddChildTreeItem,
    onSelectTreeItem,
    onDelete,
    onDragEnd,
});
</script>

<style lang="scss">
.ct-data-dictionary-tree {
    height: 100%;

    &__inner.ct-tree {
        width: 100%;
        min-height: 360px;
        border: 0;
    }

    .ct-tree-item__element {
        height: 52px;
    }

    .ct-tree-item__content {
        padding-block: 5px;
    }

    &__item {
        display: flex;
        flex: 1;
        flex-direction: column;
        align-items: flex-start;
        min-width: 0;
        padding: 0;
        border: 0;
        background: transparent;
        color: inherit;
        cursor: pointer;
        text-align: left;

        &--root {
            cursor: default;
        }
    }

    &__item-label {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    &__item-label {
        font-weight: var(--mt-font-weight-semibold);
    }
}
</style>

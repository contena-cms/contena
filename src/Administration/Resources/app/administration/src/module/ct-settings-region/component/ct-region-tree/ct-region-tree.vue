<template>
    <ct-block name="ct_region_tree">
        <ct-tree
            ref="tree"
            class="ct-region-tree"
            :items="items"
            :searchable="false"
            :sortable="false"
            translation-context="ct-settings-region.tree"
            :allow-delete-items="canDelete"
            :allow-create-items="false"
            :disable-context-menu="!canEdit && !canCreate && !canDelete"
            @get-tree-items="onLoadChildren"
            @delete-element="onDeleteRegion"
            @batch-delete="onBatchDelete"
            @checked-elements-count="onCheckedElementsCount"
        >
            <template #headline>
                <div v-if="checkedElementsCount > 0" class="ct-tree-actions__headline">
                    <span>
                        {{ $t('ct-settings-region.tree.general.treeHeadSelected', { count: checkedElementsCount }) }}:
                    </span>
                    <mt-button
                        class="ct-tree-actions__delete-items"
                        variant="critical"
                        size="small"
                        @click="onDeleteCheckedRegions"
                    >
                        {{ $t('global.default.delete') }}
                    </mt-button>
                </div>
                <span v-else class="ct-region-tree__headline-spacer" aria-hidden="true"></span>
            </template>

            <template #items="{ treeItems: visibleTreeItems, checkItem, selectedItemsPathIds, checkedItemIds }">
                <ct-tree-item
                    v-for="item in visibleTreeItems"
                    :key="item.id"
                    :item="item"
                    :sortable="false"
                    :display-checkbox="canDelete"
                    :active-parent-ids="selectedItemsPathIds"
                    :active-item-ids="checkedItemIds"
                    :disable-context-menu="!canEdit && !canCreate && !canDelete"
                    @check-item="checkItem"
                >
                    <template #content="{ item: treeItem }">
                        <button
                            class="ct-region-tree__item"
                            :class="{ 'is--selected': treeItem.data.id === selectedRegionId }"
                            type="button"
                            @click="onSelectRegion(treeItem)"
                        >
                            <span>{{ getRegionName(treeItem.data) }}</span>
                            <small v-if="treeItem.data.code">{{ treeItem.data.code }}</small>
                        </button>
                    </template>

                    <template #actions="{ item: treeItem, deleteElement }">
                        <ct-context-button>
                            <ct-context-menu-item
                                class="ct-region-tree__add-child-action"
                                :disabled="!canCreate || undefined"
                                @click="onAddChildRegion(treeItem)"
                            >
                                {{ $t('ct-settings-region.list.addChildRegion') }}
                            </ct-context-menu-item>
                            <ct-context-menu-item
                                class="ct-region-tree__edit-action"
                                :disabled="!canEdit || undefined"
                                @click="onSelectRegion(treeItem)"
                            >
                                {{ $t('global.default.edit') }}
                            </ct-context-menu-item>
                            <ct-context-menu-item
                                class="ct-region-tree__delete-action"
                                variant="danger"
                                :disabled="!canDelete || undefined"
                                @click="deleteElement(treeItem)"
                            >
                                {{ $t('global.default.delete') }}
                            </ct-context-menu-item>
                        </ct-context-button>
                    </template>
                </ct-tree-item>
            </template>
        </ct-tree>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import type { PropType } from 'vue';
import { ref } from 'vue';

type Region = Entity<'region'>;
type TreeItem = Region & { afterId: string | null; childCount: number };
type RenderedTreeItem = { id: string; data: Region };
// The tuple documents the exposed tree component contract.

type TreeComponent = { onDeleteElements: (...args: [null]) => void };

defineProps({
    items: {
        type: Array as PropType<TreeItem[]>,
        required: true,
    },
    selectedRegionId: {
        type: String as PropType<string | null>,
        default: null,
    },
    canCreate: {
        type: Boolean,
        default: false,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits<{
    'load-children': [parentId: string];
    'select-region': [treeItem: RenderedTreeItem];
    'add-child-region': [treeItem: RenderedTreeItem];
    'delete-region': [treeItem: RenderedTreeItem];
    'batch-delete': [selection: unknown];
}>();
const tree = ref<TreeComponent | null>(null);
const checkedElementsCount = ref(0);
const getRegionName = (region: Region): string => region.translated?.name ?? region.name ?? region.code ?? '';
const onLoadChildren = (parentId: string): void => emit('load-children', parentId);
const onSelectRegion = (treeItem: RenderedTreeItem): void => emit('select-region', treeItem);
const onAddChildRegion = (treeItem: RenderedTreeItem): void => emit('add-child-region', treeItem);
const onDeleteRegion = (treeItem: RenderedTreeItem): void => emit('delete-region', treeItem);
const onBatchDelete = (selection: unknown): void => emit('batch-delete', selection);
const onCheckedElementsCount = (count: number): void => {
    checkedElementsCount.value = count;
};
const onDeleteCheckedRegions = (): void => tree.value?.onDeleteElements(null);

ctDefinePublic({
    checkedElementsCount,
    getRegionName,
    onLoadChildren,
    onSelectRegion,
    onAddChildRegion,
    onDeleteRegion,
    onBatchDelete,
    onCheckedElementsCount,
    onDeleteCheckedRegions,
});

defineExpose({
    checkedElementsCount,
    getRegionName,
    onLoadChildren,
    onSelectRegion,
    onAddChildRegion,
    onDeleteRegion,
    onBatchDelete,
    onCheckedElementsCount,
    onDeleteCheckedRegions,
});
</script>

<style scoped lang="scss">
.ct-region-tree {
    width: 100%;
    min-height: 520px;
    border: 0;

    // The generic tree adds a full-panel focus ring whenever one of its
    // keyboard targets is focused. Region selection is represented by the
    // inline row background, so the panel ring and the nested row outline
    // are redundant and visually overpower the two-column workspace.
    &:focus-within {
        box-shadow: none;
    }

    :deep(.ct-tree-item:focus > .ct-tree-item__element) {
        box-shadow: none;
    }

    &__item {
        display: flex;
        flex: 1;
        flex-direction: column;
        align-items: flex-start;
        min-width: 0;
        padding: var(--scale-size-4) var(--scale-size-8);
        border: 0;
        border-radius: var(--border-radius-xs);
        background: transparent;
        color: inherit;
        cursor: pointer;
        text-align: left;

        &:hover,
        &.is--selected {
            background: var(--color-background-brand-default);
            color: var(--color-text-brand-default);
        }

        span {
            max-width: 100%;
            overflow: hidden;
            font-weight: var(--mt-font-weight-semibold);
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        small {
            color: var(--color-text-secondary-default);
        }
    }

    &__headline-spacer {
        display: none;
    }
}
</style>

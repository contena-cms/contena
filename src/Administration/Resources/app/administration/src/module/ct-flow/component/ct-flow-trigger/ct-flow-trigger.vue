<template>
    <ct-block name="ct_flow_trigger">
        <div ref="rootElement" class="ct-flow-trigger overlay">
            <ct-block name="ct_flow_trigger_select_field">
                <mt-text-field
                    id="ct-flow-trigger-input"
                    v-model="searchTerm"
                    class="ct-flow-trigger__input"
                    required
                    :label="$t('ct-flow.detail.event')"
                    :placeholder="$t('ct-flow.detail.eventPlaceholderShort')"
                    :disabled="disabled || undefined"
                    @focus="openDropdown"
                    @click="openDropdown"
                    @keydown="handleKeydown"
                >
                    <template #suffix>
                        <mt-icon name="regular-chevron-down-xs" size="10px" />
                    </template>
                </mt-text-field>
            </ct-block>

            <transition name="ct-flow-trigger__fade-down">
                <ct-block name="ct_flow_trigger_event_selection">
                    <template v-if="isExpanded">
                        <div class="ct-flow-trigger__event-selection">
                            <div v-if="!isSearching" class="ct-flow-trigger__tree">
                                <ct-tree
                                    ref="triggerTree"
                                    :items="treeItems"
                                    :active-tree-item-id="eventName"
                                    :sortable="false"
                                    :searchable="false"
                                    :disable-context-menu="true"
                                    :on-change-route="changeTrigger"
                                    route-params-active-element-id="eventName"
                                    bind-items-to-folder
                                >
                                    <template #headline><span></span></template>
                                    <template #search><span></span></template>
                                    <template
                                        #items="{
                                            treeItems: visibleTreeItems,
                                            sortable,
                                            disableContextMenu,
                                            onChangeRoute,
                                            checkItem,
                                        }"
                                    >
                                        <ct-tree-item
                                            v-for="item in visibleTreeItems"
                                            :key="item.id"
                                            should-focus
                                            :active-focus-id="focusedTreeItemId"
                                            :sortable="sortable"
                                            :item="item"
                                            :on-change-route="onChangeRoute"
                                            :display-checkbox="false"
                                            :disable-context-menu="disableContextMenu"
                                            @check-item="checkItem"
                                        >
                                            <template #actions><span></span></template>
                                        </ct-tree-item>
                                    </template>
                                </ct-tree>
                            </div>

                            <ul v-else-if="searchResults.length > 0" class="ct-flow-trigger__search-results">
                                <li v-for="(event, index) in searchResults" :key="event.name">
                                    <mt-button
                                        class="ct-flow-trigger__search-result"
                                        :class="{ 'is--focused': index === focusIndex }"
                                        variant="tertiary"
                                        @click="selectEvent(event.name)"
                                    >
                                        <mt-icon name="regular-circle-xxs" size="8px" />
                                        <span>{{ formatEventName(event.name) }}</span>
                                    </mt-button>
                                </li>
                            </ul>

                            <p v-else class="ct-flow-trigger__empty">
                                {{ $t('ct-flow.detail.eventEmpty') }}
                            </p>
                        </div>
                    </template>
                </ct-block>
            </transition>

            <mt-modal-root v-if="showConfirmModal" :is-open="true" @change="onConfirmModalChange">
                <mt-modal :title="$t('ct-flow.detail.triggerChangeTitle')" width="s">
                    <p>{{ $t('ct-flow.detail.triggerChangeText') }}</p>
                    <template #footer>
                        <mt-button variant="secondary" @click="cancelTriggerChange">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                        <mt-button variant="primary" @click="confirmTriggerChange">
                            {{ $t('global.default.confirm') }}
                        </mt-button>
                    </template>
                </mt-modal>
            </mt-modal-root>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';

import type { FlowEventOption } from '../flow-sequence.types';

interface EventTreeNode {
    id: string;
    label: string;
    children: EventTreeNode[];
}

interface EventTreeRow {
    node: EventTreeNode;
    depth: number;
}

interface TreeItem {
    id: string;
    name: string;
    parentId: string | null;
    childCount: number;
}

interface TreeComponent {
    openTreeById?(id: string): void;
}

interface RenderedTreeItem {
    id: string;
    childCount: number;
    disabled?: boolean;
    data: TreeItem;
}

const props = defineProps({
    eventName: { type: String, required: true },
    events: { type: Array as PropType<FlowEventOption[]>, required: true },
    hasSequences: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits<{ 'update:eventName': [eventName: string] }>();
const i18n = useI18n();

const rootElement = ref<HTMLElement | null>(null);
const triggerTree = ref<TreeComponent | null>(null);
const searchTerm = ref('');
const isExpanded = ref(false);
const expandedIds = ref(new Set<string>());
const focusIndex = ref(0);
const pendingEventName = ref<string | null>(null);
const showConfirmModal = ref(false);

const translatePart = (value: string): string => {
    const words = value.split(/[_-]/);
    const snippetName = words
        .map((word, index) => (index === 0 ? word : `${word.slice(0, 1).toUpperCase()}${word.slice(1)}`))
        .join('');
    const snippetKey = `ct-flow.triggers.${snippetName}`;
    if (i18n.te(snippetKey)) {
        const translation = i18n.t(snippetKey);
        if (translation !== snippetKey) {
            return translation;
        }
    }

    const normalized = value.replace(/[_-]/g, ' ');
    return `${normalized.slice(0, 1).toUpperCase()}${normalized.slice(1)}`;
};
const formatEventName = (eventName: string): string => eventName.split('.').map(translatePart).join(' / ');

const eventTree = computed<EventTreeNode[]>(() => {
    const roots: EventTreeNode[] = [];
    props.events.forEach((event) => {
        let siblings = roots;
        const path: string[] = [];
        event.name.split('.').forEach((part) => {
            path.push(part);
            const id = path.join('.');
            let node = siblings.find((item) => item.id === id);
            if (!node) {
                node = { id, label: translatePart(part), children: [] };
                siblings.push(node);
            }
            siblings = node.children;
        });
    });
    return roots;
});

const visibleTreeRows = computed<EventTreeRow[]>(() => {
    const rows: EventTreeRow[] = [];
    const append = (nodes: EventTreeNode[], depth: number): void => {
        nodes.forEach((node) => {
            rows.push({ node, depth });
            if (node.children.length > 0 && expandedIds.value.has(node.id)) {
                append(node.children, depth + 1);
            }
        });
    };
    append(eventTree.value, 0);
    return rows;
});
const treeItems = computed<TreeItem[]>(() => {
    const items: TreeItem[] = [];
    const append = (nodes: EventTreeNode[], parentId: string | null): void => {
        nodes.forEach((node) => {
            items.push({
                id: node.id,
                name: node.label,
                parentId,
                childCount: node.children.length,
            });
            append(node.children, node.id);
        });
    };
    append(eventTree.value, null);
    return items;
});
const focusedTreeItemId = computed(() => visibleTreeRows.value[focusIndex.value]?.node.id ?? '');
const isSearching = computed(() => searchTerm.value.length > 0 && searchTerm.value !== formatEventName(props.eventName));
const searchResults = computed(() => {
    if (!isSearching.value) return [];
    const keywords = searchTerm.value
        .toLowerCase()
        .split(/[\W_]+/)
        .filter(Boolean);
    return props.events.filter((event) => {
        const name = formatEventName(event.name).toLowerCase();
        return keywords.every((keyword) => name.includes(keyword));
    });
});

const resetSearch = (): void => {
    searchTerm.value = props.eventName ? formatEventName(props.eventName) : '';
};
const openDropdown = (): void => {
    if (props.disabled) return;
    isExpanded.value = true;
    focusIndex.value = 0;
};
const closeDropdown = (restoreSearch = true): void => {
    isExpanded.value = false;
    if (restoreSearch) resetSearch();
};
const toggleNode = (node: EventTreeNode): void => {
    const next = new Set(expandedIds.value);
    if (next.has(node.id)) next.delete(node.id);
    else next.add(node.id);
    expandedIds.value = next;
};
const commitEvent = (eventName: string): void => {
    emit('update:eventName', eventName);
    searchTerm.value = formatEventName(eventName);
    closeDropdown(false);
};
const selectEvent = (eventName: string): void => {
    if (eventName === props.eventName) {
        closeDropdown();
        return;
    }
    if (props.hasSequences) {
        pendingEventName.value = eventName;
        showConfirmModal.value = true;
        closeDropdown();
        return;
    }
    commitEvent(eventName);
};
const activateTreeRow = (node: EventTreeNode, index: number): void => {
    focusIndex.value = index;
    if (node.children.length > 0) {
        toggleNode(node);
        return;
    }
    selectEvent(node.id);
};
const changeTrigger = (item: RenderedTreeItem): void => {
    if (item.disabled || item.childCount > 0) return;
    selectEvent(item.data.id);
};
const focusedNode = (): EventTreeNode | FlowEventOption | undefined =>
    isSearching.value ? searchResults.value[focusIndex.value] : visibleTreeRows.value[focusIndex.value]?.node;
const handleKeydown = (event: KeyboardEvent): void => {
    if (!isExpanded.value && (event.key === 'ArrowDown' || event.key === 'Enter')) {
        event.preventDefault();
        openDropdown();
        return;
    }
    if (!isExpanded.value) return;
    const items = isSearching.value ? searchResults.value : visibleTreeRows.value;
    if (event.key === 'Escape' || event.key === 'Tab') {
        closeDropdown();
        return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const offset = event.key === 'ArrowDown' ? 1 : -1;
        focusIndex.value = Math.min(Math.max(focusIndex.value + offset, 0), Math.max(items.length - 1, 0));
        if (!isSearching.value) {
            void nextTick(() => {
                rootElement.value?.querySelector<HTMLElement>(`[data-item-id="${focusedTreeItemId.value}"]`)?.focus();
            });
        }
        return;
    }
    const item = focusedNode();
    if (!item) return;
    if (event.key === 'Enter') {
        event.preventDefault();
        if ('children' in item && item.children.length > 0) toggleNode(item);
        else selectEvent('name' in item ? item.name : item.id);
        return;
    }
    if (!isSearching.value && 'children' in item) {
        if (event.key === 'ArrowRight' && item.children.length > 0 && !expandedIds.value.has(item.id)) {
            event.preventDefault();
            toggleNode(item);
        }
        if (event.key === 'ArrowLeft' && expandedIds.value.has(item.id)) {
            event.preventDefault();
            toggleNode(item);
        }
    }
};
const confirmTriggerChange = (): void => {
    if (pendingEventName.value) commitEvent(pendingEventName.value);
    pendingEventName.value = null;
    showConfirmModal.value = false;
};
const cancelTriggerChange = (): void => {
    pendingEventName.value = null;
    showConfirmModal.value = false;
    resetSearch();
};
const onConfirmModalChange = (isOpen: boolean): void => {
    if (!isOpen) cancelTriggerChange();
};
const handleDocumentClick = (event: MouseEvent): void => {
    if (!isExpanded.value || rootElement.value?.contains(event.target as Node)) return;
    closeDropdown();
};

watch(
    () => props.eventName,
    () => {
        resetSearch();
    },
    { immediate: true },
);
watch(searchResults, () => {
    focusIndex.value = 0;
});
onMounted(() => document.addEventListener('click', handleDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', handleDocumentClick));

ctDefinePublic({
    searchTerm,
    isExpanded,
    expandedIds,
    focusIndex,
    triggerTree,
    eventTree,
    treeItems,
    visibleTreeRows,
    focusedTreeItemId,
    isSearching,
    searchResults,
    showConfirmModal,
    formatEventName,
    openDropdown,
    closeDropdown,
    activateTreeRow,
    changeTrigger,
    selectEvent,
    handleKeydown,
    confirmTriggerChange,
    cancelTriggerChange,
    onConfirmModalChange,
});

defineExpose({
    searchTerm,
    isExpanded,
    expandedIds,
    focusIndex,
    triggerTree,
    eventTree,
    treeItems,
    visibleTreeRows,
    focusedTreeItemId,
    isSearching,
    searchResults,
    showConfirmModal,
    formatEventName,
    openDropdown,
    closeDropdown,
    activateTreeRow,
    changeTrigger,
    selectEvent,
    handleKeydown,
    confirmTriggerChange,
    cancelTriggerChange,
    onConfirmModalChange,
});
</script>

<style scoped>
.ct-flow-trigger {
    position: relative;
}

.ct-flow-trigger__input {
    margin-bottom: 0;
}

.ct-flow-trigger__input :deep(.mt-block-field__block input) {
    padding-right: var(--scale-size-40, 40px);
    background: var(--color-elevation-surface-raised, #fff);
}

.ct-flow-trigger__input :deep(.mt-field__addition) {
    min-width: auto;
    padding: 0 var(--scale-size-16, 16px) 0 0;
    border-left: 0;
    background: transparent;
}

.ct-flow-trigger__event-selection {
    position: absolute;
    z-index: 700;
    top: calc(100% + var(--scale-size-4, 4px));
    left: 0;
    min-width: 100%;
    max-width: 150%;
    border-radius: var(--border-radius-xs, 4px);
    background: var(--color-elevation-surface-raised, #fff);
    box-shadow: 0 1px 6px 0 var(--color-elevation-shadow-default, rgb(0 0 0 / 20%));
}

.ct-flow-trigger__tree :deep(.ct-tree__content) {
    max-height: 38rem;
}

.ct-flow-trigger__tree :deep(.ct-tree-item__actions) {
    display: none;
}

.ct-flow-trigger__search-result:hover,
.ct-flow-trigger__search-result.is--focused {
    background-color: var(--color-background-brand-default, #e7f0ff);
    color: var(--color-text-brand-default, #0870ff);
}

.ct-flow-trigger__search-result {
    display: flex;
    align-items: center;
    width: 100%;
    padding: var(--scale-size-12, 12px) var(--scale-size-4, 4px);
    justify-content: flex-start;
    border-radius: var(--border-radius-xs, 4px);
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
    text-align: left;
    transition: background-color 0.1s ease;
    cursor: pointer;
}

.ct-flow-trigger__search-result :deep(.mt-icon) {
    margin-inline: var(--scale-size-12, 12px);
}

.ct-flow-trigger__search-results {
    margin: 0;
    padding: var(--scale-size-12, 12px);
    list-style: none;
}

.ct-flow-trigger__empty {
    margin: 0;
    padding: var(--scale-size-16, 16px);
    color: var(--color-text-secondary-default, #52667a);
}

.ct-flow-trigger__fade-down-enter-active,
.ct-flow-trigger__fade-down-leave-active {
    transition: all 0.2s ease-in-out;
}

.ct-flow-trigger__fade-down-enter-from,
.ct-flow-trigger__fade-down-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

@media screen and (max-height: 60rem) {
    .ct-flow-trigger__search-results,
    .ct-flow-trigger__tree :deep(.ct-tree__content) {
        max-height: 25rem;
    }
}

@media screen and (max-height: 64rem) {
    .ct-flow-trigger__search-results,
    .ct-flow-trigger__tree :deep(.ct-tree__content) {
        max-height: 30rem;
    }
}
</style>

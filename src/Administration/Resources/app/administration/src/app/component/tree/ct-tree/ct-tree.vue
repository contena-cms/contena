<template>
    <ct-block name="ct_tree">
        <div ref="treeRoot" class="ct-tree" role="tree" :aria-label="$t(`${translationContext}.general.treeHeadline`)">
            <ct-block name="ct_tree_search">
                <slot name="search">
                    <div v-if="searchable" class="ct-tree__search">
                        <mt-text-field
                            v-model="currentTreeSearch"
                            name="treeSearch"
                            :placeholder="$t('ct-tree.general.placeholderSearch')"
                            size="small"
                            @update:model-value="searchItems"
                        >
                            <template #prefix>
                                <mt-icon name="regular-search" />
                            </template>
                        </mt-text-field>
                    </div>
                </slot>
            </ct-block>

            <ct-block name="ct_tree_actions_headline">
                <slot name="headline">
                    <div v-if="checkedElementsCount > 0" class="ct-tree-actions__headline">
                        <span>
                            {{
                                $t(`${translationContext}.general.treeHeadSelected`, { count: checkedElementsCount })
                            }}:</span
                        >

                        <mt-button
                            class="ct-tree-actions__delete-items"
                            :disabled="!allowDeleteItems || undefined"
                            variant="critical"
                            size="small"
                            @click="onDeleteElements(null)"
                        >
                            {{ $t('global.default.delete') }}
                        </mt-button>
                    </div>

                    <div v-else class="ct-tree-actions__headline">
                        <span>{{ $t(`${translationContext}.general.treeHeadline`) }}</span>
                    </div>
                </slot>
            </ct-block>

            <ct-block name="ct_tree_content">
                <div class="ct-tree__content">
                    <div class="tree-items">
                        <ct-block name="ct_tree_items">
                            <ct-tree-input-field
                                v-if="hasNoItems && allowCreateItems"
                                :disabled="disableContextMenu"
                                @new-item-create="onCreateNewItem"
                            />
                            <slot
                                v-else
                                name="items"
                                :tree-items="treeItems"
                                :dragged-item="draggedItem"
                                :new-element-id="newElementId"
                                :check-item="checkItem"
                                :translation-context="translationContext"
                                :on-change-route="onChangeRoute"
                                :sortable="sortable"
                                :disable-context-menu="disableContextMenu"
                                :selected-items-path-ids="selectedItemsPathIds"
                                :checked-item-ids="checkedItemIds"
                            >
                                <ct-block name="ct_tree_slot_items">
                                    <ct-tree-item
                                        v-for="item in treeItems"
                                        :key="item.id"
                                        :item="item"
                                        :translation-context="translationContext"
                                        :dragged-item="draggedItem"
                                        :active-parent-ids="selectedItemsPathIds"
                                        :active-item-ids="checkedItemIds"
                                        @check-item="checkItem"
                                    />
                                </ct-block>
                            </slot>
                        </ct-block>
                    </div>
                </div>
            </ct-block>

            <ct-block name="ct_tree_delete_modal">
                <ct-modal
                    v-if="showDeleteModal"
                    :title="$t('global.default.warning')"
                    variant="small"
                    @modal-close="onCloseDeleteModal"
                >
                    <ct-block name="ct_tree_delete_modal_confirm_delete_text">
                        <div v-if="toDeleteItem">
                            <p v-if="toDeleteItem.childCount > 0" class="ct_tree__confirm-delete-text">
                                {{
                                    $t(`${translationContext}.modal.textDeleteConfirm`, {
                                        name: toDeleteItem.data.name || toDeleteItem.data.translated.name,
                                    })
                                }}<br />
                                <b>{{ $t(`${translationContext}.modal.textDeleteChildrenConfirm`) }}</b>
                            </p>

                            <p v-else class="ct_tree__confirm-delete-text">
                                {{
                                    $t(`${translationContext}.modal.textDeleteConfirm`, {
                                        name: toDeleteItem.data.name || toDeleteItem.data.translated.name,
                                    })
                                }}
                            </p>
                        </div>

                        <div v-else>
                            <p v-if="checkedElementsChildCount > 0" class="ct_tree__confirm-delete-text">
                                {{
                                    $t(`${translationContext}.modal.textDeleteMultipleConfirm`, {
                                        count: checkedElementsCount,
                                    })
                                }}<br />
                                <b>{{ $t(`${translationContext}.modal.textDeleteChildrenConfirm`) }}</b>
                            </p>

                            <p v-else class="ct_tree__confirm-delete-text">
                                {{
                                    $t(`${translationContext}.modal.textDeleteMultipleConfirm`, {
                                        count: checkedElementsCount,
                                    })
                                }}
                            </p>
                        </div>
                    </ct-block>

                    <template #modal-footer>
                        <ct-block name="ct_tree_delete_modal_footer">
                            <ct-block name="ct_tree_delete_modal_cancel">
                                <mt-button size="small" variant="secondary" @click="onCloseDeleteModal">
                                    {{ $t('global.default.cancel') }}
                                </mt-button>
                            </ct-block>

                            <ct-block name="ct_tree_delete_modal_confirm">
                                <mt-button variant="critical" size="small" @click="onConfirmDelete()">
                                    {{ $t('global.default.delete') }}
                                </mt-button>
                            </ct-block>
                        </ct-block>
                    </template>
                </ct-modal>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-tree.scss';
const { debounce, sort } = Contena.Utils;

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },

    rootParentId: {
        type: String,
        required: false,
        default: () => {
            return null;
        },
    },

    parentProperty: {
        type: String,
        required: false,
        default: () => {
            return 'parentId';
        },
    },

    afterIdProperty: {
        type: String,
        required: false,
        default: () => {
            return 'afterId';
        },
    },

    childCountProperty: {
        type: String,
        required: false,
        default: () => {
            return 'childCount';
        },
    },

    searchable: {
        type: Boolean,
        required: false,
        default: () => {
            return true;
        },
    },

    activeTreeItemId: {
        type: String,
        required: false,
        default: () => {
            return '';
        },
    },

    routeParamsActiveElementId: {
        type: String,
        required: false,
        default: () => {
            return 'id';
        },
    },

    translationContext: {
        type: String,
        required: false,
        default: () => {
            return 'ct-tree';
        },
    },

    onChangeRoute: {
        type: Function,
        required: false,
        default: () => {
            return null;
        },
    },

    disableContextMenu: {
        type: Boolean,
        required: false,
        default: () => {
            return false;
        },
    },

    bindItemsToFolder: {
        type: Boolean,
        required: false,
        default: () => {
            return false;
        },
    },

    sortable: {
        type: Boolean,
        required: false,
        default: () => {
            return true;
        },
    },

    checkItemsInitial: {
        type: Boolean,
        required: false,
        default: () => {
            return false;
        },
    },

    allowDeleteItems: {
        type: Boolean,
        required: false,
        default: () => {
            return true;
        },
    },

    allowCreateItems: {
        type: Boolean,
        required: false,
        default: () => {
            return true;
        },
    },

    initiallyExpandedRoot: {
        type: Boolean,
        required: false,
        default: () => {
            return false;
        },
    },

    ariaLabel: {
        type: String,
        required: false,
        default: null,
    },
    treeActions: {
        type: Object,
        required: false,
        default: () => ({}),
    },
});
const emit = defineEmits([
    'checked-elements-count',
    'get-tree-items',
    'search-tree-items',
    'drag-start',
    'drag-end',
    'delete-element',
    'editing-end',
    'batch-delete',
    'save-tree-items',
]);

import { ref, computed, watch, useSlots, useAttrs, provide, onMounted, onUnmounted, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const slots = useSlots();
const attrs = useAttrs();
const treeRoot = ref(null);

const treeItems = ref([]);
const draggedItem = ref(null);
const droppedItem = ref(null);
const isLoading = ref(false);
const currentTreeSearch = ref(null);
const newElementId = ref(null);
const contextItem = ref(null);
const currentEditMode = ref(null);
const addElementPosition = ref(null);
const eventFromEdit = ref(null);
const createdItem = ref(null);
const checkedElements = ref({});
const checkedElementsCount = ref(0);
const showDeleteModal = ref(false);
const toDeleteItem = ref(null);
const checkedElementsChildCount = ref(0);

const activeElementId = computed(() => {
    return route.params[props.routeParamsActiveElementId] || null;
});
const isSortable = computed(() => {
    if (currentTreeSearch.value !== null) {
        return false;
    }

    return props.sortable;
});
const isSearched = computed(() => {
    return currentTreeSearch.value !== null && currentTreeSearch.value.length > 0;
});
const hasActionSlot = computed(() => {
    return slots && slots.actions;
});
const hasNoItems = computed(() => {
    if (props.items.length === 1 && props.items[0] && props.items[0].isDeleted) {
        return true;
    }
    return props.items.length < 1;
});
const selectedItemsPathIds = computed(() => {
    return Object.keys(checkedElements.value).reduce((acc, itemId) => {
        const item = findById(itemId);

        // get each parent id
        const pathIds = item?.data?.path?.split('|').filter((pathId) => pathId.length > 0) ?? '';

        // add parent id to accumulator
        return [
            ...acc,
            ...pathIds,
        ];
    }, []);
});
const checkedItemIds = computed(() => {
    return Object.keys(checkedElements.value);
});

const createdComponent = () => {
    if (props.activeTreeItemId && activeElementId.value) {
        openTreeById();
    }
    emit('checked-elements-count', checkedElementsCount.value);
};
const mountedComponent = () => {
    // Focus handling
    treeRoot.value?.addEventListener('focusin', handleFocusIn);
    treeRoot.value?.addEventListener('keydown', handleKeyDown);
};
const beforeUnmountedComponent = () => {
    treeRoot.value?.removeEventListener('focusin', handleFocusIn);
    treeRoot.value?.removeEventListener('keydown', handleKeyDown);
};
function handleFocusIn(event) {
    // Check if focus in already in the tree on any tree item
    if (event.target.classList.contains('ct-tree-item') || event.target.classList.contains('ct-tree-item__toggle')) {
        // If focus is already on a tree item, do nothing
        return;
    }

    // Check if target is a input element
    if (event.target.tagName === 'INPUT') {
        // If focus is on an input element, do nothing
        return;
    }

    /* Check recursively if any tree item is active, if yes, focus on it.
     * If no tree item is active, focus on the tree item closest to the event target.
     */
    const activeTreeItem = treeRoot.value?.querySelector('.ct-tree-item[aria-current="page"]');
    if (activeTreeItem) {
        activeTreeItem.focus();
    } else {
        const closestTreeItem = event.target.closest('.ct-tree-item') || treeRoot.value?.querySelector('.ct-tree-item');
        closestTreeItem?.focus();
    }
}
function handleKeyDown(event) {
    switch (event.key) {
        case 'Tab': {
            // Tab out of the tree to the next focusable element

            // Add inert attribute to the tree
            treeRoot.value?.setAttribute('inert', '');

            // Remove inert attribute from the tree after normal tabbing behavior is done
            setTimeout(() => {
                treeRoot.value?.removeAttribute('inert');
            }, 0);
            break;
        }
        case 'ArrowDown': {
            const currentFocusedTreeItem = treeRoot.value?.querySelector('.ct-tree-item:focus');
            if (!currentFocusedTreeItem) {
                break;
            }

            // Check if current focused tree is open
            const isTreeItemOpen = currentFocusedTreeItem.getAttribute('aria-expanded') === 'true';

            // If tree item is open, focus on the first child tree item
            if (isTreeItemOpen) {
                const firstChildTreeItem = currentFocusedTreeItem.querySelector('.ct-tree-item');
                if (firstChildTreeItem) {
                    firstChildTreeItem.focus();
                    break;
                }
            }
            const nextTreeItem = currentFocusedTreeItem.nextElementSibling;
            if (nextTreeItem) {
                nextTreeItem.focus();
                break;
            }

            // If no next tree item is found, look at the parent tree item
            const parentTreeItem = currentFocusedTreeItem.parentElement.closest('.ct-tree-item');
            // Get the next sibling of the parent tree item
            const nextParentTreeItem = parentTreeItem.nextElementSibling;
            if (nextParentTreeItem) {
                nextParentTreeItem.focus();
                break;
            }
            break;
        }
        case 'ArrowUp': {
            const currentFocusedTreeItem = treeRoot.value?.querySelector('.ct-tree-item:focus');
            if (!currentFocusedTreeItem) {
                break;
            }
            const visibleTreeItems = Array.from(treeRoot.value?.querySelectorAll('.ct-tree-item') ?? []);
            const currentIndex = visibleTreeItems.indexOf(currentFocusedTreeItem);
            visibleTreeItems[currentIndex - 1]?.focus();
            break;
        }

        // Space key
        case ' ': {
            // Toggle the checkbox of the focused tree item
            const currentFocusedTreeItem = document.activeElement;

            // Check if active element is a tree item
            if (!currentFocusedTreeItem.classList.contains('ct-tree-item')) {
                break;
            }
            const itemId = currentFocusedTreeItem.getAttribute('data-item-id');
            if (!itemId) {
                break;
            }

            // Get tree item from the recursive this.treeItems array
            const treeItem = findById(itemId);
            if (!treeItem) {
                break;
            }

            // Toggle the tree item
            treeItem.checked = !treeItem.checked;
            checkItem(treeItem);
            break;
        }

        // Enter key
        case 'Enter': {
            // Change route to the focused tree item
            const currentFocusedTreeItem = document.activeElement;

            // Check if active element is a tree item
            if (!currentFocusedTreeItem.classList.contains('ct-tree-item')) {
                break;
            }
            const itemId = currentFocusedTreeItem.getAttribute('data-item-id');
            if (!itemId) {
                break;
            }

            // Get tree item from the recursive this.treeItems array
            const treeItem = findById(itemId);
            if (!treeItem) {
                break;
            }
            props.onChangeRoute(treeItem);
            break;
        }
        case 'ArrowLeft': {
            /* Closing is handled by the tree item component.
             * This event just gets triggered when event is not handled by the tree item component.
             * Then we need to focus the parent tree item.
             */
            const currentFocusedTreeItem = document.activeElement;

            // Check if active element is a tree item
            if (!currentFocusedTreeItem.classList.contains('ct-tree-item')) {
                break;
            }
            const parentTreeItem = currentFocusedTreeItem.parentElement.closest('.ct-tree-item');
            if (parentTreeItem) {
                parentTreeItem.focus();
            }
            break;
        }
        case 'ArrowRight': {
            /* Opening is handled by the tree item component.
             * This event just gets triggered when event is not handled by the tree item component.
             * Then we need to focus the first child tree item.
             */
            const currentFocusedTreeItem = document.activeElement;

            // Check if active element is a tree item
            if (!currentFocusedTreeItem.classList.contains('ct-tree-item')) {
                break;
            }

            // Check if current focused tree is open
            const isTreeItemOpen = currentFocusedTreeItem.getAttribute('aria-expanded') === 'true';

            // If tree item is open, focus on the first child tree item
            if (!isTreeItemOpen) {
                break;
            }
            const firstChildTreeItem = currentFocusedTreeItem.querySelector('.ct-tree-item');
            if (firstChildTreeItem) {
                firstChildTreeItem.focus();
                break;
            }
            break;
        }
        default: {
            break;
        }
    }
}
const getItems = (parentId = props.rootParentId, searchTerm = null) => {
    emit('get-tree-items', parentId, searchTerm);
};
const searchItems = debounce(() => {
    emit('search-tree-items', currentTreeSearch.value);
}, 600);
const getTreeItems = (parentId) => {
    const treeItems = [];
    props.items.forEach((item) => {
        if (item.isDeleted) {
            return;
        }

        if (parentId === null && typeof props.items.find((i) => i.id === item.parentId) !== 'undefined') {
            return;
        }

        if (parentId !== null && item[props.parentProperty] !== parentId) {
            return;
        }

        const hasChildCountProperty = item.hasOwnProperty(props.childCountProperty);
        const childCount = hasChildCountProperty ? item[props.childCountProperty] : 0;

        const alreadyLoadedTreeItem = findById(item.id);
        const initialOpened =
            alreadyLoadedTreeItem?.initialOpened ?? (props.initiallyExpandedRoot && item.parentId === null);

        treeItems.push({
            data: item,
            id: item.id,
            schema: item.schema,
            parentId: parentId,
            childCount: childCount,
            children: getTreeItems(item.id),
            initialOpened,
            active: false,
            activeElementId: props.routeParamsActiveElementId,
            checked: alreadyLoadedTreeItem?.checked ?? !!props.checkItemsInitial,
            disabled: item.disabled,
            disabledToolTipText: item.disabledToolTipText,
            [props.afterIdProperty]: item[props.afterIdProperty],
        });
    });
    return sort.afterSort(treeItems, props.afterIdProperty);
};
const updateSorting = (items) => {
    let lastId = null;

    items.forEach((item) => {
        item.data[props.afterIdProperty] = lastId;
        lastId = item.id;
    });

    return items;
};
const startDrag = (draggedComponent) => {
    draggedComponent.opened = false;
    draggedItem.value = draggedComponent.item;
    emit('drag-start');
};
const endDrag = () => {
    if (!droppedItem.value) {
        draggedItem.value = null;
        return;
    }

    const oldParentId = draggedItem.value.data.parentId;
    const newParentId = droppedItem.value.data.parentId;

    if (oldParentId !== newParentId) {
        if (oldParentId !== null) {
            const draggedParent = findById(oldParentId);
            if (draggedParent) {
                draggedParent.childCount -= 1;
                draggedParent.data.childCount -= 1;
            }
        }

        if (newParentId !== null) {
            const droppedParent = findById(newParentId);
            if (droppedParent) {
                droppedParent.childCount += 1;
                droppedParent.data.childCount += 1;
            }
        }

        draggedItem.value.data.parentId = newParentId;
    }

    updateSorting(findTreeByParentId(oldParentId));

    if (oldParentId !== droppedItem.value.parentId) {
        updateSorting(findTreeByParentId(droppedItem.value.parentId));
    }

    const eventData = {
        draggedItem: draggedItem.value,
        droppedItem: droppedItem.value,
        oldParentId,
        newParentId,
    };

    draggedItem.value = null;
    droppedItem.value = null;
    isLoading.value = true;
    emit('drag-end', eventData);
};
const moveDrag = (draggedComponent, droppedComponent) => {
    if (!draggedComponent || !droppedComponent || draggedComponent.id === droppedComponent.id) {
        return;
    }

    const sourceTree = findTreeByParentId(draggedComponent.parentId);
    const targetTree = findTreeByParentId(droppedComponent.parentId);

    if (!sourceTree || !targetTree) {
        return;
    }

    const dragItemIndex = sourceTree.findIndex((item) => item.id === draggedComponent.id);
    const dropItemIndex = targetTree.findIndex((item) => item.id === droppedComponent.id);

    if (dragItemIndex < 0 || dropItemIndex < 0) {
        return;
    }

    const targetItem = targetTree[dropItemIndex];

    if (!props.bindItemsToFolder || draggedComponent.parentId === targetItem.parentId) {
        sourceTree.splice(dragItemIndex, 1);
        targetTree.splice(dropItemIndex, 0, draggedComponent);
        draggedComponent.parentId = targetItem.parentId;
    }

    droppedItem.value = targetItem;
};
function openTreeById(id = activeElementId.value) {
    const item = findById(id);
    if (item === null) {
        return;
    }
    if (activeElementId.value === item.id) {
        item.active = true;
    } else {
        item.initialOpened = true;
    }
    const activeElementParentId = item.parentId;
    if (item.parentId !== null) {
        openTreeById(activeElementParentId);
    }
}
function findTreeByParentId(parentId) {
    const queue = [
        {
            id: null,
            children: treeItems.value,
        },
    ];
    while (queue.length > 0) {
        const next = queue.shift();
        if (next.id === parentId) {
            return next.children;
        }
        if (next.children.length) {
            queue.push(...next.children);
        }
    }
    return null;
}
function findById(id) {
    const queue = [
        {
            id: null,
            children: treeItems.value,
        },
    ];
    while (queue.length > 0) {
        const next = queue.shift();
        if (next.id === id) {
            return next;
        }
        if (next.children.length) {
            queue.push(...next.children);
        }
    }
    return null;
}
const onCreateNewItem = (name) => {
    if (!name.length || name.length <= 0) {
        return;
    }

    const newElem = props.treeActions.createNewElement?.(null, null, name);

    if (!newElem) {
        return;
    }

    saveItems();

    const item = getNewTreeItem(newElem);

    addElement(item, 'after');
};
const addSubElement = (contextItemValue) => {
    if (!contextItemValue || !contextItemValue.data || !contextItemValue.data.id) {
        return;
    }
    if (contextItem.value === null) {
        contextItem.value = contextItemValue;
    }
    currentEditMode.value = addSubElement;
    void Promise.resolve(props.treeActions.getChildrenFromParent?.(contextItemValue.id)).then(() => {
        const parentElement = contextItemValue;
        const newElem = props.treeActions.createNewElement?.(contextItemValue, contextItemValue.id);

        if (!newElem) {
            return;
        }
        const newTreeItem = getNewTreeItem(newElem);

        parentElement.childCount += 1;
        parentElement.data.childCount += 1;
        newElementId.value = newElem.id;
        createdItem.value = newTreeItem;
    });
};
const duplicateElement = (contextItem) => {
    props.treeActions.duplicateElement?.(contextItem);
};
function addElement(contextItemValue, pos) {
    const newElem = props.treeActions.createNewElement?.(contextItemValue);
    if (!newElem) {
        return;
    }
    const newTreeItem = getNewTreeItem(newElem);
    if (contextItem.value === null) {
        contextItem.value = contextItemValue;
    }
    if (addElementPosition.value === null) {
        addElementPosition.value = pos;
    }
    if (!contextItemValue.hasOwnProperty('parentId')) {
        contextItemValue.parentId = null;
    }
    currentEditMode.value = addElement;
    const targetTree = findTreeByParentId(contextItemValue.parentId);
    const newItemIdx = treeItems.value.findIndex((i) => i.id === newTreeItem.id);
    const contextItemIdx = targetTree.findIndex((i) => i.id === contextItemValue.id);
    if (pos === 'before') {
        targetTree.splice(contextItemIdx, 1, newTreeItem, contextItemValue);
    } else {
        contextItem.value = newTreeItem;
        targetTree.splice(contextItemIdx, 1, contextItemValue, newTreeItem);
    }
    treeItems.value.splice(newItemIdx, 1);
    updateSorting(targetTree);
    newElementId.value = newElem.id;
    createdItem.value = newTreeItem;
}
function getNewTreeItem(elem) {
    const hasChildCountProperty = elem.hasOwnProperty(props.childCountProperty);
    const childCount = hasChildCountProperty ? elem[props.childCountProperty] : 0;
    const hasParentProperty = elem.hasOwnProperty('parentId');
    const parentId = hasParentProperty ? elem.parentId : null;
    return {
        data: elem,
        id: elem.id,
        parentId: parentId,
        childCount: childCount,
        children: 0,
        initialOpened: false,
        active: false,
    };
}
const deleteElement = (item) => {
    const targetTree = findTreeByParentId(item.parentId);
    const deletedItemIdx = targetTree.findIndex((i) => i.id === item.id);
    if (item.children.length > 0) {
        item.children.forEach((child) => {
            child.data.isDeleted = true;
        });
    }
    targetTree.splice(deletedItemIdx, 1);
    updateSorting(targetTree);
    emit('delete-element', item);
    saveItems();
};
const abortCreateElement = (item) => {
    if (eventFromEdit.value) {
        eventFromEdit.value = null;
        return;
    }

    if (currentEditMode.value !== null) {
        deleteElement(item);

        const parent = findById(item.parentId);
        if (parent.id === item.parentId && parent.data) {
            parent.childCount -= 1;
            parent.data.childCount -= 1;
        }
    }

    contextItem.value = null;
    newElementId.value = null;
    currentEditMode.value = null;
    addElementPosition.value = null;
    emit('editing-end', { parentId: item.parentId });
};
const onFinishNameingElement = (draft, event) => {
    if (createdItem.value) {
        createdItem.value.data.name = draft;

        createdItem.value.data.save().then(() => {
            createdItem.value = null;
            saveItems();
            if (currentEditMode.value !== null && contextItem.value) {
                currentEditMode.value(contextItem.value, addElementPosition.value);
            }
        });
    }
    eventFromEdit.value = event;
    newElementId.value = null;
};
const deleteSelectedElements = () => {
    if (checkedElements.value.length <= 0) {
        return;
    }

    const batchDeleteIsFunction = typeof attrs.onBatchDelete === 'function';

    if (batchDeleteIsFunction) {
        emit('batch-delete', checkedElements.value);
    } else {
        Object.values(checkedElements.value).forEach((itemId) => {
            const item = findById(itemId);
            if (item) {
                deleteElement(item);
            }
        });
    }

    checkedElements.value = {};
    checkedElementsCount.value = 0;
    checkedElementsChildCount.value = 0;
    emit('checked-elements-count', checkedElementsCount.value);
};
function checkItem(item) {
    if (item.checked) {
        if (item.childCount > 0) {
            checkedElementsChildCount.value += 1;
        }
        checkedElements.value[item.id] = item.id;
        checkedElementsCount.value += 1;
    } else {
        if (item.childCount > 0) {
            checkedElementsChildCount.value -= 1;
        }
        delete checkedElements.value[item.id];
        checkedElementsCount.value -= 1;
    }
    emit('checked-elements-count', checkedElementsCount.value);
}
function saveItems() {
    emit('save-tree-items');
}
const onDeleteElements = (item) => {
    toDeleteItem.value = item;
    showDeleteModal.value = true;
};
const onCloseDeleteModal = () => {
    showDeleteModal.value = false;
    toDeleteItem.value = null;
};
const onConfirmDelete = () => {
    if (toDeleteItem.value) {
        deleteElement(toDeleteItem.value);
    } else {
        deleteSelectedElements();
    }
    showDeleteModal.value = false;
    toDeleteItem.value = null;
};

watch(
    () => props.items,
    () => {
        treeItems.value = getTreeItems(isSearched.value ? null : props.rootParentId);
        eventFromEdit.value = null;
    },
    { deep: true, immediate: true },
);
watch(
    () => props.activeTreeItemId,
    (val) => {
        if (val && activeElementId.value) {
            openTreeById();
        }
    },
);

provide('getItems', getItems);
provide('startDrag', startDrag);
provide('endDrag', endDrag);
provide('moveDrag', moveDrag);
provide('addSubElement', addSubElement);
provide('addElement', addElement);
provide('duplicateElement', duplicateElement);
provide('onFinishNameingElement', onFinishNameingElement);
provide('onDeleteElements', onDeleteElements);
provide('abortCreateElement', abortCreateElement);

createdComponent();

onMounted(() => {
    mountedComponent();
});
onUnmounted(() => {
    emit('checked-elements-count', 0);
});
onBeforeUnmount(() => {
    beforeUnmountedComponent();
});

ctDefinePublic({
    treeItems,
    draggedItem,
    droppedItem,
    isLoading,
    currentTreeSearch,
    newElementId,
    contextItem,
    currentEditMode,
    addElementPosition,
    eventFromEdit,
    createdItem,
    checkedElements,
    checkedElementsCount,
    showDeleteModal,
    toDeleteItem,
    checkedElementsChildCount,
    activeElementId,
    isSortable,
    isSearched,
    hasActionSlot,
    hasNoItems,
    selectedItemsPathIds,
    checkedItemIds,
    createdComponent,
    mountedComponent,
    beforeUnmountedComponent,
    handleFocusIn,
    handleKeyDown,
    getItems,
    searchItems,
    getTreeItems,
    updateSorting,
    startDrag,
    endDrag,
    moveDrag,
    openTreeById,
    findTreeByParentId,
    findById,
    onCreateNewItem,
    addSubElement,
    duplicateElement,
    addElement,
    getNewTreeItem,
    deleteElement,
    abortCreateElement,
    onFinishNameingElement,
    deleteSelectedElements,
    checkItem,
    saveItems,
    onDeleteElements,
    onCloseDeleteModal,
    onConfirmDelete,
});

defineExpose({
    treeItems,
    draggedItem,
    droppedItem,
    isLoading,
    currentTreeSearch,
    newElementId,
    contextItem,
    currentEditMode,
    addElementPosition,
    eventFromEdit,
    createdItem,
    checkedElements,
    checkedElementsCount,
    showDeleteModal,
    toDeleteItem,
    checkedElementsChildCount,
    activeElementId,
    isSortable,
    isSearched,
    hasActionSlot,
    hasNoItems,
    selectedItemsPathIds,
    checkedItemIds,
    createdComponent,
    mountedComponent,
    beforeUnmountedComponent,
    handleFocusIn,
    handleKeyDown,
    getItems,
    searchItems,
    getTreeItems,
    updateSorting,
    startDrag,
    endDrag,
    moveDrag,
    openTreeById,
    findTreeByParentId,
    findById,
    onCreateNewItem,
    addSubElement,
    duplicateElement,
    addElement,
    getNewTreeItem,
    deleteElement,
    abortCreateElement,
    onFinishNameingElement,
    deleteSelectedElements,
    checkItem,
    saveItems,
    onDeleteElements,
    onCloseDeleteModal,
    onConfirmDelete,
});
</script>

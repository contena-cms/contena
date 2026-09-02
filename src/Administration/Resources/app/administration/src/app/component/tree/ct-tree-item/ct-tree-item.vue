<template>
    <ct-block name="ct_tree_item">
        <div
            ref="treeItemRoot"
            class="ct-tree-item"
            :class="styling"
            role="treeitem"
            :aria-label="getName(item)"
            :tabindex="active ? 0 : -1"
            :aria-current="active ? 'page' : undefined"
            :aria-expanded="isOpened ? 'true' : 'false'"
            :data-item-id="item.id"
            :aria-owns="item.id"
            :aria-selected="checked"
        >
            <ct-block name="ct_tree_item_element">
                <div
                    v-droppable="{ dragGroup: 'ct-tree-item', data: item }"
                    v-draggable="dragConf"
                    class="ct-tree-item__element"
                >
                    <ct-block name="ct_tree_item_element_leaf_icon">
                        <div v-if="item.childCount <= 0" class="ct-tree-item__leaf"></div>
                    </ct-block>

                    <ct-block name="ct_tree_item_element_toggle">
                        <template v-if="item.childCount <= 0"
                            ><!-- Keeps the conditional chain connected across ct-block. --></template
                        >
                        <div
                            v-else
                            class="ct-tree-item__toggle"
                            role="button"
                            tabindex="0"
                            :aria-label="translate('ct-tree-item.toggleTreeItem', { name: getName(item) })"
                            :aria-expanded="opened ? 'true' : 'false'"
                            @click="
                                openTreeItem();
                                getTreeItemChildren(item);
                            "
                            @keydown.enter="
                                openTreeItem();
                                getTreeItemChildren(item);
                            "
                        >
                            <ct-block name="ct_tree_item_element_toggle_icon">
                                <mt-icon
                                    size="24px"
                                    :name="opened ? 'regular-chevron-down-xxs' : 'regular-chevron-right-xxs'"
                                />
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="ct_tree_item_element_selection">
                        <div class="ct-tree-item__selection">
                            <mt-checkbox
                                v-if="displayCheckbox"
                                v-model:checked="checked"
                                :disabled="item.disabled || currentEditElement === item.data.id"
                                :partial="checkedGhost"
                                :aria-label="translate('ct-tree-item.toggleItem', { name: getName(item) })"
                                @update:model-value="toggleItemCheck($event, item)"
                            />
                        </div>
                    </ct-block>

                    <ct-block name="ct_tree_item_element_grip">
                        <slot name="grip">
                            <div v-if="item.childCount > 0" class="ct-tree-item__icon">
                                <mt-icon v-if="opened" name="regular-folder-open" size="16px" />

                                <mt-icon v-else name="regular-folder" size="16px" />
                            </div>

                            <div v-else class="ct-tree-item__icon">
                                <mt-icon name="regular-circle-xxs" size="8px" />
                            </div>
                        </slot>
                    </ct-block>

                    <ct-block name="ct_tree_item_element_content">
                        <div
                            ref="item"
                            v-tooltip="{
                                message: item.disabledToolTipText,
                                disabled: !item.disabledToolTipText,
                            }"
                            class="ct-tree-item__content"
                        >
                            <slot name="content" v-bind="{ item, openTreeItem, getName }">
                                <ct-block name="ct_tree_items_item_content_edit">
                                    <template v-if="currentEditElement === item.data.id">
                                        <ct-confirm-field
                                            v-model="editItemName"
                                            class="ct-tree-detail__edit-tree-item"
                                            :prevent-empty-submit="true"
                                            :placeholder="translate(`${translationContext}.general.buttonCreate`)"
                                            @input="onFinishNameingElement"
                                            @blur="onBlurTreeItemInput(item)"
                                            @submit-cancel="onCancelSubmit(item)"
                                        />
                                    </template>
                                </ct-block>

                                <ct-block name="ct_tree_items_item_content_default">
                                    <template v-if="currentEditElement === item.data.id"
                                        ><!-- Keeps the conditional chain connected across ct-block. --></template
                                    >
                                    <template v-else>
                                        <a
                                            v-if="onChangeRoute"
                                            class="tree-link"
                                            :href="showItemUrl(item)"
                                            @click.prevent="onChangeRoute(item)"
                                        >
                                            <span class="ct-tree-item__label">
                                                {{ getName(item) }}
                                            </span>
                                        </a>

                                        <span v-else class="ct-tree-item__label">
                                            {{ getName(item) }}
                                        </span>
                                    </template>
                                </ct-block>
                            </slot>
                        </div>
                    </ct-block>

                    <ct-block name="ct_tree_item_element_actions">
                        <div class="ct-tree-item__actions">
                            <ct-block name="ct_tree_items_active_state">
                                <mt-icon
                                    v-if="shouldShowActiveState"
                                    size="6px"
                                    :color="getActiveIconColor(item)"
                                    name="solid-circle-xxxs"
                                />
                            </ct-block>

                            <slot
                                name="actions"
                                :item="item"
                                :open-tree-item="openTreeItem"
                                :add-element="addElement"
                                :add-sub-element="addSubElement"
                                :on-duplicate="onDuplicate"
                                :on-change-route="onChangeRoute"
                                :delete-element="deleteElement"
                                :tool-tip="toolTip"
                                :is-disabled="isDisabled"
                            >
                                <ct-context-button
                                    v-tooltip="toolTip"
                                    class="ct-tree-item__context_button"
                                    :disabled="isDisabled || undefined"
                                >
                                    <ct-block name="ct_tree_items_actions_without_position">
                                        <ct-context-menu-item
                                            v-if="allowCreateWithoutPosition"
                                            class="ct-tree-item__without-position-action"
                                            @click="addElement(item)"
                                        >
                                            {{ translate(`${translationContext}.general.actions.withoutPosition`) }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="ct_tree_items_actions_before">
                                        <ct-context-menu-item
                                            v-if="!allowCreateWithoutPosition"
                                            :disabled="!allowNewCategories || undefined"
                                            class="ct-tree-item__before-action"
                                            @click="addElement(item, 'before')"
                                        >
                                            {{ translate(`${translationContext}.general.actions.createBefore`) }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="ct_tree_items_actions_after">
                                        <ct-context-menu-item
                                            v-if="!allowCreateWithoutPosition"
                                            :disabled="!allowNewCategories || undefined"
                                            class="ct-tree-item__after-action"
                                            @click="addElement(item, 'after')"
                                        >
                                            {{ translate(`${translationContext}.general.actions.createAfter`) }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="ct_tree_items_actions_sub">
                                        <ct-context-menu-item
                                            v-if="!allowCreateWithoutPosition"
                                            :disabled="!allowNewCategories || undefined"
                                            class="ct-tree-item__sub-action"
                                            @click="
                                                addSubElement(item);
                                                openTreeItem(true);
                                            "
                                        >
                                            {{ translate(`${translationContext}.general.actions.createSub`) }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="ct_tree_items_actions_duplicate">
                                        <ct-context-menu-item
                                            v-if="allowDuplicate"
                                            class="ct-context-menu__duplicate-action"
                                            @click="onDuplicate(item)"
                                        >
                                            {{ translate(`global.default.duplicate`) }}
                                        </ct-context-menu-item>
                                    </ct-block>

                                    <ct-block name="ct_tree_items_actions_group">
                                        <div class="ct-context-menu__group">
                                            <ct-block name="ct_tree_items_actions_edit">
                                                <ct-context-menu-item @click="onChangeRoute(item)">
                                                    {{ translate('global.default.edit') }}
                                                </ct-context-menu-item>
                                            </ct-block>

                                            <ct-block name="ct_tree_items_actions_delete">
                                                <ct-context-menu-item
                                                    class="ct-context-menu__group-button-delete"
                                                    :disabled="!allowDeleteCategories || undefined"
                                                    variant="danger"
                                                    @click="deleteElement(item)"
                                                >
                                                    {{ translate('global.default.delete') }}
                                                </ct-context-menu-item>
                                            </ct-block>
                                        </div>
                                    </ct-block>
                                </ct-context-button>
                            </slot>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <!-- ToDo: Repeat instead of duplicated Content -->
            <ct-block name="ct_tree_item_children_transition">
                <transition name="fade">
                    <template v-if="isOpened && item.children.length > 0">
                        <ct-block name="ct_tree_item_children_content">
                            <div
                                :id="item.id"
                                class="ct-tree-item__children"
                                role="group"
                                :aria-label="translate(`ct-tree-item.childrenLabel`, { name: getName(item) })"
                            >
                                <ct-block name="ct_tree_item_children_items">
                                    <ct-tree-item
                                        v-for="child in item.children"
                                        :key="child.id"
                                        :item="child"
                                        :dragged-item="draggedItem"
                                        :new-element-id="newElementId"
                                        :translation-context="translationContext"
                                        :on-change-route="onChangeRoute"
                                        :active-parent-ids="activeParentIds"
                                        :active-item-ids="activeItemIds"
                                        :mark-inactive="markInactive"
                                        :sortable="sortable"
                                        :should-focus="shouldFocus"
                                        :active-focus-id="activeFocusId"
                                        :display-checkbox="displayCheckbox"
                                        :disable-context-menu="disableContextMenu"
                                        :get-is-highlighted="getIsHighlighted"
                                        @check-item="emitCheckedItem"
                                    >
                                        <template #content="{ item, openTreeItem, getName: innerGetName }">
                                            <ct-block name="ct_tree_item_children_items_slot_content">
                                                <ct-vnode-renderer
                                                    v-if="contentSlot"
                                                    :node="
                                                        renderContentSlotNode({ item, openTreeItem, getName: innerGetName })
                                                    "
                                                />

                                                <ct-block name="ct_tree_item_children_items_slot_content_default_block">
                                                    <template v-if="contentSlot"
                                                        ><!-- Keeps the conditional chain connected across ct-block. --></template
                                                    >
                                                    <template v-else>
                                                        <ct-block name="ct_tree_item_children_items_slot_content_edit">
                                                            <template v-if="currentEditElement === item.data.id">
                                                                <ct-confirm-field
                                                                    v-model="item.data.name"
                                                                    class="ct-tree-detail__edit-tree-item"
                                                                    :prevent-empty-submit="true"
                                                                    :placeholder="
                                                                        translate(
                                                                            `${translationContext}.general.buttonCreate`,
                                                                        )
                                                                    "
                                                                    @input="onFinishNameingElement"
                                                                    @blur="onBlurTreeItemInput(item)"
                                                                    @submit-cancel="onCancelSubmit(item)"
                                                                />
                                                            </template>
                                                        </ct-block>

                                                        <ct-block name="ct_tree_item_children_items_slot_content_default">
                                                            <template v-if="currentEditElement === item.data.id"
                                                                ><!-- Keeps the conditional chain connected across ct-block. --></template
                                                            >
                                                            <template v-else>
                                                                <a
                                                                    v-if="onChangeRoute"
                                                                    class="tree-link"
                                                                    :href="showItemUrl(item)"
                                                                    @click.prevent="onChangeRoute(item)"
                                                                >
                                                                    <span class="ct-tree-item__label">
                                                                        {{ getName(item) }}
                                                                    </span>
                                                                </a>

                                                                <span v-else class="ct-tree-item__label">
                                                                    {{ getName(item) }}
                                                                </span>
                                                            </template>
                                                        </ct-block>
                                                    </template>
                                                </ct-block>
                                            </ct-block>
                                        </template>

                                        <template #actions="{ item, openTreeItem }">
                                            <ct-block name="ct_tree_item_children_items_slot_actions">
                                                <ct-block name="ct_tree_items_transition_active_state">
                                                    <mt-icon
                                                        v-if="shouldShowActiveState"
                                                        size="6px"
                                                        :color="getActiveIconColor(item)"
                                                        name="solid-circle-xxxs"
                                                    />
                                                </ct-block>

                                                <ct-vnode-renderer
                                                    v-if="actionsSlot"
                                                    :node="renderActionsSlotNode({ item, openTreeItem })"
                                                />
                                                <template v-else>
                                                    <ct-context-button v-tooltip="toolTip" :disabled="isDisabled">
                                                        <ct-block name="ct_tree_items_transition_actions_without_position">
                                                            <ct-context-menu-item
                                                                v-if="allowCreateWithoutPosition"
                                                                class="ct-tree-item__without-position-action"
                                                                @click="addElement(item)"
                                                            >
                                                                {{
                                                                    translate(
                                                                        `${translationContext}.general.actions.withoutPosition`,
                                                                    )
                                                                }}
                                                            </ct-context-menu-item>
                                                        </ct-block>

                                                        <ct-block name="ct_tree_items_transition_actions_before">
                                                            <ct-context-menu-item
                                                                v-if="!allowCreateWithoutPosition"
                                                                :disabled="!allowNewCategories || undefined"
                                                                class="ct-tree-item__before-action"
                                                                @click="addElement(item, 'before')"
                                                            >
                                                                {{
                                                                    translate(
                                                                        `${translationContext}.general.actions.createBefore`,
                                                                    )
                                                                }}
                                                            </ct-context-menu-item>
                                                        </ct-block>

                                                        <ct-block name="ct_tree_items_transition_actions_after">
                                                            <ct-context-menu-item
                                                                v-if="!allowCreateWithoutPosition"
                                                                :disabled="!allowNewCategories"
                                                                class="ct-tree-item__after-action"
                                                                @click="addElement(item, 'after')"
                                                            >
                                                                {{
                                                                    translate(
                                                                        `${translationContext}.general.actions.createAfter`,
                                                                    )
                                                                }}
                                                            </ct-context-menu-item>
                                                        </ct-block>

                                                        <ct-block name="ct_tree_items_transition_actions_sub">
                                                            <ct-context-menu-item
                                                                v-if="!allowCreateWithoutPosition"
                                                                :disabled="!allowNewCategories"
                                                                class="ct-tree-item__sub-action"
                                                                @click="
                                                                    addSubElement(item);
                                                                    openTreeItem(true);
                                                                "
                                                            >
                                                                {{
                                                                    translate(
                                                                        `${translationContext}.general.actions.createSub`,
                                                                    )
                                                                }}
                                                            </ct-context-menu-item>
                                                        </ct-block>

                                                        <ct-block name="ct_tree_items_transition_actions_duplicate">
                                                            <ct-context-menu-item
                                                                v-if="allowDuplicate"
                                                                class="ct-context-menu__duplicate-action"
                                                                @click="onDuplicate(item)"
                                                            >
                                                                {{ translate(`global.default.duplicate`) }}
                                                            </ct-context-menu-item>
                                                        </ct-block>

                                                        <ct-block name="ct_tree_items_transition_actions_group">
                                                            <div class="ct-context-menu__group">
                                                                <ct-block name="ct_tree_items_transition_actions_edit">
                                                                    <ct-context-menu-item @click="onChangeRoute(item)">
                                                                        {{ translate('global.default.edit') }}
                                                                    </ct-context-menu-item>
                                                                </ct-block>

                                                                <ct-block name="ct_tree_items_transition_actions_delete">
                                                                    <ct-context-menu-item
                                                                        class="ct-context-menu__group-button-delete"
                                                                        :disabled="!allowDeleteCategories || undefined"
                                                                        variant="danger"
                                                                        @click="deleteElement(item)"
                                                                    >
                                                                        {{ translate('global.default.delete') }}
                                                                    </ct-context-menu-item>
                                                                </ct-block>
                                                            </div>
                                                        </ct-block>
                                                    </ct-context-button>
                                                </template>
                                            </ct-block>
                                        </template>
                                    </ct-tree-item>
                                </ct-block>
                            </div>
                        </ct-block>
                    </template>
                    <template v-else-if="isLoading">
                        <div class="ct-tree-item__children">
                            <ct-skeleton variant="tree-item" />
                            <ct-skeleton variant="tree-item" />
                            <ct-skeleton variant="tree-item" />
                            <ct-skeleton variant="tree-item" />
                            <ct-skeleton variant="tree-item" />
                        </div>
                    </template>
                </transition>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-tree-item.scss';

const props = defineProps({
    item: {
        type: Object,
        required: true,
        default: () => {
            return {};
        },
    },

    draggedItem: {
        type: Object,
        required: false,
        default: () => {
            return null;
        },
    },

    newElementId: {
        type: String,
        required: false,
        default: () => {
            return null;
        },
    },

    translationContext: {
        type: String,
        default: () => {
            return 'ct-tree';
        },
    },

    onChangeRoute: {
        type: Function,
        default: () => {
            return null;
        },
    },

    disableContextMenu: {
        type: Boolean,
        default: () => {
            return false;
        },
    },

    contextMenuTooltipText: {
        type: String,
        required: false,
        default: () => {
            return null;
        },
    },

    activeParentIds: {
        type: Array,
        required: false,
        default: () => {
            return null;
        },
    },

    activeItemIds: {
        type: Array,
        required: false,
        default: () => {
            return null;
        },
    },

    sortable: {
        type: Boolean,
        required: false,
        default: () => {
            return true;
        },
    },

    markInactive: {
        type: Boolean,
        required: false,
        default: false,
    },

    shouldFocus: {
        type: Boolean,
        required: false,
        default: false,
    },

    shouldShowActiveState: {
        type: Boolean,
        required: false,
        default: false,
    },

    activeFocusId: {
        type: String,
        required: false,
        default: () => {
            return '';
        },
    },

    displayCheckbox: {
        type: Boolean,
        required: false,
        default: () => {
            return true;
        },
    },

    allowNewCategories: {
        type: Boolean,
        required: false,
        default: () => {
            return true;
        },
    },

    allowDeleteCategories: {
        type: Boolean,
        required: false,
        default: () => {
            return true;
        },
    },

    allowCreateWithoutPosition: {
        type: Boolean,
        required: false,
        default: () => {
            return false;
        },
    },

    allowDuplicate: {
        type: Boolean,
        required: false,
        default: () => {
            return false;
        },
    },

    getItemUrl: {
        type: Function,
        required: false,
        default: () => {
            return null;
        },
    },

    getIsHighlighted: {
        type: Function,
        required: false,
        default: () => {
            return false;
        },
    },
});
const emit = defineEmits(['check-item']);

import { ref, computed, inject, watch, nextTick, useSlots, onUpdated, onMounted, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';

const route = useRoute();
const slots = useSlots();
const { t } = useI18n();
const treeItemRoot = ref(null);

const translate = t;
const getItems = inject('getItems', null);
const treeStartDrag = inject('startDrag', null);
const treeEndDrag = inject('endDrag', null);
const treeMoveDrag = inject('moveDrag', null);
const treeAddSubElement = inject('addSubElement', null);
const treeAddElement = inject('addElement', null);
const treeDuplicateElement = inject('duplicateElement', null);
const treeOnFinishNameingElement = inject('onFinishNameingElement', null);
const treeOnDeleteElements = inject('onDeleteElements', null);
const treeAbortCreateElement = inject('abortCreateElement', null);

const opened = ref(props.item.initialOpened);
const active = ref(props.item.active);
const selected = ref(false);
const isLeaf = ref(false);
const isLoading = ref(false);
const dragEl = ref(null);
const dragStartX = ref(0);
const dragStartY = ref(0);
const mouseStartX = ref(0);
const mouseStartY = ref(0);
const rootParent = ref(null);
const checkedGhost = ref(false);
const currentEditElement = ref(null);
const editItemName = ref(props.item.data.name);

const checked = ref(props.item.checked);
const activeElementId = computed(() => {
    return route.params[props.item.activeElementId] || null;
});
const isOpened = computed(() => opened.value);
const isDragging = computed(() => {
    if (props.draggedItem === null) {
        return false;
    }
    return props.draggedItem.data.id === props.item.data.id;
});
const styling = computed(() => {
    return {
        'is--dragging': isDragging.value,
        'is--active': active.value,
        'is--opened': isOpened.value,
        'is--no-children': props.item.childCount <= 0,
        'is--marked-inactive': props.markInactive && !props.item.data.active,
        'is--focus': props.shouldFocus && props.activeFocusId === props.item.id,
        'is--no-checkbox': !props.displayCheckbox,
        'is--highlighted': isHighlighted.value,
        'is--disabled': props.item.disabled,
    };
});
const dragConf = computed(() => {
    return {
        delay: 300,
        validDragCls: null,
        dragGroup: 'ct-tree-item',
        data: props.item,
        onDragStart: dragStart,
        onDragEnter: onMouseEnter,
        onDrop: dragEnd,
        preventEvent: true,
        disabled: !props.sortable,
    };
});
const parentScope = computed(() => {
    return {
        addSubElement: treeAddSubElement,
        addElement: treeAddElement,
        duplicateElement: treeDuplicateElement,
        onFinishNameingElement: treeOnFinishNameingElement,
        onDeleteElements: treeOnDeleteElements,
        abortCreateElement: treeAbortCreateElement,
    };
});
const toolTip = computed(() => {
    if (props.contextMenuTooltipText !== null) {
        return {
            showDelay: 300,
            message: props.contextMenuTooltipText,
            disabled: !props.disableContextMenu,
        };
    }

    return {
        showDelay: 300,
        message: t(`${props.translationContext}.general.actions.actionsDisabledInLanguage`),
        disabled: !props.disableContextMenu,
    };
});
const isDisabled = computed(() => {
    return currentEditElement.value !== null || props.disableContextMenu;
});
const isHighlighted = computed(() => {
    return props.getIsHighlighted(props.item);
});
const contentSlot = computed(() => {
    return slots.content;
});
const actionsSlot = computed(() => {
    return slots.actions;
});

const updatedComponent = () => {
    if (props.item.children.length > 0 || props.item.childCount <= 0) {
        isLoading.value = false;
    }
};
const mountedComponent = () => {
    treeItemRoot.value?.addEventListener('keydown', handleKeyDown);

    if (props.item.active) {
        treeItemRoot.value?.querySelector('.ct-tree-item.is--active input')?.focus();
    }

    if (props.newElementId) {
        currentEditElement.value = props.newElementId;
        editElementName();
    }

    if (props.item.initialOpened) {
        openTreeItem(true);
        getTreeItemChildren(props.item);
    }

    updatedComponent();
};
const beforeUnmountComponent = () => {
    treeItemRoot.value?.removeEventListener('keydown', handleKeyDown);
};
function handleKeyDown(event) {
    // Check if the event is fired inside the tree item
    if (event.target !== treeItemRoot.value) {
        return;
    }
    switch (event.key) {
        case 'ArrowRight': {
            // When the tree item is already open, do nothing
            if (opened.value) {
                break;
            }

            // Open the tree item
            openTreeItem();
            getTreeItemChildren(props.item);
            event.stopPropagation();
            event.preventDefault();
            break;
        }
        case 'ArrowLeft': {
            // Check if the tree is open
            if (!opened.value) {
                break;
            }

            // Close the tree item
            openTreeItem(false);
            event.stopPropagation();
            event.preventDefault();
            break;
        }
        default: {
            break;
        }
    }
}
function openTreeItem(open = !opened.value) {
    if (isDragging.value) {
        return;
    }
    opened.value = open;
}
function getTreeItemChildren(treeItem) {
    if (isDragging.value || isLoading.value) {
        return;
    }
    if (treeItem.children.length <= 0) {
        isLoading.value = true;
        getItems(treeItem.data.id, treeItem.data.schema);
    }
}
const dragContext = {
    get item() {
        return props.item;
    },
    get opened() {
        return opened.value;
    },
    set opened(value) {
        opened.value = value;
    },
};
function dragStart(_config, _element, dragElement) {
    if (isDragging.value || isLoading.value) {
        return;
    }
    dragEl.value = dragElement;
    treeStartDrag(dragContext);
}
function dragEnd() {
    treeEndDrag();
}
function onMouseEnter(dragData, dropData) {
    if (!dropData) {
        return;
    }
    treeMoveDrag(dragData, dropData);
}
const startDrag = (draggedComponent) => {
    return treeStartDrag(draggedComponent);
};
const endDrag = () => {
    treeEndDrag();
};
const moveDrag = (draggedComponent, droppedComponent) => {
    return treeMoveDrag(draggedComponent, droppedComponent);
};
const emitCheckedItem = (item) => {
    emit('check-item', item);
};
const toggleItemCheck = (event, item) => {
    if (checkedGhost.value && !item.checked) {
        checked.value = true;
    } else {
        checked.value = event;
    }

    item.checked = checked.value;
    emit('check-item', item);
};
const addSubElement = (item) => {
    parentScope.value.addSubElement(item);
};
const addElement = (item, pos) => {
    parentScope.value.addElement(item, pos);
};
const duplicateElement = (contextItem) => {
    parentScope.value.duplicateElement(contextItem);
};
const onDuplicate = (item) => {
    duplicateElement(item);
    openTreeItem(true);
};
function editElementName() {
    void nextTick(() => {
        const elementNameField = treeItemRoot.value?.querySelector('.ct-tree-detail__edit-tree-item input');
        if (elementNameField) {
            elementNameField.focus();
        }
    });
}
const onFinishNameingElement = (draft, event) => {
    void nextTick(() => {
        parentScope.value.onFinishNameingElement(draft, event);
    });
};
const onBlurTreeItemInput = (item) => {
    abortCreateElement(item);
};
const onCancelSubmit = (item) => {
    abortCreateElement(item);
};
function abortCreateElement(item) {
    parentScope.value.abortCreateElement(item);
}
const deleteElement = (item) => {
    parentScope.value.onDeleteElements(item);
};
const getName = (item) => {
    if (item.data.translated) {
        return item.data.name || item.data.translated.name;
    }

    return item.data.name;
};
const getActiveIconColor = (item) => {
    if (item.data?.active) {
        return item.data.active === true ? '#37d046' : '#d1d9e0';
    }

    return '#d1d9e0';
};
const showItemUrl = (item) => {
    if (props.getItemUrl) {
        return props.getItemUrl(item);
    }

    return false;
};
const renderContentSlotNode = ({ item, openTreeItem, getName }) => {
    return slots.content({ item, openTreeItem, getName });
};
const renderActionsSlotNode = ({ item, openTreeItem }) => {
    return slots.actions({ item, openTreeItem });
};

watch(
    () => activeElementId.value,
    (newId) => {
        active.value = newId === props.item.id;
    },
);
watch(
    () => props.newElementId,
    (newId) => {
        currentEditElement.value = newId;
    },
);
watch(
    () => props.item.data.name,
    (name) => {
        editItemName.value = name;
    },
);
watch(
    () => props.activeParentIds,
    () => {
        if (props.activeParentIds) {
            checkedGhost.value = props.activeParentIds.indexOf(props.item.id) >= 0;
        }
    },
    { deep: true, immediate: true },
);
watch(
    () => props.activeItemIds,
    () => {
        if (props.activeItemIds) {
            checked.value = props.activeItemIds.indexOf(props.item.id) >= 0;
        }
    },
    { deep: true, immediate: true },
);

onUpdated(() => {
    updatedComponent();
});
onMounted(() => {
    mountedComponent();
});
onBeforeUnmount(() => {
    beforeUnmountComponent();
});

ctDefinePublic({
    getItems,
    treeStartDrag,
    treeEndDrag,
    treeMoveDrag,
    treeAddSubElement,
    treeAddElement,
    treeDuplicateElement,
    treeOnFinishNameingElement,
    treeOnDeleteElements,
    treeAbortCreateElement,
    opened,
    active,
    selected,
    isLeaf,
    isLoading,
    dragEl,
    dragStartX,
    dragStartY,
    mouseStartX,
    mouseStartY,
    rootParent,
    checkedGhost,
    currentEditElement,
    editItemName,
    checked,
    activeElementId,
    isOpened,
    isDragging,
    styling,
    dragConf,
    parentScope,
    toolTip,
    isDisabled,
    isHighlighted,
    contentSlot,
    actionsSlot,
    updatedComponent,
    mountedComponent,
    beforeUnmountComponent,
    handleKeyDown,
    openTreeItem,
    getTreeItemChildren,
    dragStart,
    dragEnd,
    onMouseEnter,
    startDrag,
    endDrag,
    moveDrag,
    emitCheckedItem,
    toggleItemCheck,
    addSubElement,
    addElement,
    duplicateElement,
    onDuplicate,
    editElementName,
    onFinishNameingElement,
    onBlurTreeItemInput,
    onCancelSubmit,
    abortCreateElement,
    deleteElement,
    getName,
    getActiveIconColor,
    showItemUrl,
    renderContentSlotNode,
    renderActionsSlotNode,
});

defineExpose({
    getItems,
    treeStartDrag,
    treeEndDrag,
    treeMoveDrag,
    treeAddSubElement,
    treeAddElement,
    treeDuplicateElement,
    treeOnFinishNameingElement,
    treeOnDeleteElements,
    treeAbortCreateElement,
    opened,
    active,
    selected,
    isLeaf,
    isLoading,
    dragEl,
    dragStartX,
    dragStartY,
    mouseStartX,
    mouseStartY,
    rootParent,
    checkedGhost,
    currentEditElement,
    editItemName,
    checked,
    activeElementId,
    isOpened,
    isDragging,
    styling,
    dragConf,
    parentScope,
    toolTip,
    isDisabled,
    isHighlighted,
    contentSlot,
    actionsSlot,
    updatedComponent,
    mountedComponent,
    beforeUnmountComponent,
    handleKeyDown,
    openTreeItem,
    getTreeItemChildren,
    dragStart,
    dragEnd,
    onMouseEnter,
    startDrag,
    endDrag,
    moveDrag,
    emitCheckedItem,
    toggleItemCheck,
    addSubElement,
    addElement,
    duplicateElement,
    onDuplicate,
    editElementName,
    onFinishNameingElement,
    onBlurTreeItemInput,
    onCancelSubmit,
    abortCreateElement,
    deleteElement,
    getName,
    getActiveIconColor,
    showItemUrl,
    renderContentSlotNode,
    renderActionsSlotNode,
});
</script>

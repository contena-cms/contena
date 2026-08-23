<template>
    <ct-block name="sw_experience_studio_sidebar_tree_node">
        <div class="ct-experience-studio-sidebar-tree-node">
            <ct-block name="sw_experience_studio_sidebar_tree_node_element">
                <div
                    v-draggable="dragConfig()"
                    v-droppable="dropConfigForElement()"
                    class="ct-experience-studio-sidebar-tree-node__element"
                    :class="{
                        'is--active': isSelected,
                        'is--expandable': hasSlots,
                    }"
                    role="button"
                    tabindex="0"
                    @click="onSelectElement"
                    @keydown.enter="onSelectElement"
                >
                    <ct-block name="sw_experience_studio_sidebar_tree_node_toggle">
                        <button
                            v-if="hasSlots"
                            class="ct-experience-studio-sidebar-tree-node__toggle"
                            type="button"
                            :aria-label="isExpanded ? $t('global.default.close') : $t('global.default.open')"
                            @click.stop="onToggleExpand"
                        >
                            <mt-icon
                                :name="isExpanded ? 'regular-chevron-down-xxs' : 'regular-chevron-right-xxs'"
                                size="12px"
                            />
                        </button>
                    </ct-block>

                    <ct-block name="sw_experience_studio_sidebar_tree_node_label">
                        <div class="ct-experience-studio-sidebar-tree-node__label">
                            <mt-icon
                                class="ct-experience-studio-sidebar-tree-node__type-icon"
                                :name="typeIcon"
                                size="12px"
                            />

                            <span class="ct-experience-studio-sidebar-tree-node__name">
                                {{ label }}
                            </span>
                        </div>
                    </ct-block>

                    <ct-block name="sw_experience_studio_sidebar_tree_node_actions">
                        <div class="ct-experience-studio-sidebar-tree-node__actions">
                            <button
                                v-tooltip="{
                                    message: $t('ct-experience-studio.detail.sidebarTree.duplicateElement'),
                                    disabled: allowEdit,
                                }"
                                class="ct-experience-studio-sidebar-tree-node__action"
                                type="button"
                                :disabled="!allowEdit || undefined"
                                :aria-label="$t('ct-experience-studio.detail.sidebarTree.duplicateElement')"
                                @click.stop="onDuplicateElement"
                            >
                                <mt-icon name="regular-duplicate" size="16px" />
                            </button>

                            <button
                                v-tooltip="{
                                    message: $t('ct-experience-studio.detail.sidebarTree.deleteElement'),
                                    disabled: allowEdit,
                                }"
                                class="ct-experience-studio-sidebar-tree-node__action"
                                type="button"
                                :disabled="!allowEdit || undefined"
                                :aria-label="$t('ct-experience-studio.detail.sidebarTree.deleteElement')"
                                @click.stop="onDeleteElement"
                            >
                                <mt-icon name="regular-trash" size="16px" />
                            </button>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_experience_studio_sidebar_tree_node_children">
                <div v-if="isExpanded && hasSlots" class="ct-experience-studio-sidebar-tree-node__children">
                    <template v-for="slot in slotEntries" :key="slot.name">
                        <div class="ct-experience-studio-sidebar-tree-node__slot">
                            <ct-block name="sw_experience_studio_sidebar_tree_node_slot_header">
                                <div class="ct-experience-studio-sidebar-tree-node__slot-header">
                                    <div class="ct-experience-studio-sidebar-tree-node__slot-label">
                                        <mt-icon
                                            class="ct-experience-studio-sidebar-tree-node__type-icon"
                                            name="regular-products-s"
                                            size="12px"
                                        />

                                        <span class="ct-experience-studio-sidebar-tree-node__slot-name">
                                            {{ slot.name }}
                                        </span>
                                    </div>

                                    <button
                                        v-tooltip="{
                                            message: $t('ct-experience-studio.detail.sidebarTree.addElement'),
                                            disabled: allowEdit,
                                        }"
                                        class="ct-experience-studio-sidebar-tree-node__action"
                                        type="button"
                                        :disabled="!allowEdit || undefined"
                                        :aria-label="$t('ct-experience-studio.detail.sidebarTree.addElement')"
                                        @click.stop="onAddElement(slot.name, $event)"
                                    >
                                        <mt-icon name="regular-plus-xs" size="12px" />
                                    </button>
                                </div>
                            </ct-block>

                            <ct-block name="sw_experience_studio_sidebar_tree_node_slot_elements">
                                <div class="ct-experience-studio-sidebar-tree-node__slot-elements">
                                    <ct-experience-studio-sidebar-tree-node
                                        v-for="(childElement, childIndex) in slot.elements"
                                        :key="childElement.id"
                                        :element="childElement"
                                        :selected-element-id="selectedElementId"
                                        :depth="depth + 1"
                                        :allow-drag-and-drop="allowDragAndDrop"
                                        :validate-move-target="validateMoveTarget"
                                        :parent-element-id="contentElement.id"
                                        :parent-slot-name="slot.name"
                                        :index-in-parent="childIndex"
                                        @select-element="$emit('select-element', $event)"
                                        @add-element="$emit('add-element', $event)"
                                        @duplicate-element="$emit('duplicate-element', $event)"
                                        @delete-element="$emit('delete-element', $event)"
                                        @move-element="$emit('move-element', $event)"
                                    />
                                </div>
                                <div
                                    v-droppable="dropConfigForSlot(slot.name)"
                                    class="ct-experience-studio-sidebar-tree-node__slot-drop-target"
                                ></div>
                            </ct-block>
                        </div>
                    </template>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import type { ContentElementNode } from '../../types/content-element.types';
import { getContentElementLabel } from '../../util/content-element-label.util';
import type { ExperienceStudioElementTypeStore } from '../../store/experience-studio-element-type.store';
import './ct-experience-studio-sidebar-tree-node.scss';
type MoveElementPayload = {
    elementId: string;
    newParentElementId: string | null;
    newSlotName: string | null;
    newIndex: number | null;
};

const DRAG_GROUP = 'experience-studio-sidebar-tree';

defineOptions({ name: 'SwExperienceStudioSidebarTreeNode' });

const props = defineProps({
    element: {
        type: Object,
        required: true,
    },
    selectedElementId: {
        type: String,
        required: false,
        default: null,
    },
    depth: {
        type: Number,
        required: false,
        default: 0,
    },
    allowDragAndDrop: {
        type: Boolean,
        required: false,
        default: false,
    },
    validateMoveTarget: {
        type: Function,
        required: false,
        default: null,
    },
    parentElementId: {
        type: String,
        required: false,
        default: null,
    },
    parentSlotName: {
        type: String,
        required: false,
        default: null,
    },
    indexInParent: {
        type: Number,
        required: false,
        default: null,
    },
});
const emit = defineEmits([
    'select-element',
    'add-element',
    'duplicate-element',
    'delete-element',
    'move-element',
]);

import { ref, computed, inject } from 'vue';

const acl = inject('acl');

const isExpanded = ref(true);

const contentElement = computed(() => {
    return props.element as ContentElementNode;
});
const elementTypeStore = computed(() => {
    return Contena.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
});
const label = computed(() => {
    return getContentElementLabel(contentElement.value);
});
const typeIcon = computed(() => {
    const configuredIcon = elementTypeStore.value.getByName(contentElement.value.component)?.icon;

    return configuredIcon && configuredIcon.length > 0 ? configuredIcon : 'bars-square-s';
});
const slotEntries = computed(() => {
    const slots = contentElement.value.slots ?? {};
    const definedSlots = elementTypeStore.value.getByName(contentElement.value.component)?.slots ?? [];
    const slotNames = Array.from(
        new Set([
            ...definedSlots.map((slot) => slot.name),
            ...Object.keys(slots),
        ]),
    );

    return slotNames.map((name) => ({
        name,
        elements: Array.isArray(slots[name]) ? slots[name] : [],
    }));
});
const hasSlots = computed(() => {
    return slotEntries.value.length > 0;
});
const isSelected = computed(() => {
    return props.selectedElementId === contentElement.value.id;
});
const allowEdit = computed(() => {
    return acl.can('experience_studio.editor');
});

const onSelectElement = () => {
    emit('select-element', contentElement.value.id);
};
const onToggleExpand = () => {
    if (!hasSlots.value) {
        return;
    }

    isExpanded.value = !isExpanded.value;
};
const onAddElement = (slotName: string, event: MouseEvent) => {
    const trigger = event.currentTarget as HTMLElement | null;
    const bounds = trigger?.getBoundingClientRect();

    emit('add-element', {
        parentElementId: contentElement.value.id,
        slotName,
        anchorTop: bounds?.top ?? 0,
        anchorLeft: bounds ? bounds.right : 0,
    });
};
const onDuplicateElement = () => {
    emit('duplicate-element', contentElement.value.id);
};
const onDeleteElement = () => {
    emit('delete-element', contentElement.value.id);
};
const collectSubtreeIds = (element: ContentElementNode) => {
    const nestedSlotElements = Object.values(element.slots ?? {}).flatMap((slotElements) => slotElements);
    const nestedIds = nestedSlotElements.flatMap((childElement) => collectSubtreeIds(childElement));

    return [
        element.id,
        ...nestedIds,
    ];
};
const dragConfig = () => {
    return {
        dragGroup: DRAG_GROUP,
        disabled: !props.allowDragAndDrop,
        data: {
            elementId: contentElement.value.id,
            elementComponent: contentElement.value.component,
            subtreeIds: collectSubtreeIds(contentElement.value),
        },
        onDrop: onDropElement,
    };
};
const dropConfigForSlot = (slotName: string) => {
    return {
        dragGroup: DRAG_GROUP,
        data: {
            newParentElementId: contentElement.value.id,
            newSlotName: slotName,
            newIndex: null,
        },
        validateDrop: validateMoveDrop,
    };
};
const dropConfigForElement = () => {
    return {
        dragGroup: DRAG_GROUP,
        data: {
            newParentElementId: props.parentElementId,
            newSlotName: props.parentSlotName,
            newIndex: props.indexInParent,
        },
        validateDrop: validateMoveDrop,
    };
};
const validateMoveDrop = (
    dragData: { elementId: string; subtreeIds: string[] } | null,
    dropData: Omit<MoveElementPayload, 'elementId'> | null,
) => {
    if (!props.allowDragAndDrop || !dragData || !dropData) {
        return false;
    }

    if (dropData.newParentElementId && dragData.subtreeIds.includes(dropData.newParentElementId)) {
        return false;
    }

    if (typeof props.validateMoveTarget === 'function') {
        return props.validateMoveTarget({
            elementId: dragData.elementId,
            newParentElementId: dropData.newParentElementId,
            newSlotName: dropData.newSlotName,
            newIndex: dropData.newIndex,
        });
    }

    return true;
};
const onDropElement = (dragData: { elementId: string } | null, dropData: Omit<MoveElementPayload, 'elementId'> | null) => {
    if (!dragData || !dropData) {
        return;
    }

    emit('move-element', {
        elementId: dragData.elementId,
        newParentElementId: dropData.newParentElementId,
        newSlotName: dropData.newSlotName,
        newIndex: dropData.newIndex,
    });
};

swDefinePublic({
    acl,
    isExpanded,
    contentElement,
    elementTypeStore,
    label,
    typeIcon,
    slotEntries,
    hasSlots,
    isSelected,
    allowEdit,
    onSelectElement,
    onToggleExpand,
    onAddElement,
    onDuplicateElement,
    onDeleteElement,
    collectSubtreeIds,
    dragConfig,
    dropConfigForSlot,
    dropConfigForElement,
    validateMoveDrop,
    onDropElement,
});

defineExpose({
    acl,
    isExpanded,
    contentElement,
    elementTypeStore,
    label,
    typeIcon,
    slotEntries,
    hasSlots,
    isSelected,
    allowEdit,
    onSelectElement,
    onToggleExpand,
    onAddElement,
    onDuplicateElement,
    onDeleteElement,
    collectSubtreeIds,
    dragConfig,
    dropConfigForSlot,
    dropConfigForElement,
    validateMoveDrop,
    onDropElement,
});
</script>

<template>
    <ct-block name="ct_experience_studio_sidebar_tree">
        <div class="ct-experience-studio-sidebar-tree">
            <ct-block name="ct_experience_studio_sidebar_tree_header">
                <div class="ct-experience-studio-sidebar-tree__header">
                    <h3>{{ $t('ct-experience-studio.detail.sidebarTree.title') }}</h3>

                    <button
                        v-tooltip="{
                            message: $t('ct-experience-studio.detail.sidebarTree.addElement'),
                            disabled: allowEdit,
                        }"
                        class="ct-experience-studio-sidebar-tree__add-button"
                        type="button"
                        :disabled="!allowEdit || undefined"
                        :aria-label="$t('ct-experience-studio.detail.sidebarTree.addElement')"
                        @click="onAddRootElement($event)"
                    >
                        <mt-icon name="regular-plus-xs" size="12px" />
                    </button>
                </div>
            </ct-block>

            <ct-block name="ct_experience_studio_sidebar_tree_content">
                <div v-droppable="rootDropConfig()" class="ct-experience-studio-sidebar-tree__content">
                    <template v-if="hasElements">
                        <ct-block name="ct_experience_studio_sidebar_tree_nodes">
                            <ct-experience-studio-sidebar-tree-node
                                v-for="(element, elementIndex) in layoutElements"
                                :key="element.id"
                                :element="element"
                                :selected-element-id="selectedElementId"
                                :allow-drag-and-drop="allowEdit"
                                :validate-move-target="validateMoveTarget"
                                :parent-element-id="null"
                                :parent-slot-name="null"
                                :index-in-parent="elementIndex"
                                @select-element="onSelectElement"
                                @add-element="onAddElement"
                                @duplicate-element="onDuplicateElement"
                                @delete-element="onDeleteElement"
                                @move-element="onMoveElement"
                            />
                        </ct-block>
                    </template>

                    <mt-empty-state
                        v-else
                        icon="regular-sitemap"
                        :description="$t('ct-experience-studio.detail.sidebarTree.emptyState')"
                    />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { castContentElementNodes } from '../../util/content-element-label.util';
import './ct-experience-studio-sidebar-tree.scss';
interface AddElementPayload {
    parentElementId: string | null;
    slotName: string | null;
    anchorTop: number;
    anchorLeft: number;
}
interface MoveElementPayload {
    elementId: string;
    newParentElementId: string | null;
    newSlotName: string | null;
    newIndex: number | null;
}

const DRAG_GROUP = 'experience-studio-sidebar-tree';

const props = defineProps({
    layout: {
        type: Object,
        required: false,
        default: null,
    },
    selectedElementId: {
        type: String,
        required: false,
        default: null,
    },
    validateMoveTarget: {
        type: Function,
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

import { computed, inject } from 'vue';

const acl = inject('acl');

const layoutElements = computed(() => {
    const layout = props.layout as Entity<'content_layout'> | null;

    return castContentElementNodes(layout?.layout);
});
const hasElements = computed(() => {
    return layoutElements.value.length > 0;
});
const allowEdit = computed(() => {
    return acl.can('experience_studio.editor');
});

const onSelectElement = (elementId: string) => {
    emit('select-element', elementId);
};
const onAddElement = (payload: AddElementPayload) => {
    emit('add-element', payload);
};
const onAddRootElement = (event: MouseEvent) => {
    const trigger = event.currentTarget as HTMLElement | null;
    const bounds = trigger?.getBoundingClientRect();

    emit('add-element', {
        parentElementId: null,
        slotName: null,
        anchorTop: bounds?.top ?? 0,
        anchorLeft: bounds ? bounds.right : 0,
    });
};
const onDuplicateElement = (elementId: string) => {
    emit('duplicate-element', elementId);
};
const onDeleteElement = (elementId: string) => {
    emit('delete-element', elementId);
};
const onMoveElement = (payload: MoveElementPayload) => {
    emit('move-element', payload);
};
const validateMoveDrop = (
    dragData: { elementId: string } | null,
    dropData: Omit<MoveElementPayload, 'elementId'> | null,
) => {
    if (!allowEdit.value || !dragData || !dropData) {
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
const rootDropConfig = () => {
    return {
        dragGroup: DRAG_GROUP,
        data: {
            newParentElementId: null,
            newSlotName: null,
            newIndex: null,
        },
        validateDrop: validateMoveDrop,
        onDrop: onRootDrop,
    };
};
const onRootDrop = (dragData: { elementId: string } | null, dropData: Omit<MoveElementPayload, 'elementId'> | null) => {
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

ctDefinePublic({
    acl,
    layoutElements,
    hasElements,
    allowEdit,
    onSelectElement,
    onAddElement,
    onAddRootElement,
    onDuplicateElement,
    onDeleteElement,
    onMoveElement,
    validateMoveDrop,
    rootDropConfig,
    onRootDrop,
});

defineExpose({
    acl,
    layoutElements,
    hasElements,
    allowEdit,
    onSelectElement,
    onAddElement,
    onAddRootElement,
    onDuplicateElement,
    onDeleteElement,
    onMoveElement,
    validateMoveDrop,
    rootDropConfig,
    onRootDrop,
});
</script>

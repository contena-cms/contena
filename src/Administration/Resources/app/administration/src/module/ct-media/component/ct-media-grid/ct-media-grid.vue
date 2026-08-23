<template>
    <ct-block name="sw_media_grid">
        <div ref="componentRef" class="ct-media-grid">
            <slot name="content">
                <ct-block name="sw_media_grid_slot_content">
                    <div class="ct-media-grid__content" :class="presentationClass">
                        <slot>
                            <ct-block name="sw_media_grid_default_slot"></ct-block>
                        </slot>
                    </div>
                </ct-block>
            </slot>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-media-grid.scss';

const props = defineProps({
    presentation: {
        required: false,
        type: String,
        default: 'medium-preview',
        validator(value) {
            return [
                'small-preview',
                'medium-preview',
                'large-preview',
                'list-preview',
            ].includes(value);
        },
    },
});
const emit = defineEmits(['media-grid-selection-clear']);

import { ref, computed, onBeforeUnmount } from 'vue';

const componentRef = ref(null);

const presentationClass = computed(() => {
    return `ct-media-grid__presentation--${props.presentation}`;
});
const nonDeselectingComponents = computed(() => {
    return [
        'ct-media-sidebar',
        'ct-context-menu',
        'ct-media-index__load-more',
        'ct-media-index__options-container',
        'ct-modal',
        'mt-modal',
        'mt-modal-root__backdrop',
    ];
});

const createdComponent = () => {
    window.addEventListener('click', clearSelectionOnClickOutside, false);
};
const beforeDestroyComponent = () => {
    window.removeEventListener('click', clearSelectionOnClickOutside, false);
};
const clearSelectionOnClickOutside = (event) => {
    if (!isEmittedFromChildren(event.target) && !originatesFromExcludedComponent(event)) {
        emitSelectionCleared(event);
    }
};
const originatesFromExcludedComponent = (event) => {
    const eventPathClasses = event.composedPath().reduce((classes, eventParent) => {
        return eventParent.classList ? classes.concat(Array.from(eventParent.classList)) : classes;
    }, []);

    return nonDeselectingComponents.value.some((cssClass) => {
        return eventPathClasses.includes(cssClass);
    });
};
const isEmittedFromChildren = (target) => {
    return componentRef.value?.contains(target) ?? false;
};
const emitSelectionCleared = (originalDomEvent) => {
    emit('media-grid-selection-clear', {
        originalDomEvent,
    });
};

createdComponent();

onBeforeUnmount(() => {
    beforeDestroyComponent();
});

swDefinePublic({
    presentationClass,
    nonDeselectingComponents,
    createdComponent,
    beforeDestroyComponent,
    clearSelectionOnClickOutside,
    originatesFromExcludedComponent,
    isEmittedFromChildren,
    emitSelectionCleared,
});

defineExpose({
    presentationClass,
    nonDeselectingComponents,
    createdComponent,
    beforeDestroyComponent,
    clearSelectionOnClickOutside,
    originatesFromExcludedComponent,
    isEmittedFromChildren,
    emitSelectionCleared,
});
</script>

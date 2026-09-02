<template>
    <ct-block name="ct_experience_studio_preview_node">
        <article class="ct-experience-studio-preview-node">
            <header class="ct-experience-studio-preview-node__header">
                <h4 class="ct-experience-studio-preview-node__title">
                    {{ element.component }}
                </h4>
            </header>

            <dl v-if="primitiveProperties.length > 0" class="ct-experience-studio-preview-node__properties">
                <template v-for="property in primitiveProperties" :key="`${element.id}-${property.key}`">
                    <dt class="ct-experience-studio-preview-node__property-key">{{ property.key }}</dt>
                    <dd class="ct-experience-studio-preview-node__property-value">{{ property.value }}</dd>
                </template>
            </dl>

            <div
                v-for="slot in slotEntries"
                :key="`${element.id}-${slot.name}`"
                class="ct-experience-studio-preview-node__slot"
            >
                <div class="ct-experience-studio-preview-node__slot-name">{{ slot.name }}</div>

                <div v-if="slot.elements.length > 0" class="ct-experience-studio-preview-node__slot-elements">
                    <ct-experience-studio-preview-node
                        v-for="slotElement in slot.elements"
                        :key="slotElement.id"
                        :element="slotElement"
                    />
                </div>

                <p v-else class="ct-experience-studio-preview-node__slot-empty">
                    {{ $t('ct-experience-studio.detail.preview.emptySlot') }}
                </p>
            </div>
        </article>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-experience-studio-preview-node.scss';
type PreviewElement = {
    id: string;
    component: string;
    properties?: Record<string, unknown>;
    slots?: Record<string, PreviewElement[]>;
};
type PreviewPrimitive = string | number | boolean | null;

const props = defineProps({
    element: {
        type: Object,
        required: true,
    },
});

import { computed } from 'vue';

const primitiveProperties = computed(() => {
    const element = props.element as PreviewElement;
    const properties = element.properties ?? {};

    return Object.entries(properties)
        .filter(
            ([
                ,
                value,
            ]): value is PreviewPrimitive => {
                return isPreviewPrimitive(value);
            },
        )
        .map(
            ([
                key,
                value,
            ]) => ({
                key,
                value: formatPrimitiveValue(value),
            }),
        );
});
const slotEntries = computed(() => {
    const element = props.element as PreviewElement;
    const slots = element.slots ?? {};

    return Object.entries(slots).map(
        ([
            name,
            elements,
        ]) => ({
            name,
            elements: Array.isArray(elements) ? elements : [],
        }),
    );
});

const isPreviewPrimitive = (value: unknown) => {
    return value === null || typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean';
};
const formatPrimitiveValue = (value: PreviewPrimitive) => {
    if (value === null) {
        return 'null';
    }

    if (typeof value === 'string') {
        return value;
    }

    if (typeof value === 'number') {
        return value.toString();
    }

    return value ? 'true' : 'false';
};

ctDefinePublic({
    primitiveProperties,
    slotEntries,
    isPreviewPrimitive,
    formatPrimitiveValue,
});

defineExpose({
    primitiveProperties,
    slotEntries,
    isPreviewPrimitive,
    formatPrimitiveValue,
});
</script>

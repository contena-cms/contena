<template>
    <ct-block name="mt_text_editor_original">
        <MtTextEditorOriginal
            ref="mtTextEditorOriginal"
            v-bind="$attrs"
            v-model="compatValue"
            :custom-buttons="mergedCustomButtons"
            :excluded-buttons="mergedExcludedButtons"
        >
            <!-- Special buttons -->
            <template #button_link="{ editor, disabled, button }">
                <ct-text-editor-toolbar-button-link :editor="editor" :disabled="disabled" :button="button" />
            </template>

            <!-- Dynamically pass all slots -->
            <template v-for="(_, name) in getSlots()" #[name]="bindings">
                <slot :name="name" v-bind="bindings"></slot>
            </template>
        </MtTextEditorOriginal>
    </ct-block>
</template>

<script setup lang="ts">
import MtTextEditorOriginal from '@contena/meteor-component-library/dist/esm/MtTextEditor';
import type { CustomButton } from '@contena/meteor-component-library/dist/esm/MtTextEditorToolbar';
import './mt-text-editor.scss';

const props = defineProps({
    modelValue: {
        type: String,
        required: false,
        default: '',
    },

    /**
     * Custom buttons to be added to the toolbar
     */
    customButtons: {
        type: Array as PropType<CustomButton[]>,
        default: () => [],
    },

    /**
     * Excluded buttons from the toolbar
     */
    excludedButtons: {
        type: Array as PropType<string[]>,
        default: () => [],
    },
});
const emit = defineEmits(['update:modelValue']);

import { type PropType, ref, computed, useSlots } from 'vue';

const slots = useSlots();

const mtTextEditorOriginal = ref(null);

const compatValue = computed({
    get: () => {
        return props.modelValue;
    },
    set: (value) => {
        emit('update:modelValue', value);
    },
});
const mergedCustomButtons = computed(() => {
    const editorButtons: CustomButton[] = [];

    return [
        ...editorButtons,
        ...props.customButtons,
    ];
});
const mergedExcludedButtons = computed(() => {
    const excludedEditorButtons: string[] = [];

    return [
        ...excludedEditorButtons,
        ...props.excludedButtons,
    ];
});

const getSlots = () => {
    return slots;
};
const onUpdateModelValue = (value: string) => {
    emit('update:modelValue', value);
};
const validate = () => {
    const original = mtTextEditorOriginal.value as { validate?: () => Promise<boolean> } | undefined;

    if (!original?.validate) {
        return Promise.resolve(true);
    }

    return original.validate();
};

swDefinePublic({
    compatValue,
    mergedCustomButtons,
    mergedExcludedButtons,
    getSlots,
    onUpdateModelValue,
    validate,
});

defineExpose({
    compatValue,
    mergedCustomButtons,
    mergedExcludedButtons,
    getSlots,
    onUpdateModelValue,
    validate,
});
</script>

<template>
    <ct-block name="ct_grid_row">
        <div ref="ctGridRow" class="ct-grid-row" role="row" tabindex="0" @dblclick="onInlineEditStart($event.currentTarget)">
            <ct-block name="ct_grid_row_actions">
                <div class="ct-grid-row__actions">
                    <slot name="actions">
                        <ct-block name="ct_grid_row_slot_actions">
                            <mt-button size="small" variant="secondary" @click="onInlineEditCancel(id, index)">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                            <mt-button
                                class="ct-grid-row__inline-edit-action"
                                variant="primary"
                                size="small"
                                @click="onInlineEditFinish"
                            >
                                {{ $t('global.default.save') }}
                            </mt-button>
                        </ct-block>
                    </slot>
                </div>
            </ct-block>

            <slot>
                <ct-block name="ct_grid_row_slot_default"></ct-block>
            </slot>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-grid-row.scss';
const utils = Contena.Utils;

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },

    index: {
        type: Number,
        required: false,
        default: null,
    },

    allowInlineEdit: {
        type: Boolean,
        required: false,
        default: true,
    },
});
const emit = defineEmits(['inline-edit-finish']);

import { ref, inject, watch, onBeforeUnmount, getCurrentInstance } from 'vue';

const ctGridRow = ref(null);

const instance = getCurrentInstance();
const device = instance?.proxy?.$device;
const ctGridInlineEditStart = inject('ctGridInlineEditStart', null);
const ctGridInlineEditCancel = inject('ctGridInlineEditCancel', null);
const ctOnInlineEditStart = inject('ctOnInlineEditStart', null);
const ctRegisterGridDisableInlineEditListener = inject('ctRegisterGridDisableInlineEditListener', null);
const ctUnregisterGridDisableInlineEditListener = inject('ctUnregisterGridDisableInlineEditListener', null);
const ctGridSetColumns = inject('ctGridSetColumns', null);
const ctGridColumns = inject('ctGridColumns', null);

const isEditingActive = ref(false);
const inlineEditingCls = ref('is--inline-editing');
const id = ref(utils.createId());

const createdComponent = () => {
    ctRegisterGridDisableInlineEditListener(onInlineEditCancel);
};
const onInlineEditStart = () => {
    if (!props.allowInlineEdit || (device?.getViewportWidth() ?? Number.POSITIVE_INFINITY) < 960) {
        return;
    }

    const isInlineEditingConfigured = ctGridColumns.some((column) => column.editable);

    if (isEditingActive.value || !isInlineEditingConfigured) {
        return;
    }

    isEditingActive.value = true;
    ctGridInlineEditStart(id.value);
    ctOnInlineEditStart(props.item);
};
const startInlineEditing = () => {
    onInlineEditStart();
};
function onInlineEditCancel(idValue, index) {
    if (idValue && idValue !== id.value) {
        return;
    }
    isEditingActive.value = false;
    ctGridInlineEditCancel(props.item, index);
}
const onInlineEditFinish = () => {
    isEditingActive.value = false;
    emit('inline-edit-finish', props.item);
};

watch(
    () => isEditingActive.value,
    () => {
        if (isEditingActive.value) {
            ctGridRow.value.classList.add(inlineEditingCls.value);
            return;
        }

        ctGridRow.value.classList.remove(inlineEditingCls.value);
    },
);

createdComponent();

onBeforeUnmount(() => {
    ctUnregisterGridDisableInlineEditListener(onInlineEditCancel);
});

ctDefinePublic({
    ctGridInlineEditStart,
    ctGridInlineEditCancel,
    ctOnInlineEditStart,
    ctRegisterGridDisableInlineEditListener,
    ctUnregisterGridDisableInlineEditListener,
    ctGridSetColumns,
    ctGridColumns,
    isEditingActive,
    inlineEditingCls,
    id,
    createdComponent,
    onInlineEditStart,
    startInlineEditing,
    onInlineEditCancel,
    onInlineEditFinish,
});

defineExpose({
    ctGridInlineEditStart,
    ctGridInlineEditCancel,
    ctOnInlineEditStart,
    ctRegisterGridDisableInlineEditListener,
    ctUnregisterGridDisableInlineEditListener,
    ctGridSetColumns,
    ctGridColumns,
    isEditingActive,
    inlineEditingCls,
    id,
    createdComponent,
    onInlineEditStart,
    startInlineEditing,
    onInlineEditCancel,
    onInlineEditFinish,
});
</script>

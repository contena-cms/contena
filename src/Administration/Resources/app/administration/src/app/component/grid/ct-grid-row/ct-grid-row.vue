<template>
    <ct-block name="sw_grid_row">
        <div ref="swGridRow" class="ct-grid-row" role="row" tabindex="0" @dblclick="onInlineEditStart($event.currentTarget)">
            <ct-block name="sw_grid_row_actions">
                <div class="ct-grid-row__actions">
                    <slot name="actions">
                        <ct-block name="sw_grid_row_slot_actions">
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
                <ct-block name="sw_grid_row_slot_default"></ct-block>
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

const swGridRow = ref(null);

const instance = getCurrentInstance();
const device = instance?.proxy?.$device;
const swGridInlineEditStart = inject('swGridInlineEditStart', null);
const swGridInlineEditCancel = inject('swGridInlineEditCancel', null);
const swOnInlineEditStart = inject('swOnInlineEditStart', null);
const swRegisterGridDisableInlineEditListener = inject('swRegisterGridDisableInlineEditListener', null);
const swUnregisterGridDisableInlineEditListener = inject('swUnregisterGridDisableInlineEditListener', null);
const swGridSetColumns = inject('swGridSetColumns', null);
const swGridColumns = inject('swGridColumns', null);

const isEditingActive = ref(false);
const inlineEditingCls = ref('is--inline-editing');
const id = ref(utils.createId());

const createdComponent = () => {
    swRegisterGridDisableInlineEditListener(onInlineEditCancel);
};
const onInlineEditStart = () => {
    if (!props.allowInlineEdit || (device?.getViewportWidth() ?? Number.POSITIVE_INFINITY) < 960) {
        return;
    }

    const isInlineEditingConfigured = swGridColumns.some((column) => column.editable);

    if (isEditingActive.value || !isInlineEditingConfigured) {
        return;
    }

    isEditingActive.value = true;
    swGridInlineEditStart(id.value);
    swOnInlineEditStart(props.item);
};
const startInlineEditing = () => {
    onInlineEditStart();
};
function onInlineEditCancel(idValue, index) {
    if (idValue && idValue !== id.value) {
        return;
    }
    isEditingActive.value = false;
    swGridInlineEditCancel(props.item, index);
}
const onInlineEditFinish = () => {
    isEditingActive.value = false;
    emit('inline-edit-finish', props.item);
};

watch(
    () => isEditingActive.value,
    () => {
        if (isEditingActive.value) {
            swGridRow.value.classList.add(inlineEditingCls.value);
            return;
        }

        swGridRow.value.classList.remove(inlineEditingCls.value);
    },
);

createdComponent();

onBeforeUnmount(() => {
    swUnregisterGridDisableInlineEditListener(onInlineEditCancel);
});

swDefinePublic({
    swGridInlineEditStart,
    swGridInlineEditCancel,
    swOnInlineEditStart,
    swRegisterGridDisableInlineEditListener,
    swUnregisterGridDisableInlineEditListener,
    swGridSetColumns,
    swGridColumns,
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
    swGridInlineEditStart,
    swGridInlineEditCancel,
    swOnInlineEditStart,
    swRegisterGridDisableInlineEditListener,
    swUnregisterGridDisableInlineEditListener,
    swGridSetColumns,
    swGridColumns,
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

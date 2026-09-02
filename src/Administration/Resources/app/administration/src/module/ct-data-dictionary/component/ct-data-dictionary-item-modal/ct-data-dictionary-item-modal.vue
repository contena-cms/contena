<template>
    <ct-block name="ct_data_dictionary_item_modal">
        <ct-modal
            class="ct-data-dictionary-item-modal"
            :title="translate('ct-data-dictionary.detail.editorTitle')"
            variant="small"
            @modal-close="onCancel"
        >
            <ct-block name="ct_data_dictionary_item_modal_content">
                <ct-block name="ct_data_dictionary_item_modal_parent">
                    <mt-text-field
                        :model-value="parentLabel"
                        :label="translate('ct-data-dictionary.detail.itemParent')"
                        disabled
                    />
                </ct-block>

                <ct-container columns="1fr 1fr" gap="0 16px">
                    <ct-block name="ct_data_dictionary_item_modal_code">
                        <mt-text-field
                            v-model="item.code"
                            :label="translate('ct-data-dictionary.detail.itemCode')"
                            :disabled="!canEdit || undefined"
                            required
                        />
                    </ct-block>

                    <ct-block name="ct_data_dictionary_item_modal_label">
                        <mt-text-field
                            v-model="item.label"
                            :label="translate('ct-data-dictionary.detail.itemLabel')"
                            :disabled="!canEdit || undefined"
                            required
                        />
                    </ct-block>
                </ct-container>

                <ct-block name="ct_data_dictionary_item_modal_description">
                    <mt-textarea
                        v-model="item.description"
                        :label="translate('ct-data-dictionary.detail.itemDescription')"
                        :disabled="!canEdit || undefined"
                    />
                </ct-block>

                <ct-container columns="1fr 1fr" gap="0 16px">
                    <ct-block name="ct_data_dictionary_item_modal_position">
                        <mt-number-field
                            v-model="item.position"
                            :label="translate('ct-data-dictionary.detail.itemPosition')"
                            number-type="int"
                            :disabled="!canEdit || undefined"
                        />
                    </ct-block>

                    <ct-block name="ct_data_dictionary_item_modal_active">
                        <mt-switch
                            v-model="item.active"
                            :label="translate('ct-data-dictionary.detail.itemActive')"
                            :disabled="!canEdit || undefined"
                        />
                    </ct-block>
                </ct-container>
            </ct-block>

            <template #modal-footer>
                <ct-block name="ct_data_dictionary_item_modal_footer">
                    <ct-block name="ct_data_dictionary_item_modal_footer_cancel">
                        <mt-button size="small" variant="secondary" @click="onCancel">
                            {{ translate('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="ct_data_dictionary_item_modal_footer_actions">
                        <mt-button v-if="canCreate" size="small" variant="secondary" @click="onAddChild">
                            {{ translate('ct-data-dictionary.detail.addChildItem') }}
                        </mt-button>
                        <mt-button v-if="canDelete" size="small" variant="critical" @click="onDelete">
                            {{ translate('global.default.delete') }}
                        </mt-button>
                        <mt-button
                            variant="primary"
                            size="small"
                            :disabled="!canEdit || !item.code?.trim() || !item.label?.trim() || undefined"
                            @click="onSave"
                        >
                            {{ translate('global.default.save') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type DictionaryItem = Entity<'data_dictionary_item'>;

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    parentLabel: {
        type: String,
        required: true,
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
    canCreate: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits([
    'modal-close',
    'save-item',
    'add-child',
    'delete-item',
]);
const { t } = useI18n();
const translate = t;
const item = computed(() => props.item as DictionaryItem);
const parentLabel = computed(() => props.parentLabel);
const canEdit = computed(() => props.canEdit);
const canCreate = computed(() => props.canCreate);
const canDelete = computed(() => props.canDelete);

const onCancel = (): void => emit('modal-close');
const onSave = (): void => {
    emit('save-item', item.value);
    emit('modal-close');
};
const onAddChild = (): void => emit('add-child', item.value);
const onDelete = (): void => emit('delete-item', item.value);

ctDefinePublic({
    onCancel,
    onSave,
    onAddChild,
    onDelete,
});

defineExpose({
    item,
    parentLabel,
    canEdit,
    canCreate,
    canDelete,
    onCancel,
    onSave,
    onAddChild,
    onDelete,
});
</script>

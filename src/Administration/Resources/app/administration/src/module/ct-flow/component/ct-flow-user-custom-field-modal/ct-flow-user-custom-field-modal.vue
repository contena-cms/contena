<template>
    <ct-block name="sw_flow_user_custom_field_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal :title="$t('ct-flow.customField.title')" width="s">
                <ct-block name="sw_flow_user_custom_field_modal_content">
                    <div class="ct-flow-user-custom-field-modal__content">
                        <mt-entity-select
                            v-model="customFieldId"
                            entity="custom_field"
                            label-property="name"
                            required
                            :criteria="customFieldCriteria"
                            :label="$t('ct-flow.customField.field')"
                        />
                        <mt-select v-model="option" :label="$t('ct-flow.customField.operation')" :options="options" />
                        <mt-textarea
                            v-if="option !== 'clear'"
                            v-model="customFieldValue"
                            :label="$t('ct-flow.customField.value')"
                            :help-text="$t('ct-flow.customField.valueHelp')"
                        />
                    </div>
                </ct-block>
                <template #footer>
                    <ct-block name="sw_flow_user_custom_field_modal_footer">
                        <div class="ct-flow-modal__footer-actions">
                            <mt-button variant="secondary" @click="onCancel">{{ $t('global.default.cancel') }}</mt-button>
                            <mt-button variant="primary" :disabled="!canSave || undefined" @click="onSave">
                                {{ $t('global.default.save') }}
                            </mt-button>
                        </div>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';

const { Criteria } = Contena.Data;
const props = defineProps({ config: { type: Object as PropType<Record<string, unknown>>, required: true } });
const emit = defineEmits<{ save: [config: Record<string, unknown>]; cancel: [] }>();
const { t } = useI18n();

const customFieldId = ref(typeof props.config.customFieldId === 'string' ? props.config.customFieldId : null);
const configuredValue = props.config.customFieldValue;
const formatConfiguredValue = (value: unknown): string => {
    if (value === undefined || value === null) {
        return '';
    }

    if (typeof value === 'string') {
        return value;
    }

    return JSON.stringify(value, null, 2) ?? '';
};
const customFieldValue = ref(formatConfiguredValue(configuredValue));
const option = ref(typeof props.config.option === 'string' ? props.config.option : 'upsert');
const options = computed(() => [
    { label: t('ct-flow.customField.overwrite'), value: 'upsert' },
    { label: t('ct-flow.customField.create'), value: 'create' },
    { label: t('ct-flow.customField.clear'), value: 'clear' },
    { label: t('ct-flow.customField.add'), value: 'add' },
    { label: t('ct-flow.customField.remove'), value: 'remove' },
]);
const customFieldCriteria = computed(() => {
    const criteria = new Criteria(1, 25);
    criteria.addFilter(Criteria.equals('customFieldSet.relations.entityName', 'user'));
    return criteria;
});
const canSave = computed(() => Boolean(customFieldId.value && (option.value === 'clear' || customFieldValue.value)));
const onSave = (): void => {
    if (!customFieldId.value) return;
    let value: unknown = null;
    if (option.value !== 'clear') {
        try {
            value = JSON.parse(customFieldValue.value);
        } catch {
            value = customFieldValue.value;
        }
    }
    emit('save', {
        customFieldId: customFieldId.value,
        customFieldValue: value,
        option: option.value,
    });
};
const onCancel = (): void => emit('cancel');
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) onCancel();
};

swDefinePublic({
    customFieldId,
    customFieldValue,
    option,
    options,
    customFieldCriteria,
    canSave,
    onSave,
    onCancel,
    onModalChange,
});

defineExpose({
    customFieldId,
    customFieldValue,
    option,
    options,
    customFieldCriteria,
    canSave,
    onSave,
    onCancel,
    onModalChange,
});
</script>

<style scoped>
.ct-flow-user-custom-field-modal__content {
    display: grid;
    gap: var(--scale-size-16, 16px);
}
</style>

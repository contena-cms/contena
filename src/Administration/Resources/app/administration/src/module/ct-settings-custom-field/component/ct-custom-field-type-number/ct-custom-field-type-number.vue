<template>
    <ct-block name="sw_custom_field_type_base">
        <div class="ct-custom-field-type-base">
            <ct-block name="sw_custom_field_type_base_content">
                <ct-block name="sw_custom_field_type_base_labels">
                    <ct-custom-field-translated-labels
                        v-model:config="currentCustomField.config"
                        :disabled="!acl.can('custom_field.editor')"
                        :property-names="propertyNames"
                        :locales="locales"
                    />
                </ct-block>

                <ct-block name="sw_custom_field_type_number_container">
                    <div class="ct-custom-field-type-number__container">
                        <ct-block name="sw_custom_field_type_number_container_numbertype">
                            <mt-select
                                v-model="currentCustomField.config.numberType"
                                :label="$t('ct-settings-custom-field.customField.detail.labelNumberType')"
                                :options="numberTypes"
                            />
                        </ct-block>

                        <ct-block name="sw_custom_field_type_number_container_step">
                            <mt-number-field
                                v-model="currentCustomField.config.step"
                                :label="$t('ct-settings-custom-field.customField.detail.labelStep')"
                                :number-type="currentCustomField.config.numberType"
                            />
                        </ct-block>

                        <ct-block name="sw_custom_field_type_number_container_min">
                            <mt-number-field
                                v-model="currentCustomField.config.min"
                                :label="$t('ct-settings-custom-field.customField.detail.labelMin')"
                                :number-type="currentCustomField.config.numberType"
                            />
                        </ct-block>

                        <ct-block name="sw_custom_field_type_number_container_max">
                            <mt-number-field
                                v-model="currentCustomField.config.max"
                                :label="$t('ct-settings-custom-field.customField.detail.labelMax')"
                                :number-type="currentCustomField.config.numberType"
                            />
                        </ct-block>
                    </div>
                </ct-block>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref, toRef, watch, type PropType } from 'vue';

import { useCustomFieldType, type CustomField, type CustomFieldSet } from '../composables/use-custom-field-type';

const props = defineProps({
    currentCustomField: {
        type: Object as PropType<CustomField>,
        required: true,
    },
    set: {
        type: Object as PropType<CustomFieldSet>,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});
const currentCustomField = toRef(props, 'currentCustomField');

const { acl, locales, propertyNames, translate } = useCustomFieldType(props, [
    [
        'label',
        'ct-settings-custom-field.customField.detail.labelLabel',
    ],
    [
        'placeholder',
        'ct-settings-custom-field.customField.detail.labelPlaceholder',
    ],
    [
        'helpText',
        'ct-settings-custom-field.customField.detail.labelHelpText',
    ],
]);
const numberTypes = ref([
    {
        value: 'int',
        label: translate('ct-settings-custom-field.customField.detail.labelInt'),
    },
    {
        value: 'float',
        label: translate('ct-settings-custom-field.customField.detail.labelFloat'),
    },
]);
const isIntField = computed(() => currentCustomField.value.config.numberType === 'int');

if (!currentCustomField.value.config.numberType) {
    currentCustomField.value.config.numberType = 'int';
}

watch(
    () => currentCustomField.value.config.numberType,
    (value) => {
        currentCustomField.value.type = value;

        if (value !== 'int') {
            return;
        }

        const { step, min, max } = currentCustomField.value.config;

        if (step !== null && step !== undefined) {
            const roundedStep = Math.round(step);
            currentCustomField.value.config.step = roundedStep >= 1 ? roundedStep : 1;
        }

        if (min !== null && min !== undefined) {
            currentCustomField.value.config.min = Math.round(min);
        }

        if (max !== null && max !== undefined) {
            currentCustomField.value.config.max = Math.round(max);
        }
    },
);

swDefinePublic({
    acl,
    locales,
    propertyNames,
    numberTypes,
    isIntField,
});

defineExpose({
    acl,
    locales,
    propertyNames,
    numberTypes,
    isIntField,
});
</script>

<style scoped>
.ct-custom-field-type-number__container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 20px;
}
</style>

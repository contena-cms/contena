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

                <ct-block name="sw_custom_field_type_date_container">
                    <div class="ct-custom-field-type-date__container">
                        <ct-block name="sw_custom_field_type_date_container_type">
                            <mt-select
                                v-model="currentCustomField.config.dateType"
                                :label="$t('ct-settings-custom-field.customField.detail.labelDateType')"
                                :options="types"
                            />
                        </ct-block>

                        <ct-block name="sw_custom_field_type_date_container_time_format">
                            <mt-select
                                v-if="['time', 'datetime'].includes(currentCustomField.config.dateType ?? '')"
                                v-model="currentCustomField.config.config.time_24hr"
                                :label="$t('ct-settings-custom-field.customField.detail.labelTimeForm')"
                                :options="timeForms"
                            />
                        </ct-block>
                    </div>
                </ct-block>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { ref, toRef, type PropType } from 'vue';

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
        'helpText',
        'ct-settings-custom-field.customField.detail.labelHelpText',
    ],
]);
const types = ref([
    {
        value: 'datetime',
        label: translate('ct-settings-custom-field.customField.detail.labelDatetime'),
    },
    {
        value: 'date',
        label: translate('ct-settings-custom-field.customField.detail.labelDate'),
    },
    {
        value: 'time',
        label: translate('ct-settings-custom-field.customField.detail.labelTime'),
    },
]);
const timeForms = ref([
    {
        value: 'true',
        label: translate('global.default.yes'),
    },
    {
        value: 'false',
        label: translate('global.default.no'),
    },
]);

if (currentCustomField.value.config.dateType === undefined) {
    currentCustomField.value.config.dateType = 'datetime';
}

if (currentCustomField.value.config.config === undefined) {
    currentCustomField.value.config.config = {
        time_24hr: true,
    };
}

swDefinePublic({
    acl,
    locales,
    propertyNames,
    types,
    timeForms,
});

defineExpose({
    acl,
    locales,
    propertyNames,
    types,
    timeForms,
});
</script>

<style scoped>
.ct-custom-field-type-date__container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
</style>

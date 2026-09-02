<template>
    <ct-block name="ct_custom_field_type_base">
        <div class="ct-custom-field-type-base">
            <ct-block name="ct_custom_field_type_base_content">
                <ct-block name="ct_custom_field_type_base_labels">
                    <ct-custom-field-translated-labels
                        v-model:config="currentCustomField.config"
                        :disabled="!acl.can('custom_field.editor')"
                        :property-names="propertyNames"
                        :locales="locales"
                    />
                </ct-block>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { toRef, type PropType } from 'vue';
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

const { acl, propertyNames, locales } = useCustomFieldType(props, [
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

ctDefinePublic({
    acl,
    propertyNames,
    locales,
});

defineExpose({
    acl,
    propertyNames,
    locales,
});
</script>

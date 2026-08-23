<template>
    <ct-block name="sw_custom_field_type_base">
        <div class="ct-custom-field-type-base">
            <ct-block name="sw_custom_field_type_base_content">
                <ct-block name="sw_custom_field_type_select_options" />
                <ct-block name="sw_custom_field_type_select_add_option" />
                <ct-block name="sw_custom_field_type_select_multi" />

                <ct-block name="sw_custom_field_type_base_labels">
                    <ct-custom-field-translated-labels
                        v-model:config="currentCustomField.config"
                        :disabled="!acl.can('custom_field.editor')"
                        :property-names="propertyNames"
                        :locales="locales"
                    />
                </ct-block>

                <ct-block name="sw_custom_field_type_entity_type">
                    <mt-select
                        v-model="currentCustomField.config.entity"
                        :disabled="!currentCustomField._isNew || undefined"
                        :help-text="$t('ct-settings-custom-field.customField.detail.helpTextEntitySelect')"
                        :label="$t('ct-settings-custom-field.customField.detail.labelEntityTypeSelect')"
                        :options="sortedEntityTypes"
                        @update:model-value="onChangeEntityType"
                    />
                </ct-block>

                <ct-block name="sw_custom_field_type_entity_multi">
                    <mt-switch
                        v-model="multiSelectSwitch"
                        class="ct-custom-field-detail__switch"
                        :disabled="multiSelectSwitchDisabled || undefined"
                        :label="$t('ct-settings-custom-field.customField.detail.labelMultiSelect')"
                        @update:model-value="onChangeMultiSelectSwitch"
                    />
                </ct-block>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref, toRef, type PropType } from 'vue';

import { useCustomFieldType, type CustomField, type CustomFieldSet } from '../composables/use-custom-field-type';

interface EntityType {
    label: string;
    value: string;
    config?: {
        labelProperty: string[];
    };
}

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
const entityTypes = computed<EntityType[]>(() => [
    {
        label: translate('ct-settings-custom-field.customField.entity.country'),
        value: 'country',
    },
    {
        label: translate('ct-settings-custom-field.customField.entity.region'),
        value: 'region',
    },
    {
        label: translate('ct-settings-custom-field.customField.entity.media'),
        value: 'media',
    },
    {
        label: translate('ct-settings-custom-field.customField.entity.tag'),
        value: 'tag',
    },
    {
        label: translate('ct-settings-custom-field.customField.entity.user'),
        value: 'user',
        config: {
            labelProperty: ['name'],
        },
    },
]);
const sortedEntityTypes = computed(() =>
    [...entityTypes.value].sort((first, second) => first.label.localeCompare(second.label)),
);
const multiSelectSwitchDisabled = ref(!currentCustomField.value._isNew);
const multiSelectSwitch = ref(currentCustomField.value.config.enableMultiSelection === true);

function createdComponent(): void {
    delete currentCustomField.value.config.options;
    currentCustomField.value.config.componentName = 'mt-entity-select';
}

function onChangeEntityType(entity: string): void {
    const entityType = entityTypes.value.find((type) => type.value === entity);

    delete currentCustomField.value.config.labelProperty;

    if (entityType?.config?.labelProperty) {
        currentCustomField.value.config.labelProperty = entityType.config.labelProperty;
    }
}

function onChangeMultiSelectSwitch(state: boolean): void {
    currentCustomField.value.config.componentName = 'mt-entity-select';
    currentCustomField.value.config.enableMultiSelection = state;
}

createdComponent();

swDefinePublic({
    acl,
    locales,
    propertyNames,
    entityTypes,
    sortedEntityTypes,
    multiSelectSwitch,
    multiSelectSwitchDisabled,
    onChangeEntityType,
    onChangeMultiSelectSwitch,
});

defineExpose({
    acl,
    locales,
    propertyNames,
    entityTypes,
    sortedEntityTypes,
    multiSelectSwitch,
    multiSelectSwitchDisabled,
    onChangeEntityType,
    onChangeMultiSelectSwitch,
});
</script>

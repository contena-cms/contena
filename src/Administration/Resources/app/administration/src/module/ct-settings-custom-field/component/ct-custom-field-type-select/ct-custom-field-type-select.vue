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

                <ct-block name="ct_custom_field_type_select_multi">
                    <mt-switch
                        v-model="multiSelectSwitch"
                        class="ct-custom-field-detail__switch"
                        :disabled="multiSelectSwitchDisabled || undefined"
                        :label="$t('ct-settings-custom-field.customField.detail.labelMultiSelect')"
                        @update:model-value="onChangeMultiSelectSwitch"
                    />
                </ct-block>

                <ct-block name="ct_custom_field_type_select_options">
                    <div v-for="(option, index) in currentCustomField.config.options" :key="index">
                        <ct-block name="ct_custom_field_type_select_options_label">
                            <span>
                                {{ $t('ct-settings-custom-field.customField.detail.labelOption', { count: index + 1 }, 0) }}
                            </span>
                        </ct-block>

                        <ct-block name="ct_custom_field_type_select_options_delete">
                            <mt-button
                                class="ct-custom-field-type-select__delete-option-button"
                                size="small"
                                variant="secondary"
                                @click="onDeleteOption(index)"
                            >
                                {{ $t('global.default.delete') }}
                            </mt-button>
                        </ct-block>

                        <ct-block name="ct_custom_field_type_select_options_container">
                            <div class="ct-custom-field-type-select__option-container">
                                <ct-block name="ct_custom_field_type_select_options_container_technical_name">
                                    <mt-text-field
                                        v-model="option.value"
                                        :label="$t('ct-settings-custom-field.customField.detail.labelTechnicalName')"
                                    />
                                </ct-block>

                                <ct-block name="ct_custom_field_type_select_options_container_labels">
                                    <div>
                                        <mt-text-field
                                            v-for="locale in locales"
                                            :key="locale"
                                            v-model="option.label[locale]"
                                            class="ct-custom-field-type-select__option-label"
                                            :label="getLabel(locale)"
                                        />
                                    </div>
                                </ct-block>
                            </div>
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="ct_custom_field_type_select_add_option">
                    <mt-button
                        v-if="isOptionAddable"
                        size="small"
                        class="ct-custom-field-type-select__button-add"
                        variant="secondary"
                        @click="onClickAddOption"
                    >
                        {{ $t('ct-settings-custom-field.customField.detail.buttonAddOption') }}
                    </mt-button>
                </ct-block>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref, toRef, type PropType } from 'vue';

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
const multiSelectSwitch = ref(false);
const multiSelectSwitchDisabled = ref(false);
const isOptionAddable = computed(() => Array.isArray(currentCustomField.value.config.options));

function addOption(): void {
    currentCustomField.value.config.options?.push({
        value: '',
        label: {},
    });
}

function createdComponent(): void {
    if (!Array.isArray(currentCustomField.value.config.options)) {
        currentCustomField.value.config.options = [];

        addOption();
        addOption();
    }

    currentCustomField.value.config.componentName = 'mt-select';
    multiSelectSwitch.value = currentCustomField.value.config.enableMultiSelection === true;

    currentCustomField.value.config.options = currentCustomField.value.config.options.map((option) => {
        if (Array.isArray(option.label)) {
            option.label = {};
        }

        return option;
    });
}

function onClickAddOption(): void {
    addOption();
}

function getLabel(locale: string): string {
    const snippet = translate('ct-settings-custom-field.customField.detail.labelLabel');
    const language = translate(`locale.${locale}`);

    return `${snippet} (${language})`;
}

function onDeleteOption(index: number): void {
    currentCustomField.value.config.options?.splice(index, 1);
}

function onChangeMultiSelectSwitch(state: boolean): void {
    currentCustomField.value.config.componentName = 'mt-select';
    currentCustomField.value.config.enableMultiSelection = state;
}

createdComponent();

ctDefinePublic({
    acl,
    locales,
    propertyNames,
    multiSelectSwitch,
    multiSelectSwitchDisabled,
    isOptionAddable,
    addOption,
    onClickAddOption,
    getLabel,
    onDeleteOption,
    onChangeMultiSelectSwitch,
});

defineExpose({
    acl,
    locales,
    propertyNames,
    multiSelectSwitch,
    multiSelectSwitchDisabled,
    isOptionAddable,
    addOption,
    onClickAddOption,
    getLabel,
    onDeleteOption,
    onChangeMultiSelectSwitch,
});
</script>

<style src="./ct-custom-field-type-select.scss" lang="scss"></style>

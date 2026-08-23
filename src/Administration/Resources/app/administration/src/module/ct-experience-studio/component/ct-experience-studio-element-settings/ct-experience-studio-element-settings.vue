<template>
    <ct-block name="sw_experience_studio_element_settings">
        <div class="ct-experience-studio-element-settings">
            <div class="ct-experience-studio-element-settings__content">
                <mt-empty-state
                    v-if="!hasSelectedElement"
                    icon="regular-sliders-v"
                    :description="$t('ct-experience-studio.detail.elementSettings.emptyState')"
                />

                <mt-empty-state
                    v-else-if="isLoadingSettings"
                    icon="regular-hourglass"
                    :description="$t('ct-experience-studio.detail.elementSettings.loading')"
                />

                <mt-empty-state
                    v-else-if="hasTypeLoadError"
                    icon="regular-exclamation-triangle"
                    :description="$t('ct-experience-studio.detail.elementSettings.typeLoadError')"
                />

                <mt-empty-state
                    v-else-if="!hasSelectedElementType"
                    icon="regular-puzzle-piece"
                    :description="$t('ct-experience-studio.detail.elementSettings.typeMissing')"
                />

                <div v-else :key="selectedElementId" class="ct-experience-studio-element-settings__tabs-area">
                    <mt-tabs
                        class="ct-experience-studio-element-settings__tabs"
                        position-identifier="ct-experience-studio-element-settings"
                        :items="settingsTabItems"
                        default-item="element"
                        @new-item-active="onSettingsTabChange"
                    />

                    <div v-if="activeSettingsTab === 'element'" class="ct-experience-studio-element-settings__tab-panel">
                        <mt-empty-state
                            v-if="showElementEmptyState"
                            icon="regular-sliders-v"
                            :description="$t('ct-experience-studio.detail.elementSettings.noEditableProperties')"
                        />

                        <ct-experience-studio-settings-fields
                            v-else
                            :fields="elementFields"
                            :values="elementPropertyValues"
                            :allow-edit="allowEdit"
                            :selected-element-type="selectedElementType"
                            :is-inline-editing-active="isInlineEditingActive"
                            :show-inline-text-hints="true"
                            show-panels
                            @update-field="onUpdateElementField"
                        />
                    </div>

                    <div v-else-if="activeSettingsTab === 'layout'" class="ct-experience-studio-element-settings__tab-panel">
                        <mt-empty-state
                            v-if="hasStyleOptionLoadError"
                            icon="regular-exclamation-triangle"
                            :description="$t('ct-experience-studio.detail.elementSettings.styleLoadError')"
                        />

                        <mt-empty-state
                            v-else-if="showLayoutEmptyState"
                            icon="regular-sliders-v"
                            :description="$t('ct-experience-studio.detail.elementSettings.noEditableStyleOptions')"
                        />

                        <ct-experience-studio-settings-fields
                            v-else
                            :fields="layoutFields"
                            :values="elementStyleValues"
                            :allow-edit="allowEdit"
                            @update-field="onUpdateLayoutField"
                        />
                    </div>
                </div>
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import type { ContentElementNode } from '../../types/content-element.types';
import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import {
    getElementPropertyStorageKey,
    getInitialPropertyValue,
    getPropertyControlType,
    isPropertyVisible,
} from '../../util/element-settings.util';
import { getEditableStyleFields } from '../../util/style-settings.util';
import './ct-experience-studio-element-settings.scss';

const props = defineProps({
    layout: {
        type: Object,
        required: false,
        default: null,
    },
    selectedElementId: {
        type: String,
        required: false,
        default: null,
    },
    selectedElement: {
        type: Object,
        required: false,
        default: null,
    },
    selectedElementType: {
        type: Object,
        required: false,
        default: null,
    },
    styleOptions: {
        type: Object,
        required: false,
        default: () => ({}),
    },
    isLoadingTypes: {
        type: Boolean,
        required: false,
        default: false,
    },
    isLoadingStyleOptions: {
        type: Boolean,
        required: false,
        default: false,
    },
    typeLoadError: {
        type: String,
        required: false,
        default: null,
    },
    styleOptionLoadError: {
        type: String,
        required: false,
        default: null,
    },
    allowEdit: {
        type: Boolean,
        required: false,
        default: false,
    },
    isInlineEditingActive: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'update-properties',
    'update-style',
]);

import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const $t = t;
const activeSettingsTab = ref('element' as 'element' | 'layout');

const hasSelectedElement = computed(() => {
    return props.selectedElementId !== null;
});
const hasTypeLoadError = computed(() => {
    return typeof props.typeLoadError === 'string' && props.typeLoadError.length > 0;
});
const hasStyleOptionLoadError = computed(() => {
    return typeof props.styleOptionLoadError === 'string' && props.styleOptionLoadError.length > 0;
});
const hasSelectedElementType = computed(() => {
    return props.selectedElementType !== null;
});
const isLoadingSettings = computed(() => {
    return props.isLoadingTypes || props.isLoadingStyleOptions;
});
const elementPropertyValues = computed(() => {
    const selectedElement = props.selectedElement as ContentElementNode | null;
    const typeSpecification = props.selectedElementType as ContentSystemElementTypeSpecification | null;
    const properties = selectedElement?.properties ?? {};
    const values = { ...properties };

    if (!typeSpecification) {
        return values;
    }

    for (const key of Object.keys(typeSpecification.properties)) {
        const storageKey = getElementPropertyStorageKey(typeSpecification, key);

        if (storageKey !== key && Object.prototype.hasOwnProperty.call(properties, storageKey)) {
            values[key] = properties[storageKey];
        }
    }

    return values;
});
const elementStyleValues = computed(() => {
    const selectedElement = props.selectedElement as ContentElementNode | null;

    return selectedElement?.style ?? {};
});
const elementFields = computed(() => {
    const typeSpecification = props.selectedElementType as ContentSystemElementTypeSpecification | null;
    const selectedElement = props.selectedElement as ContentElementNode | null;

    if (!typeSpecification) {
        return [];
    }

    const resolvedPropertyValues = Object.entries(typeSpecification.properties).reduce<Record<string, unknown>>(
        (
            accumulator,
            [
                key,
                property,
            ],
        ) => {
            const storageKey = getElementPropertyStorageKey(typeSpecification, key);
            const elementProperties = selectedElement?.properties ?? {};
            const currentValue = Object.prototype.hasOwnProperty.call(elementProperties, storageKey)
                ? elementProperties[storageKey]
                : elementProperties[key];
            accumulator[key] = getInitialPropertyValue(property, currentValue);

            return accumulator;
        },
        {},
    );

    return Object.entries(typeSpecification.properties)
        .filter(
            ([
                ,
                property,
            ]) => getPropertyControlType(property) !== null,
        )
        .filter(
            ([
                ,
                property,
            ]) => isPropertyVisible(property, resolvedPropertyValues),
        )
        .map(
            ([
                key,
                property,
            ]) => ({
                key,
                property,
                breakpointAware: property.adminUI?.breakpointAware === true,
            }),
        );
});
const layoutFields = computed(() => {
    const styleOptions = props.styleOptions as Record<string, ContentSystemStyleOptionSpecification>;

    return getEditableStyleFields(styleOptions, elementStyleValues.value).map((field) => ({
        key: field.key,
        property: field.property,
        breakpointAware: field.breakpointAware,
    }));
});
const showElementEmptyState = computed(() => {
    return hasSelectedElementType.value && elementFields.value.length === 0;
});
const showLayoutEmptyState = computed(() => {
    return !hasStyleOptionLoadError.value && layoutFields.value.length === 0;
});
const settingsTabItems = computed(() => {
    return [
        {
            name: 'element',
            label: t('ct-experience-studio.detail.elementSettings.tabElement'),
        },
        {
            name: 'layout',
            label: t('ct-experience-studio.detail.elementSettings.tabLayout'),
        },
    ];
});

const onSettingsTabChange = (tabName: string) => {
    if (tabName === 'element' || tabName === 'layout') {
        activeSettingsTab.value = tabName;
    }
};
const onUpdateElementField = (payload: { key: string; value: unknown }) => {
    const selectedElement = props.selectedElement as ContentElementNode | null;

    if (!selectedElement || !props.allowEdit) {
        return;
    }

    const typeSpecification = props.selectedElementType as ContentSystemElementTypeSpecification | null;
    const storageKey = typeSpecification ? getElementPropertyStorageKey(typeSpecification, payload.key) : payload.key;

    emit('update-properties', {
        elementId: selectedElement.id,
        properties: {
            [storageKey]: payload.value,
        },
    });
};
const onUpdateLayoutField = (payload: { key: string; value: unknown }) => {
    const selectedElement = props.selectedElement as ContentElementNode | null;

    if (!selectedElement || !props.allowEdit) {
        return;
    }

    emit('update-style', {
        elementId: selectedElement.id,
        style: {
            [payload.key]: payload.value,
        },
    });
};

watch(
    () => props.selectedElementId,
    () => {
        activeSettingsTab.value = 'element';
    },
);

swDefinePublic({
    activeSettingsTab,
    hasSelectedElement,
    hasTypeLoadError,
    hasStyleOptionLoadError,
    hasSelectedElementType,
    isLoadingSettings,
    elementPropertyValues,
    elementStyleValues,
    elementFields,
    layoutFields,
    showElementEmptyState,
    showLayoutEmptyState,
    settingsTabItems,
    onSettingsTabChange,
    onUpdateElementField,
    onUpdateLayoutField,
});

defineExpose({
    activeSettingsTab,
    hasSelectedElement,
    hasTypeLoadError,
    hasStyleOptionLoadError,
    hasSelectedElementType,
    isLoadingSettings,
    elementPropertyValues,
    elementStyleValues,
    elementFields,
    layoutFields,
    showElementEmptyState,
    showLayoutEmptyState,
    settingsTabItems,
    onSettingsTabChange,
    onUpdateElementField,
    onUpdateLayoutField,
});
</script>

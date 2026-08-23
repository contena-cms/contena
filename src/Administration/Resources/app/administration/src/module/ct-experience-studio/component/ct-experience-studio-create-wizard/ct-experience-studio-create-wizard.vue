<template>
    <ct-block name="sw_experience_studio_create_wizard">
        <div class="ct-experience-studio-create-wizard">
            <div class="ct-experience-studio-create-wizard__content">
                <h2 class="ct-experience-studio-create-wizard__title">
                    {{ $t(createWizardTitleSnippet) }}
                </h2>

                <p class="ct-experience-studio-create-wizard__description">
                    {{ $t(createWizardDescriptionSnippet) }}
                </p>

                <mt-text-field
                    class="ct-experience-studio-create-wizard__name"
                    :label="$t('ct-experience-studio.createWizard.nameLabel')"
                    :placeholder="$t('ct-experience-studio.createWizard.namePlaceholder')"
                    :model-value="name"
                    @update:model-value="onNameChange"
                    @keypress.enter="onComplete"
                />

                <mt-empty-state
                    v-if="isLoadingTypes"
                    icon="regular-hourglass"
                    :description="$t('ct-experience-studio.createWizard.loadingTypes')"
                />

                <mt-empty-state
                    v-else-if="hasTypeLoadError"
                    icon="regular-exclamation-triangle"
                    :description="$t('ct-experience-studio.createWizard.typeLoadError')"
                />

                <mt-empty-state
                    v-else-if="!hasTypeOptions"
                    icon="regular-puzzle-piece"
                    :description="$t('ct-experience-studio.createWizard.noTypes')"
                />

                <div v-else class="ct-experience-studio-create-wizard__type mt-field">
                    <label class="ct-experience-studio-create-wizard__type-label mt-field__label label">
                        {{ $t('ct-experience-studio.createWizard.typeLabel') }}
                    </label>

                    <div
                        class="ct-experience-studio-create-wizard__type-options"
                        role="radiogroup"
                        :aria-label="$t('ct-experience-studio.createWizard.typeLabel')"
                    >
                        <button
                            v-for="option in typeOptions"
                            :id="getTypeOptionId(option.value)"
                            :key="option.value"
                            type="button"
                            class="ct-experience-studio-create-wizard__type-option"
                            :class="{ 'is--selected': isSelectedType(option.value) }"
                            role="radio"
                            :aria-checked="isSelectedType(option.value)"
                            @click="onTypeChange(option.value)"
                        >
                            <mt-icon
                                :name="getTypeIcon(option)"
                                size="20px"
                                class="ct-experience-studio-create-wizard__type-option-icon"
                            />
                            <span class="ct-experience-studio-create-wizard__type-option-label">
                                {{ option.label }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="ct-experience-studio-create-wizard__actions">
                    <mt-button variant="secondary" @click="onCancel">
                        {{ $t('global.default.cancel') }}
                    </mt-button>

                    <mt-button variant="primary" :disabled="!isCompletable || undefined" @click="onComplete">
                        {{ $t(createWizardStartSnippet) }}
                    </mt-button>
                </div>
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import './ct-experience-studio-create-wizard.scss';
type LayoutTypeOption = {
    value: string;
    label: string;
    icon?: string | null;
};

const props = defineProps({
    name: {
        type: String,
        required: false,
        default: '',
    },
    selectedType: {
        type: String,
        required: false,
        default: null,
    },
    typeOptions: {
        type: Array as PropType<LayoutTypeOption[]>,
        required: false,
        default: () => [],
    },
    isLoadingTypes: {
        type: Boolean,
        required: false,
        default: false,
    },
    typeLoadError: {
        type: String,
        required: false,
        default: null,
    },
});
const emit = defineEmits([
    'update:name',
    'update:selected-type',
    'complete',
    'cancel',
]);

import { type PropType, computed } from 'vue';

const createWizardTitleSnippet = computed(() => {
    return 'ct-experience-studio.createWizard.title';
});
const createWizardDescriptionSnippet = computed(() => {
    return 'ct-experience-studio.createWizard.description';
});
const createWizardStartSnippet = computed(() => {
    return 'ct-experience-studio.createWizard.start';
});
const trimmedName = computed(() => {
    return props.name.trim();
});
const hasTypeLoadError = computed(() => {
    return typeof props.typeLoadError === 'string' && props.typeLoadError.length > 0;
});
const hasTypeOptions = computed(() => {
    return Array.isArray(props.typeOptions) && props.typeOptions.length > 0;
});
const isCompletable = computed(() => {
    return (
        trimmedName.value.length > 0 &&
        typeof props.selectedType === 'string' &&
        props.selectedType.length > 0 &&
        !props.isLoadingTypes
    );
});

const getTypeOptionId = (value: string) => {
    const normalized = value.replace(/[^a-zA-Z0-9_-]/g, '-');

    return `ct-experience-studio-create-wizard-type-${normalized}`;
};
const isSelectedType = (value: string) => {
    return props.selectedType === value;
};
const getTypeIcon = (option: LayoutTypeOption) => {
    return option.icon ?? 'regular-file';
};
const onNameChange = (value: string) => {
    emit('update:name', value);
};
const onTypeChange = (value: string | null) => {
    emit('update:selected-type', value);
};
const onCancel = () => {
    emit('cancel');
};
const onComplete = () => {
    if (!isCompletable.value) {
        return;
    }

    emit('complete', {
        name: trimmedName.value,
        type: props.selectedType,
    });
};

swDefinePublic({
    createWizardTitleSnippet,
    createWizardDescriptionSnippet,
    createWizardStartSnippet,
    trimmedName,
    hasTypeLoadError,
    hasTypeOptions,
    isCompletable,
    getTypeOptionId,
    isSelectedType,
    getTypeIcon,
    onNameChange,
    onTypeChange,
    onCancel,
    onComplete,
});

defineExpose({
    createWizardTitleSnippet,
    createWizardDescriptionSnippet,
    createWizardStartSnippet,
    trimmedName,
    hasTypeLoadError,
    hasTypeOptions,
    isCompletable,
    getTypeOptionId,
    isSelectedType,
    getTypeIcon,
    onNameChange,
    onTypeChange,
    onCancel,
    onComplete,
});
</script>

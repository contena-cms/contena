<template>
    <ct-block name="sw_custom_field_set_renderer">
        <div class="ct-custom-field-set-renderer">
            <template v-if="visibleCustomFieldSets.length > 0">
                <ct-block name="sw_custom_field_set_renderer_card">
                    <div
                        v-if="variant === 'tabs'"
                        class="ct-custom-field-set-renderer__card-tabs"
                        position-identifier="ct-custom-field-set-renderer"
                    >
                        <mt-tabs
                            :items="customFieldSetTabs"
                            :default-item="visibleCustomFieldSets[0].id"
                            @new-item-active="onTabChange"
                        />
                        <ct-block name="sw_custom_field_set_renderer_card_tabs"> </ct-block>

                        <ct-block name="sw_custom_field_set_renderer_card_tabs_content">
                            <div class="ct-custom-field-set-renderer__tab-content">
                                <template v-for="set in visibleCustomFieldSets" :key="set.id">
                                    <div
                                        v-show="(activeCustomFieldSetId || visibleCustomFieldSets[0].id) === set.id"
                                        :class="'ct-custom-field-set-renderer-tab-content__' + set.name"
                                    >
                                        <ct-block name="sw_custom_field_set_renderer_card_form_renderer">
                                            <ct-skeleton v-if="!set.customFields" style="width: 100%" />
                                            <template v-else>
                                                <template v-for="customField in set.customFields" :key="customField.name">
                                                    <ct-inherit-wrapper
                                                        v-if="entity && customField.config"
                                                        v-model:value="customFields[customField.name]"
                                                        v-bind="getInheritWrapperBind(customField)"
                                                        :class="'ct-form-field-renderer-field__' + customField.name"
                                                        :has-parent="hasParent"
                                                        :required="customField.config.validation === 'required'"
                                                        :inherited-value="getInheritedCustomField(customField.name)"
                                                    >
                                                        <template #content="props">
                                                            <ct-form-field-renderer
                                                                v-bind="getBind(customField, props)"
                                                                :key="props.isInherited"
                                                                :class="
                                                                    'ct-form-field-renderer-input-field__' + customField.name
                                                                "
                                                                :disabled="disabled"
                                                                :value="props.currentValue"
                                                                v-on="getElementEventListeners(customField, props)"
                                                                @update:value="props.updateCurrentValue"
                                                            />
                                                        </template>
                                                    </ct-inherit-wrapper>
                                                </template>
                                            </template>
                                        </ct-block>
                                    </div>
                                </template>
                            </div>
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="sw_custom_field_set_renderer_media">
                    <template v-if="variant === 'tabs'"
                        ><!-- Keeps the conditional chain connected across ct-block. --></template
                    >
                    <template v-else-if="variant === 'media-collapse'">
                        <template v-for="set in visibleCustomFieldSets" :key="`ct-media-collapse--${set.id}`">
                            <!-- eslint-disable vue/no-use-v-if-with-v-for -->
                            <ct-media-collapse
                                v-if="set.customFields && set.customFields.length > 0"
                                :expand-on-loading="false"
                                :title="getInlineSnippet(set.config.label) || set.name"
                            >
                                <template #content>
                                    <template v-for="customField in set.customFields" :key="customField.name">
                                        <ct-block name="sw_custom_field_set_renderer_media_form_renderer">
                                            <ct-form-field-renderer
                                                v-bind="customField"
                                                v-model:value="customFields[customField.name]"
                                                :disabled="disabled"
                                                :required="customField.config.validation === 'required'"
                                            />
                                        </ct-block>
                                    </template>
                                    <ct-block name="sw_custom_field_set_renderer_media_button_save">
                                        <ct-button-process
                                            :is-loading="isLoading"
                                            :process-success="isSaveSuccessful"
                                            :disabled="isLoading || disabled"
                                            variant="primary"
                                            size="small"
                                            @update:process-success="$emit('process-finish')"
                                            @click="$emit('save')"
                                        >
                                            {{ $t('global.default.save') }}
                                        </ct-button-process>
                                    </ct-block>
                                </template>
                            </ct-media-collapse>
                        </template>
                    </template>
                </ct-block>
            </template>
        </div>
    </ct-block>
</template>

<script setup>
import { mapInheritanceSlotPropsToMeteorProps } from 'src/core/service/utils/meteor-inheritance.utils';
import './ct-custom-field-set-renderer.scss';
const { Criteria } = Contena.Data;

const props = defineProps({
    sets: {
        type: Array,
        required: true,
    },
    entity: {
        type: Object,
        required: true,
    },
    parentEntity: {
        type: Object,
        required: false,
        default: null,
    },
    variant: {
        type: String,
        required: false,
        default: 'tabs',
        validValues: [
            'tabs',
            'media-collapse',
        ],
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'tabs',
                'media-collapse',
            ].includes(value);
        },
    },
    disabled: {
        type: Boolean,
        default: false,
        required: false,
    },
    isLoading: {
        type: Boolean,
        default: false,
        required: false,
    },
    isSaveSuccessful: {
        type: Boolean,
        default: false,
        required: false,
    },
    showCustomFieldSetSelection: {
        type: Boolean,
        default: false,
        require: false,
    },
});
const emit = defineEmits([
    'process-finish',
    'save',
    'change-active-selection',
]);

import { ref, computed, inject, provide, watch } from 'vue';
import { useInlineSnippet } from 'src/app/composables/use-inline-snippet';

const { getInlineSnippet } = useInlineSnippet();

const repositoryFactory = inject('repositoryFactory');

const customFields = ref({});
const activeCustomFieldSetId = ref(null);
const indirectInheritedCustomFields = ref(null);
const loadingFields = ref([]);
const refreshVisibleSets = ref(false);
const translatedInheritanceLoadKey = ref(null);
const renderSets = ref(Array.from(props.sets ?? []));

const hasParent = computed(() => {
    return hasExplicitParentEntity.value || usesTranslatedInheritance.value;
});
const hasExplicitParentEntity = computed(() => {
    return !!props.parentEntity?.id;
});
const usesTranslatedInheritance = computed(() => {
    return (
        !hasExplicitParentEntity.value &&
        !!props.entity?.id &&
        typeof props.entity?.getEntityName === 'function' &&
        !!translatedInheritanceSourceLanguageId.value
    );
});
const visibleCustomFieldSets = computed(() => {
    return sortSets(renderSets.value);
});
const customFieldSetTabs = computed(() => {
    return visibleCustomFieldSets.value.map((set) => ({
        label: getTabLabel(set),
        name: set.id,
    }));
});
const customFieldSetRepository = computed(() => {
    return repositoryFactory.create('custom_field_set');
});
const createCustomFieldSetCriteria = () => {
    const criteria = new Criteria(1, null);
    const entityName = props.entity.getEntityName?.() ?? props.entity.entityName ?? props.entity.apiAlias ?? '';
    criteria.addFilter(Criteria.equals('relations.entityName', entityName));
    criteria.addFilter(Criteria.equals('global', 0));
    criteria.addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

    return criteria;
};
const customFieldSetCriteria = computed(createCustomFieldSetCriteria);
const globalCustomFieldSets = computed(() => {
    return renderSets.value.filter((set) => set.global);
});
const componentsWithMapInheritanceSupport = computed(() => {
    return [
        'mt-text-field',
        'mt-textarea',
        'mt-select',
        'mt-checkbox',
        'mt-switch',
        'mt-number-field',
        'mt-datepicker',
        'mt-email-field',
        'mt-url-field',
        'mt-password-field',
        'mt-radio-group-root',
        'mt-colorpicker',
        // Stored custom-field configuration can still use legacy renderer identifiers.
        'ct-text-field',
        'ct-textarea-field',
        'ct-select-field',
        'ct-checkbox-field',
        'ct-switch-field',
        'ct-number-field',
        'ct-datepicker',
        'ct-email-field',
        'ct-url-field',
        'ct-password-field',
        'ct-colorpicker',
        'ct-tagged-field',
        'ct-field',
    ];
});
const translatedInheritanceSourceLanguageId = computed(() => {
    const language = Contena.Store.get('context')?.api?.language;
    const parentLanguageId = language?.parentId;

    if (parentLanguageId) {
        return parentLanguageId;
    }

    if (Contena.Context.api.languageId === Contena.Context.api.systemLanguageId) {
        return null;
    }

    return Contena.Context.api.systemLanguageId;
});

const createdComponent = () => {
    initializeCustomFields();
    void loadInheritedCustomFields();
    onChangeCustomFieldSets();
};
function initializeCustomFields() {
    if (!props.entity.customFields) {
        return;
    }
    customFields.value = props.entity.customFields;
}
const hasOverriddenTranslatedCustomFields = () => {
    return Object.values(customFields.value ?? {}).some((value) => value !== null && value !== undefined);
};
const hasInheritedTranslatedCustomFields = () => {
    return renderSets.value.some((set) => {
        return set.customFields?.some((customField) => isInheritedTranslatedCustomField(customField.name));
    });
};
const hasInheritedTranslatedCustomFieldsWithoutFallback = () => {
    return renderSets.value.some((set) => {
        return set.customFields?.some((customField) => {
            if (!isInheritedTranslatedCustomField(customField.name)) {
                return false;
            }

            const translatedValue = props.entity?.translated?.customFields?.[customField.name];

            return translatedValue === null || translatedValue === undefined;
        });
    });
};
const resetTranslatedInheritanceState = () => {
    indirectInheritedCustomFields.value = null;
    translatedInheritanceLoadKey.value = null;
};
const getTranslatedInheritanceLoadKey = () => {
    return [
        props.entity.getEntityName(),
        props.entity.id,
        translatedInheritanceSourceLanguageId.value,
    ].join(':');
};
const getTranslatedInheritanceContext = () => {
    return {
        ...Contena.Context.api,
        languageId: translatedInheritanceSourceLanguageId.value,
    };
};
function isInheritedTranslatedCustomField(customFieldName) {
    return customFields.value?.[customFieldName] === null || customFields.value?.[customFieldName] === undefined;
}
const getInheritedCustomFields = (customFieldName) => {
    const parentCustomFields = props.parentEntity?.translated?.customFields;

    if (parentCustomFields) {
        return parentCustomFields?.[customFieldName];
    }

    if (!usesTranslatedInheritance.value || !isInheritedTranslatedCustomField(customFieldName)) {
        return indirectInheritedCustomFields.value?.[customFieldName];
    }

    if (Object.hasOwn(indirectInheritedCustomFields.value ?? {}, customFieldName)) {
        return indirectInheritedCustomFields.value?.[customFieldName];
    }

    return props.entity?.translated?.customFields?.[customFieldName];
};
const getDefaultInheritedCustomFieldValue = (customFieldName) => {
    const customFieldInformation = getCustomFieldInformation(customFieldName);
    const customFieldType = customFieldInformation.type;

    switch (customFieldType) {
        case 'select': {
            return [];
        }

        case 'bool': {
            return false;
        }

        case 'html':
        case 'datetime':
        case 'text': {
            return '';
        }

        case 'float':
        case 'int': {
            return 0;
        }

        default: {
            return null;
        }
    }
};
async function loadInheritedCustomFields() {
    if (!usesTranslatedInheritance.value) {
        resetTranslatedInheritanceState();
        return;
    }
    const loadKey = getTranslatedInheritanceLoadKey();
    if (!hasOverriddenTranslatedCustomFields() && !hasInheritedTranslatedCustomFields()) {
        if (translatedInheritanceLoadKey.value !== loadKey) {
            resetTranslatedInheritanceState();
        }
        return;
    }
    if (translatedInheritanceLoadKey.value === loadKey) {
        return;
    }
    translatedInheritanceLoadKey.value = loadKey;
    try {
        const inheritedEntity = await repositoryFactory
            .create(props.entity.getEntityName())
            .get(props.entity.id, getTranslatedInheritanceContext());
        if (translatedInheritanceLoadKey.value !== loadKey) {
            return;
        }
        indirectInheritedCustomFields.value = inheritedEntity?.customFields ?? null;
    } catch (error) {
        console.error(error);
        if (translatedInheritanceLoadKey.value === loadKey) {
            resetTranslatedInheritanceState();
        }
    }
}
const getInheritedCustomField = (customFieldName) => {
    const value = getInheritedCustomFields(customFieldName);

    if (value !== null && value !== undefined) {
        return value;
    }

    return getDefaultInheritedCustomFieldValue(customFieldName);
};
function getCustomFieldInformation(customFieldName) {
    let returnValue;
    renderSets.value.some((set) =>
        set.customFields.some((customField) => {
            const isMatching = customField.name === customFieldName;
            if (isMatching) {
                returnValue = customField;
            }
            return isMatching;
        }),
    );
    return returnValue;
}
const getInheritValue = (field) => {
    // Search field in translated
    const value = props.parentEntity?.translated?.[field] ?? null;

    if (value) {
        return value;
    }

    // Search field on top level of entity
    return props.parentEntity?.[field] ?? null;
};
const getParentCustomFieldSetSelectionSwitchState = () => {
    const parentEntity = props.parentEntity;

    if (parentEntity && parentEntity.hasOwnProperty('customFieldSets')) {
        return parentEntity.customFieldSets.length > 0;
    }

    return null;
};
const supportsMapInheritance = (customField) => {
    const componentName = customField.config.componentName;

    return componentsWithMapInheritanceSupport.value.includes(componentName);
};
const isMeteorComponent = (customField) => {
    if (customField.config?.componentName?.startsWith('mt-')) {
        return true;
    }

    return [
        'bool',
        'text',
        'number',
        'float',
        'int',
        'datetime',
    ].includes(customField.type);
};
const getBind = (customField, props) => {
    const customFieldClone = Contena.Utils.object.cloneDeep(customField);
    const isMeteorComponentValue = isMeteorComponent(customField);
    const inheritedCustomFieldValue = props.isInheritField ? getInheritedCustomField(customField.name) : null;
    if (customFieldClone.type === 'bool') {
        customFieldClone.config.bordered = true;
    }
    if (supportsMapInheritance(customFieldClone)) {
        customFieldClone.mapInheritance = props;

        // Special case for meteor components
        if (isMeteorComponentValue) {
            Object.assign(customFieldClone, mapInheritanceSlotPropsToMeteorProps(props, inheritedCustomFieldValue));
            customFieldClone.disabled = props.disabled || props.isInherited;
        }

        return customFieldClone;
    }
    delete customFieldClone.config.label;
    delete customFieldClone.config.helpText;
    return customFieldClone;
};
const getElementEventListeners = (customField, props) => {
    const isMeteorComponentValue = isMeteorComponent(customField);
    const eventHandler = {};
    if (isMeteorComponentValue) {
        eventHandler['inheritance-remove'] = props.removeInheritance;
        eventHandler['inheritance-restore'] = props.restoreInheritance;
    }
    return eventHandler;
};
const getInheritWrapperBind = (customField) => {
    if (supportsMapInheritance(customField)) {
        return {};
    }

    return {
        helpText: getInlineSnippet(customField.config.helpText) || '',
        label: getInlineSnippet(customField.config.label) || ' ',
    };
};
const customFieldSetCriteriaById = () => {
    const criteria = new Criteria(1, 1);

    criteria.getAssociation('customFields').addSorting(Criteria.naturalSorting('config.customFieldPosition'));

    return criteria;
};
const loadCustomFieldSet = (setId) => {
    if (loadingFields.value.includes(setId)) {
        // as we might triggered multiple times with the same item, we store the loading set in a heap cache
        return;
    }

    // failsave dealing with sets (should be an entityCollection, but in reality might be just an array)
    const set = renderSets.value.find((candidate) => candidate.id === setId);

    if (set.customFields && set.customFields.length > 0) {
        // already loaded, so do nothing
        return;
    }

    // indicate the loading of this item
    loadingFields.value.push(setId);

    // fully load the set
    customFieldSetRepository.value
        .get(setId, Contena.Context.api, customFieldSetCriteriaById())
        .then((newSet) => {
            // replace the fully fetched set
            renderSets.value.forEach((originalSet, index) => {
                if (originalSet.id === newSet.id) {
                    renderSets.value[index] = newSet;
                }
            });

            // remove the set from the currently loading onces and refresh the visible sets
            loadingFields.value = loadingFields.value.filter((s) => s.id !== setId);
        })
        .catch((error) => {
            console.error(error);
            // in case of error make loading again possible
            loadingFields.value = loadingFields.value.filter((s) => s.id !== setId);
        });
};
const resetTabs = () => {
    if (visibleCustomFieldSets.value.length > 0) {
        onTabChange(visibleCustomFieldSets.value[0].id);
    }
};
function onTabChange(setId) {
    activeCustomFieldSetId.value = setId;
    loadCustomFieldSet(setId);
}
function getTabLabel(set) {
    if (set.config && getInlineSnippet(set.config.label)) {
        return getInlineSnippet(set.config.label);
    }
    return set.name;
}
function onChangeCustomFieldSets(value, updateFn) {
    resetTabs();
    if (typeof updateFn === 'function') {
        updateFn(value);
    }
}
const onChangeCustomFieldSetSelectionActive = (newVal) => {
    onChangeCustomFieldSets();
    if (!newVal) {
        if (!props.entity.customFieldSets) {
            initializeCustomFields();
            return;
        }
        // DAL entities are mutable by design; this clears the association selected by the user.
        // eslint-disable-next-line vue/no-mutating-props
        props.entity.customFieldSets = props.entity.customFieldSets.filter(() => {
            return false;
        });
    }
};
function sortSets(sets) {
    return [...sets].sort((a, b) => a.position - b.position);
}
const onUpdateActiveSelection = (value) => {
    emit('change-active-selection', value);
};

watch(
    () => translatedInheritanceSourceLanguageId.value,
    () => {
        void loadInheritedCustomFields();
    },
);
watch(
    () => props.sets,
    (sets) => {
        renderSets.value = Array.from(sets ?? []);
        void loadInheritedCustomFields();
    },
    { deep: true },
);
watch(
    () => props.entity.customFieldSetSelectionActive,
    (value) => {
        onChangeCustomFieldSetSelectionActive(value);
    },
    { deep: true },
);
watch(
    () => props.entity.customFieldsSets,
    () => {
        onChangeCustomFieldSets();
    },
);
watch(
    () => props.entity,
    () => {
        initializeCustomFields();
        void loadInheritedCustomFields();
    },
    { deep: true },
);
watch(
    () => customFields.value,
    (customFields) => {
        // DAL entities are mutable by design; the renderer persists its edited field map on the entity.
        // eslint-disable-next-line vue/no-mutating-props
        props.entity.customFields = customFields;
    },
    { deep: true },
);

createdComponent();

swDefinePublic({
    repositoryFactory,
    customFields,
    activeCustomFieldSetId,
    indirectInheritedCustomFields,
    loadingFields,
    refreshVisibleSets,
    translatedInheritanceLoadKey,
    hasParent,
    hasExplicitParentEntity,
    usesTranslatedInheritance,
    visibleCustomFieldSets,
    customFieldSetTabs,
    customFieldSetRepository,
    customFieldSetCriteria,
    globalCustomFieldSets,
    componentsWithMapInheritanceSupport,
    translatedInheritanceSourceLanguageId,
    createdComponent,
    initializeCustomFields,
    hasOverriddenTranslatedCustomFields,
    hasInheritedTranslatedCustomFields,
    hasInheritedTranslatedCustomFieldsWithoutFallback,
    resetTranslatedInheritanceState,
    getTranslatedInheritanceLoadKey,
    getTranslatedInheritanceContext,
    isInheritedTranslatedCustomField,
    getInheritedCustomFields,
    getDefaultInheritedCustomFieldValue,
    loadInheritedCustomFields,
    getInheritedCustomField,
    getCustomFieldInformation,
    getInheritValue,
    getParentCustomFieldSetSelectionSwitchState,
    supportsMapInheritance,
    isMeteorComponent,
    getBind,
    getElementEventListeners,
    getInheritWrapperBind,
    customFieldSetCriteriaById,
    loadCustomFieldSet,
    resetTabs,
    onTabChange,
    getTabLabel,
    onChangeCustomFieldSets,
    onChangeCustomFieldSetSelectionActive,
    sortSets,
    onUpdateActiveSelection,
});

defineExpose({
    repositoryFactory,
    customFields,
    activeCustomFieldSetId,
    indirectInheritedCustomFields,
    loadingFields,
    refreshVisibleSets,
    translatedInheritanceLoadKey,
    hasParent,
    hasExplicitParentEntity,
    usesTranslatedInheritance,
    visibleCustomFieldSets,
    customFieldSetTabs,
    customFieldSetRepository,
    customFieldSetCriteria,
    globalCustomFieldSets,
    componentsWithMapInheritanceSupport,
    translatedInheritanceSourceLanguageId,
    createdComponent,
    initializeCustomFields,
    hasOverriddenTranslatedCustomFields,
    hasInheritedTranslatedCustomFields,
    hasInheritedTranslatedCustomFieldsWithoutFallback,
    resetTranslatedInheritanceState,
    getTranslatedInheritanceLoadKey,
    getTranslatedInheritanceContext,
    isInheritedTranslatedCustomField,
    getInheritedCustomFields,
    getDefaultInheritedCustomFieldValue,
    loadInheritedCustomFields,
    getInheritedCustomField,
    getCustomFieldInformation,
    getInheritValue,
    getParentCustomFieldSetSelectionSwitchState,
    supportsMapInheritance,
    isMeteorComponent,
    getBind,
    getElementEventListeners,
    getInheritWrapperBind,
    customFieldSetCriteriaById,
    loadCustomFieldSet,
    resetTabs,
    onTabChange,
    getTabLabel,
    onChangeCustomFieldSets,
    onChangeCustomFieldSetSelectionActive,
    sortSets,
    onUpdateActiveSelection,
});

provide(
    'getEntity',
    computed(() => props.entity),
);
provide(
    'getParentEntity',
    computed(() => props.parentEntity),
);
provide(
    'getCustomFieldSetVariant',
    computed(() => props.variant),
);
</script>

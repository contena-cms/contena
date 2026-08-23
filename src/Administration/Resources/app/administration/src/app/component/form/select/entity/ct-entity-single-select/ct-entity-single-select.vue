<template>
    <ct-block name="sw_entity_single_select">
        <ct-select-base
            ref="selectBase"
            class="ct-entity-single-select"
            :is-loading="isLoading"
            :size="size"
            :disable-auto-close="disableAutoClose"
            v-bind="$attrs"
            :label="label"
            :disabled="disabled"
            @clear="clearInput"
            @select-expanded="onSelectExpanded"
            @select-collapsed="onSelectCollapsed"
        >
            <template #ct-select-selection>
                <ct-block name="sw_entity_single_select_base_selection">
                    <ct-block name="sw_entity_single_select_advanced_selection_modal">
                        <component
                            :is="advancedSelectionComponent"
                            v-if="isAdvancedSelectionActive && isAdvancedSelectionModalVisible"
                            :is-single-select="true"
                            :initial-search-term="advancedSelectionInitialSearchTerm"
                            v-bind="advancedSelectionParameters"
                            @modal-close="closeAdvancedSelectionModal"
                            @selection-submit="onAdvancedSelectionSubmit"
                        />
                    </ct-block>

                    <ct-block name="sw_entity_single_select_base_selection_slot">
                        <div class="ct-entity-single-select__selection">
                            <ct-block name="sw_entity_single_select_single_selection_inner">
                                <ct-block name="sw_entity_single_select_single_selection_inner_label">
                                    <div
                                        v-show="!isExpanded"
                                        class="ct-entity-single-select__selection-text"
                                        :class="selectionTextClasses"
                                    >
                                        <template v-if="singleSelection">
                                            <slot
                                                name="selection-label-property"
                                                v-bind="{ item: singleSelection, labelProperty, searchTerm, getKey }"
                                            >
                                                {{ displayLabelProperty(singleSelection) }}
                                            </slot>
                                        </template>
                                        <template v-else>
                                            {{ placeholder }}
                                        </template>
                                    </div>
                                </ct-block>
                                <ct-block name="sw_entity_single_select_single_selection_inner_input">
                                    <!-- eslint-disable-next-line vuejs-accessibility/form-control-has-label -->
                                    <input
                                        ref="swSelectInput"
                                        v-model="searchTerm"
                                        class="ct-entity-single-select__selection-input"
                                        :class="inputClasses"
                                        type="text"
                                        :placeholder="placeholder"
                                        :aria-label="label"
                                        :autocomplete="autocomplete"
                                        @input="onInputSearchTerm"
                                    />
                                </ct-block>
                            </ct-block>
                        </div>
                    </ct-block>
                </ct-block>
            </template>

            <template #results-list>
                <ct-block name="sw_entity_single_select_base_results">
                    <ct-block name="sw_entity_single_select_base_results_slot">
                        <ct-select-result-list
                            ref="resultsList"
                            :options="results"
                            :is-loading="isLoading"
                            :popover-classes="popoverClasses"
                            :empty-message="translate('global.ct-single-select.messageNoResults', { term: searchTerm })"
                            :focus-el="$refs.swSelectInput"
                            @paginate="paginate"
                            @item-select="setValue"
                        >
                            <template #before-item-list>
                                <ct-block name="sw_entity_single_select_base_results_list_before">
                                    <ct-block name="sw_entity_single_select_base_results_list_before_advanced_selection">
                                        <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
                                        <div
                                            v-if="isAdvancedSelectionActive"
                                            class="ct-single-select-filtering__advanced-selection ct-select-result"
                                            @click="openAdvancedSelectionModal"
                                        >
                                            {{ translate('global.ct-entity-advanced-selection-modal.link') }}
                                        </div>
                                    </ct-block>

                                    <ct-block name="sw_entity_single_select_base_results_list_before_slot">
                                        <slot name="before-item-list"></slot>
                                    </ct-block>
                                </ct-block>
                            </template>

                            <template #result-item="{ item, index }">
                                <ct-block name="sw_entity_single_select_base_results_list_result">
                                    <slot
                                        name="result-item"
                                        v-bind="{
                                            item,
                                            index,
                                            labelProperty,
                                            searchTerm,
                                            highlightSearchTerm,
                                            isSelected,
                                            setValue,
                                            getKey,
                                        }"
                                    >
                                        <ct-select-result
                                            :tooltip="getDisabledSelectionTooltip(item)"
                                            :selected="isSelected(item)"
                                            :disabled="isSelectionDisabled(item)"
                                            :description-position="descriptionPosition"
                                            v-bind="{ item, index }"
                                            @item-select="setValue"
                                        >
                                            <template v-if="shouldShowActiveState" #preview>
                                                <ct-block name="sw_entity_multi_select_base_results_list_result_preview">
                                                    <ct-block name="sw_entity_multi_select_base_results_list_result_active">
                                                        <mt-icon
                                                            class="ct-entity-single-select__selection-active"
                                                            size="6"
                                                            :color="getActiveIconColor(item)"
                                                            name="solid-circle"
                                                        />
                                                    </ct-block>
                                                </ct-block>
                                            </template>

                                            <ct-block name="sw_entity_single_select_base_results_list_result_label">
                                                <slot
                                                    name="result-label-property"
                                                    v-bind="{
                                                        item,
                                                        index,
                                                        labelProperty,
                                                        searchTerm,
                                                        highlightSearchTerm,
                                                        getKey,
                                                    }"
                                                >
                                                    <ct-highlight-text
                                                        v-if="highlightSearchTerm && !isSelected(item)"
                                                        :text="displayLabelProperty(item)"
                                                        :search-term="searchTerm"
                                                    />
                                                    <template v-else>
                                                        {{ displayLabelProperty(item) }}
                                                    </template>
                                                </slot>
                                            </ct-block>
                                            <template #description>
                                                <ct-block name="sw_entity_multi_select_base_results_list_result_description">
                                                    <slot
                                                        name="result-description-property"
                                                        v-bind="{ item, searchTerm, highlightSearchTerm }"
                                                    ></slot>
                                                </ct-block>
                                            </template>
                                        </ct-select-result>
                                    </slot>
                                </ct-block>
                            </template>

                            <template #after-item-list>
                                <ct-block name="sw_entity_single_select_base_results_list_after">
                                    <slot name="after-item-list"></slot>
                                </ct-block>
                            </template>
                        </ct-select-result-list>
                    </ct-block>
                </ct-block>
            </template>

            <template #label>
                <slot name="label"></slot>
            </template>

            <template #hint>
                <slot name="hint"></slot>
            </template>
        </ct-select-base>
    </ct-block>
</template>

<script setup>
import './ct-entity-single-select.scss';
const { Component, Utils } = Contena;
const { Criteria, EntityCollection } = Contena.Data;
const { debounce, get } = Contena.Utils;

const props = defineProps({
    // null is a common value here, e.g. passed by the inheritance system.
    value: {
        required: false,
    },
    highlightSearchTerm: {
        type: Boolean,
        required: false,
        default: true,
    },
    placeholder: {
        type: String,
        required: false,
        default: '',
    },
    resetOption: {
        type: String,
        required: false,
        default: '',
    },
    labelProperty: {
        type: [
            String,
            Array,
        ],
        required: false,
        default: 'name',
    },
    labelCallback: {
        type: Function,
        required: false,
        default: null,
    },
    entity: {
        required: true,
        type: String,
    },
    resultLimit: {
        type: Number,
        required: false,
        default: 25,
    },
    criteria: {
        type: Object,
        required: false,
        default(props) {
            return new Contena.Data.Criteria(1, props.resultLimit).setTotalCountMode(0);
        },
    },
    context: {
        type: Object,
        required: false,
        default: () => Contena.Context.api,
    },
    selectionDisablingMethod: {
        type: Function,
        required: false,
        default: () => false,
    },
    disableAutoClose: {
        type: Boolean,
        required: false,
        default: false,
    },
    disabledSelectionTooltip: {
        type: Object,
        required: false,
        default: () => {
            return { message: '' };
        },
    },
    descriptionPosition: {
        type: String,
        required: false,
        default: 'right',
        validValues: [
            'bottom',
            'right',
            'left',
        ],
        validator(value) {
            return [
                'bottom',
                'right',
                'left',
            ].includes(value);
        },
    },
    allowEntityCreation: {
        type: Boolean,
        required: false,
        default: false,
    },
    entityCreationLabel: {
        type: String,
        required: false,
        default() {
            return Contena.Snippet.tc('global.ct-single-select.labelEntity');
        },
    },
    advancedSelectionComponent: {
        type: String,
        required: false,
        default: '',
    },
    advancedSelectionParameters: {
        type: Object,
        required: false,
        default() {
            return {};
        },
    },
    shouldShowActiveState: {
        type: Boolean,
        required: false,
        default: false,
    },
    disabled: {
        type: Boolean,
        required: false,
        default: undefined,
    },
    label: {
        type: String,
        required: false,
        default: undefined,
    },
    size: {
        type: String,
        required: false,
        default: 'default',
    },
    popoverClasses: {
        type: Array,
        required: false,
        default: () => [],
    },
    autocomplete: {
        type: String,
        required: false,
        default: undefined,
    },
});
const emit = defineEmits([
    'update:value',
    'search',
    'option-select',
    'before-selection-clear',
    'search-term-change',
]);

import { ref, computed, inject, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const translate = t;
const swSelectInput = ref(null);
const selectBase = ref(null);
const resultsList = ref(null);

const repositoryFactory = inject('repositoryFactory');
const searchTerm = ref('');
const isExpanded = ref(false);
const resultCollection = ref(null);
const singleSelection = ref(null);
const isLoading = ref(false);
const itemRecentlySelected = ref(false);
const lastSelection = ref(null);
const entityExists = ref(true);
const newEntityName = ref('');
const isAdvancedSelectionModalVisible = ref(false);

const inputClasses = computed(() => {
    return {
        'is--expanded': isExpanded.value,
    };
});
const selectionTextClasses = computed(() => {
    return {
        'is--placeholder': !singleSelection.value,
    };
});
const repository = computed(() => {
    return repositoryFactory.create(props.entity);
});
const results = computed(() => {
    return resultCollection.value;
});
const isAdvancedSelectionActive = computed(() => {
    return props.advancedSelectionComponent && Component.getComponentRegistry().has(props.advancedSelectionComponent);
});
const advancedSelectionInitialSearchTerm = computed(() => {
    if (singleSelection.value && tryGetSearchText(singleSelection.value) === searchTerm.value) {
        return '';
    }

    return searchTerm.value;
});

const createdComponent = () => {
    loadSelected();
};
function loadSelected() {
    if (!props.value || props.value.length === 0) {
        if (props.resetOption) {
            singleSelection.value = {
                id: null,
                name: props.resetOption,
            };
        }
        return Promise.resolve();
    }
    isLoading.value = true;
    return repository.value
        .get(
            props.value,
            {
                ...props.context,
                inheritance: true,
            },
            props.criteria,
        )
        .then((item) => {
            if (!item) {
                emit('update:value', null);
            }
            props.criteria.setIds([]);
            singleSelection.value = item;
            isLoading.value = false;
            return item;
        });
}
const createCollection = (collection) => {
    return new EntityCollection(collection.source, collection.entity, collection.criteria);
};
const isSelected = (item) => {
    return item.id === props.value;
};
const debouncedSearch = debounce(() => {
    search();
}, 400);
function search() {
    if (props.criteria.term === searchTerm.value) {
        if (props.allowEntityCreation) {
            filterSearchGeneratedTags();
        }
        return Promise.resolve();
    }
    if (!props.allowEntityCreation) {
        return handleSearchPromise();
    }
    isLoading.value = true;
    return checkEntityExists(searchTerm.value).then(() => {
        if (!entityExists.value && searchTerm.value) {
            const criteria = new Criteria(1, props.resultLimit);
            criteria.addFilter(Criteria.contains('name', searchTerm.value));
            return repository.value
                .search(criteria, {
                    ...props.context,
                    inheritance: true,
                })
                .then((result) => {
                    resultCollection.value = result;
                    const newEntity = repository.value.create(props.context, -1);
                    newEntity.name = t(
                        'global.ct-single-select.labelEntityAdd',
                        {
                            term: searchTerm.value,
                            entity: props.entityCreationLabel,
                        },
                        0,
                    );
                    resultCollection.value.unshift(newEntity);
                    newEntityName.value = searchTerm.value;
                    displaySearch(resultCollection.value);
                    isLoading.value = false;
                    return Promise.resolve();
                });
        }
        return handleSearchPromise();
    });
}
function handleSearchPromise() {
    props.criteria.setPage(1);
    props.criteria.setLimit(props.resultLimit);
    props.criteria.setTerm(searchTerm.value);
    resultCollection.value = null;
    const searchPromise = loadData().then(() => {
        resetActiveItem();
    });
    emit('search', searchPromise);
    return searchPromise;
}
const paginate = () => {
    if (!resultCollection.value || resultCollection.value.total < props.criteria.page * props.criteria.limit) {
        return;
    }

    props.criteria.setPage(props.criteria.page + 1);

    loadData();
};
function loadData() {
    isLoading.value = true;
    return repository.value
        .search(props.criteria, {
            ...props.context,
            inheritance: true,
        })
        .then((result) => {
            displaySearch(result);
            isLoading.value = false;
            return result;
        });
}
function checkEntityExists(term) {
    // Set existing entity to true to display all manufacturers when no search term is given
    if (term.trim().length === 0) {
        entityExists.value = true;
        return Promise.resolve();
    }
    const criteria = new Criteria(1, props.resultLimit);
    criteria.addIncludes({
        [props.entity]: [
            'id',
            'name',
        ],
    });
    criteria.addFilter(Criteria.equals('name', term));
    return repository.value.search(criteria, props.context).then((response) => {
        entityExists.value = response.total > 0;
        return response.total > 0;
    });
}
function displaySearch(result) {
    if (!resultCollection.value) {
        resultCollection.value = result;
    } else {
        result.forEach((item) => {
            // Prevent duplicate entries
            if (!resultCollection.value.has(item.id)) {
                resultCollection.value.push(item);
            }
        });
    }
    if (props.resetOption) {
        if (!resultCollection.value.has(null)) {
            resultCollection.value.unshift({
                id: null,
                name: props.resetOption,
            });
        }
    }
}
const displayLabelProperty = (item) => {
    if (typeof props.labelCallback === 'function') {
        return props.labelCallback(item);
    }

    const labelProperties = [];

    if (Array.isArray(props.labelProperty)) {
        labelProperties.push(...props.labelProperty);
    } else {
        labelProperties.push(props.labelProperty);
    }

    return labelProperties
        .map((labelProperty) => {
            const propertyName = typeof labelProperty === 'string' ? labelProperty : '';

            return getKey(item, propertyName) || getKey(item, `translated.${propertyName}`);
        })
        .join(' ');
};
const onSelectExpanded = () => {
    isExpanded.value = true;
    // Always start with a fresh list when opening the result list
    props.criteria.setPage(1);
    props.criteria.setLimit(props.resultLimit);
    props.criteria.setTerm('');
    resultCollection.value = null;

    loadData().then(() => {
        resetActiveItem();
    });

    // Get the search text of the selected item as prefilled value
    searchTerm.value = tryGetSearchText(singleSelection.value);

    void nextTick(() => {
        swSelectInput.value.select();
        swSelectInput.value.focus();
    });
};
function tryGetSearchText(option) {
    if (typeof props.labelCallback === 'function') {
        return props.labelCallback(option);
    }
    const propertyName = typeof props.labelProperty === 'string' ? props.labelProperty : '';
    let searchText = getKey(option, propertyName, '');
    if (!searchText) {
        searchText = getKey(option, `translated.${propertyName}`, '');
    }

    return typeof searchText === 'string' ? searchText : '';
}
const onSelectCollapsed = () => {
    // Empty the selection if the search term is empty
    if (searchTerm.value === '' && !itemRecentlySelected.value) {
        clearSelection();
    }
    swSelectInput.value.blur();
    searchTerm.value = '';
    itemRecentlySelected.value = false;
    isExpanded.value = false;
};
const closeResultList = () => {
    selectBase.value.collapse();
};
const addItem = (item) => {
    if (!props.allowEntityCreation || item.id !== -1) {
        return null;
    }

    createNewEntity();
    return null;
};
const setValue = (item) => {
    itemRecentlySelected.value = true;

    if (!props.disableAutoClose) {
        closeResultList();
    }

    if (props.allowEntityCreation && !entityExists.value && item.id === -1) {
        return addItem(item);
    }

    lastSelection.value = item;
    emit('update:value', item.id, item);
    emit('option-select', Utils.string.camelCase(props.entity), item);

    return null;
};
function clearSelection() {
    emit('before-selection-clear', singleSelection.value, props.value);
    emit('update:value', null);
    emit('option-select', Utils.string.camelCase(props.entity), null);
}
const clearInput = () => {
    searchTerm.value = '';
    clearSelection();
    selectBase.value.collapse();
};
function resetActiveItem(pos = 0) {
    // Return if the result list is closed before the search request returns
    if (!resultsList.value) {
        return;
    }
    // If an item is selected the second entry is the first search result
    if (singleSelection.value) {
        pos = 1;
    }
    resultsList.value.setActiveItemIndex(pos);
}
const onInputSearchTerm = (event) => {
    const value = event.target.value;

    emit('search-term-change', value);
    debouncedSearch();
};
function getKey(object, keyPath, defaultValue) {
    return get(object, keyPath, defaultValue);
}
const isSelectionDisabled = (selection) => {
    if (props.disabled) {
        return true;
    }

    return props.selectionDisablingMethod(selection);
};
const getDisabledSelectionTooltip = (selection) => {
    return {
        ...props.disabledSelectionTooltip,
        disabled: props.disabledSelectionTooltip.disabled || !props.selectionDisablingMethod(selection),
    };
};
function createNewEntity() {
    const entity = repository.value.create(props.context);
    entity.name = newEntityName.value;
    repository.value
        .save(entity, props.context)
        .then(() => {
            lastSelection.value = entity;
            emit('update:value', entity.id, entity);
            emit('option-select', Utils.string.camelCase(props.entity), entity);
            createNotificationSuccess({
                message: t(
                    'global.ct-single-select.labelEntityAddedSuccess',
                    {
                        term: entity.name,
                        entity: props.entityCreationLabel,
                    },
                    0,
                ),
            });
        })
        .catch(() => {
            createNotificationError({
                message: t(
                    'global.notification.notificationSaveErrorMessage',
                    {
                        entityName: props.entity,
                    },
                    0,
                ),
            });
            Contena.Utils.debug.error('Only Entities with "name" as the only required field are creatable.');
            isLoading.value = false;
        });
}
function filterSearchGeneratedTags() {
    resultCollection.value = resultCollection.value.filter((entity) => {
        return entity.id !== -1;
    });
}
const openAdvancedSelectionModal = () => {
    isAdvancedSelectionModalVisible.value = true;
};
const closeAdvancedSelectionModal = () => {
    isAdvancedSelectionModalVisible.value = false;
};
const onAdvancedSelectionSubmit = (selectedItems) => {
    if (selectedItems.length > 0) {
        setValue(selectedItems[0]);
    }
};
const getActiveIconColor = (item) => {
    if (item?.active) {
        return item.active === true ? '#37d046' : '#d1d9e0';
    }

    return '#d1d9e0';
};

watch(
    () => props.value,
    (value) => {
        // No need to fetch again when the new value is the last one we selected
        if (lastSelection.value && props.value === lastSelection.value.id) {
            singleSelection.value = lastSelection.value;
            lastSelection.value = null;
            return;
        }

        if (value === '' || value === null) {
            singleSelection.value = null;
            return;
        }

        loadSelected();
    },
);

createdComponent();

swDefinePublic({
    repositoryFactory,
    searchTerm,
    isExpanded,
    resultCollection,
    singleSelection,
    isLoading,
    itemRecentlySelected,
    lastSelection,
    entityExists,
    newEntityName,
    isAdvancedSelectionModalVisible,
    inputClasses,
    selectionTextClasses,
    repository,
    results,
    isAdvancedSelectionActive,
    advancedSelectionInitialSearchTerm,
    createdComponent,
    loadSelected,
    createCollection,
    isSelected,
    debouncedSearch,
    search,
    handleSearchPromise,
    paginate,
    loadData,
    checkEntityExists,
    displaySearch,
    displayLabelProperty,
    onSelectExpanded,
    tryGetSearchText,
    onSelectCollapsed,
    closeResultList,
    setValue,
    addItem,
    clearSelection,
    clearInput,
    resetActiveItem,
    onInputSearchTerm,
    getKey,
    isSelectionDisabled,
    getDisabledSelectionTooltip,
    createNewEntity,
    filterSearchGeneratedTags,
    openAdvancedSelectionModal,
    closeAdvancedSelectionModal,
    onAdvancedSelectionSubmit,
    getActiveIconColor,
});

defineExpose({
    repositoryFactory,
    searchTerm,
    isExpanded,
    resultCollection,
    singleSelection,
    isLoading,
    itemRecentlySelected,
    lastSelection,
    entityExists,
    newEntityName,
    isAdvancedSelectionModalVisible,
    inputClasses,
    selectionTextClasses,
    repository,
    results,
    isAdvancedSelectionActive,
    advancedSelectionInitialSearchTerm,
    createdComponent,
    loadSelected,
    createCollection,
    isSelected,
    debouncedSearch,
    search,
    handleSearchPromise,
    paginate,
    loadData,
    checkEntityExists,
    displaySearch,
    displayLabelProperty,
    onSelectExpanded,
    tryGetSearchText,
    onSelectCollapsed,
    closeResultList,
    setValue,
    addItem,
    clearSelection,
    clearInput,
    resetActiveItem,
    onInputSearchTerm,
    getKey,
    isSelectionDisabled,
    getDisabledSelectionTooltip,
    createNewEntity,
    filterSearchGeneratedTags,
    openAdvancedSelectionModal,
    closeAdvancedSelectionModal,
    onAdvancedSelectionSubmit,
    getActiveIconColor,
});
</script>

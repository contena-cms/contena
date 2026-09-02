<template>
    <ct-block name="ct_entity_multi_select">
        <ct-select-base
            ref="selectBase"
            class="ct-entity-multi-select"
            :is-loading="isLoading"
            v-bind="$attrs"
            :label="label"
            :disabled="disabled"
            @select-expanded="onSelectExpanded"
            @select-collapsed="onSelectCollapsed"
            @clear="clearSelection"
        >
            <template #ct-select-selection="{ disabled, size }">
                <ct-block name="ct_entity_multi_select_base_selection">
                    <ct-block name="ct_entity_multi_select_advanced_selection_modal">
                        <component
                            :is="advancedSelectionComponent"
                            v-if="isAdvancedSelectionActive && isAdvancedSelectionModalVisible"
                            :initial-search-term="searchTerm"
                            :initial-selection="currentCollection"
                            v-bind="advancedSelectionParameters"
                            @modal-close="closeAdvancedSelectionModal"
                            @selection-submit="onAdvancedSelectionSubmit"
                        />
                    </ct-block>

                    <ct-block name="ct_entity_multi_select_base_selection_slot">
                        <ct-select-selection-list
                            ref="selectionList"
                            :selections="visibleValues"
                            :invisible-count="invisibleValueCount"
                            value-property="id"
                            v-bind="{
                                size,
                                labelProperty,
                                placeholder,
                                alwaysShowPlaceholder,
                                searchTerm,
                                disabled,
                                autocomplete,
                            }"
                            :input-label="label"
                            :hide-labels="hideLabels"
                            :selection-disabling-method="selectionDisablingMethod"
                            @total-count-click="expandValueLimit"
                            @item-remove="remove"
                            @last-item-delete="removeLastItem"
                            @search-term-change="onSearchTermChange"
                        >
                            <template #label-property="{ item, index, labelProperty, valueProperty, getKey }">
                                <ct-block name="ct_entity_multi_select_base_selection_list">
                                    <ct-block name="ct_entity_multi_select_base_selection_list_label">
                                        <ct-block name="ct_entity_multi_select_base_selection_list_label_inner">
                                            <slot
                                                name="selection-label-property"
                                                v-bind="{ item, index, labelProperty, valueProperty, getKey }"
                                            >
                                                {{ displayLabelProperty(item) }}
                                            </slot>
                                        </ct-block>
                                    </ct-block>
                                </ct-block>
                            </template>
                        </ct-select-selection-list>
                    </ct-block>
                </ct-block>
            </template>

            <template #results-list>
                <ct-block name="ct_entity_multi_select_base_results">
                    <ct-block name="ct_entity_multi_select_base_results_slot">
                        <ct-select-result-list
                            ref="ctSelectResultListRef"
                            :options="resultCollection"
                            :is-loading="isLoading"
                            :empty-message="$t('global.ct-entity-multi-select.messageNoResults', { term: searchTerm }, 0)"
                            :focus-el="selectionList?.getFocusEl()"
                            @paginate="paginate"
                            @item-select="addItem"
                        >
                            <template #before-item-list>
                                <ct-block name="ct_entity_multi_select_base_results_list_before">
                                    <ct-block name="ct_entity_multi_select_base_results_list_before_advanced_selection">
                                        <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
                                        <div
                                            v-if="isAdvancedSelectionActive"
                                            class="ct-multi-select-filtering__advanced-selection ct-select-result"
                                            @click="openAdvancedSelectionModal"
                                        >
                                            {{ $t('global.ct-entity-advanced-selection-modal.link') }}
                                        </div>
                                    </ct-block>

                                    <ct-block name="ct_entity_multi_select_base_results_list_before_slot">
                                        <slot name="before-item-list"></slot>
                                    </ct-block>
                                </ct-block>
                            </template>

                            <template #result-item="{ item, index }">
                                <ct-block name="ct_entity_multi_select_base_results_list_result">
                                    <slot
                                        name="result-item"
                                        v-bind="{
                                            item,
                                            index,
                                            labelProperty,
                                            valueProperty: 'id',
                                            searchTerm,
                                            highlightSearchTerm,
                                            isSelected,
                                            addItem,
                                            getKey,
                                            displayLabelProperty,
                                            isSelectionDisabled,
                                            descriptionPosition,
                                        }"
                                    >
                                        <ct-select-result
                                            :selected="isSelected(item)"
                                            v-bind="{ item, index }"
                                            :disabled="isSelectionDisabled(item)"
                                            :description-position="descriptionPosition"
                                            @item-select="addItem"
                                        >
                                            <template #preview>
                                                <ct-block name="ct_entity_multi_select_base_results_list_result_preview">
                                                    <slot
                                                        name="result-label-preview"
                                                        v-bind="{
                                                            item,
                                                            index,
                                                            labelProperty,
                                                            valueProperty: 'id',
                                                            searchTerm,
                                                            highlightSearchTerm,
                                                            getKey,
                                                        }"
                                                    ></slot>
                                                </ct-block>
                                            </template>
                                            <ct-block name="ct_entity_multi_select_base_results_list_result_label">
                                                <slot
                                                    name="result-label-property"
                                                    v-bind="{
                                                        item,
                                                        index,
                                                        labelProperty,
                                                        valueProperty: 'id',
                                                        searchTerm,
                                                        highlightSearchTerm,
                                                        getKey,
                                                    }"
                                                >
                                                    <ct-highlight-text
                                                        v-if="highlightSearchTerm"
                                                        :text="displayLabelProperty(item)"
                                                        :search-term="searchTerm"
                                                    />
                                                    <template v-else>
                                                        {{ displayLabelProperty(item) }}
                                                    </template>
                                                </slot>
                                            </ct-block>
                                            <template #description>
                                                <ct-block name="ct_entity_multi_select_base_results_list_result_description">
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
                                <ct-block name="ct_entity_multi_select_base_results_list_after">
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
import './ct-entity-multi-select.scss';
const { Component } = Contena;
const { debounce, get } = Contena.Utils;
const { EntityCollection } = Contena.Data;

defineOptions({ inheritAttrs: false });

const props = defineProps({
    labelProperty: {
        type: [
            String,
            Array,
        ],
        required: false,
        default: 'name',
    },

    resultLimit: {
        type: Number,
        required: false,
        default: 25,
    },

    valueLimit: {
        type: Number,
        required: false,
        default: 5,
    },

    placeholder: {
        type: String,
        required: false,
        default: '',
    },

    alwaysShowPlaceholder: {
        type: Boolean,
        required: false,
        default: false,
    },

    criteria: {
        type: Object,
        required: false,
        default(props) {
            return new Contena.Data.Criteria(1, props.resultLimit);
        },
    },

    disabled: {
        type: Boolean,
        required: false,
        default: undefined,
    },

    highlightSearchTerm: {
        type: Boolean,
        required: false,
        default: true,
    },

    entityCollection: {
        type: Array,
        required: true,
    },

    entityName: {
        type: String,
        required: false,
        default: null,
    },

    context: {
        type: Object,
        required: false,
        default() {
            return Contena.Context.api;
        },
    },

    hideLabels: {
        type: Boolean,
        required: false,
        default: false,
    },

    selectionDisablingMethod: {
        type: Function,
        required: false,
        default: () => false,
    },

    descriptionPosition: {
        type: String,
        required: false,
        default: 'right',
        validValues: [
            'bottom',
            'right',
        ],
        validator(value) {
            return [
                'bottom',
                'right',
            ].includes(value);
        },
    },

    advancedSelectionComponent: {
        type: String,
        required: false,
        default() {
            return '';
        },
    },

    advancedSelectionParameters: {
        type: Object,
        required: false,
        default() {
            return {};
        },
    },

    label: {
        type: String,
        required: false,
        default: undefined,
    },
    autocomplete: {
        type: String,
        required: false,
        default: undefined,
    },
});
const emit = defineEmits([
    'search',
    'update:entityCollection',
    'item-add',
    'item-remove',
    'display-values-expand',
    'search-term-change',
]);

import { ref, computed, inject, watch } from 'vue';

const ctSelectResultListRef = ref(null);
const selectionList = ref(null);
const selectBase = ref(null);

const repositoryFactory = inject('repositoryFactory');

const searchTerm = ref('');
const limit = ref(props.valueLimit);
const searchCriteria = ref(null);
const isLoading = ref(false);
const currentCollection = ref(null);
const resultCollection = ref(null);
const isAdvancedSelectionModalVisible = ref(false);

const repository = computed(() => {
    return repositoryFactory.create(props.entityName || props.entityCollection.entity);
});
const visibleValues = computed(() => {
    if (!currentCollection.value || currentCollection.value.length <= 0) {
        return [];
    }

    return currentCollection.value.slice(0, limit.value);
});
const totalValuesCount = computed(() => {
    if (currentCollection.value.length) {
        return currentCollection.value.length;
    }

    return 0;
});
const invisibleValueCount = computed(() => {
    if (!currentCollection.value) {
        return 0;
    }

    return Math.max(0, totalValuesCount.value - limit.value);
});
const isAdvancedSelectionActive = computed(() => {
    return props.advancedSelectionComponent && Component.getComponentRegistry().has(props.advancedSelectionComponent);
});

const createdComponent = () => {
    refreshCurrentCollection();
};
function refreshCurrentCollection() {
    if (props.entityCollection) {
        currentCollection.value = EntityCollection.fromCollection(props.entityCollection);
    }
}
const createEmptyCollection = () => {
    return new EntityCollection(
        props.entityCollection.source,
        props.entityCollection.entity,
        props.entityCollection.context,
        props.entityCollection.criteria,
    );
};
const isSelected = (item) => {
    return currentCollection.value.has(item.id);
};
const loadData = () => {
    isLoading.value = true;

    return repository.value.search(props.criteria, { ...props.context, inheritance: true }).then((result) => {
        displaySearch(result);

        isLoading.value = false;

        return result;
    });
};
const search = () => {
    if (props.criteria.term === searchTerm.value) {
        return Promise.resolve();
    }

    resetCriteria();
    resultCollection.value = null;

    const searchPromise = loadData().then((result) => {
        resetActiveItem();
        return result;
    });
    emit('search', searchPromise);

    return searchPromise;
};
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
}
const displayLabelProperty = (item) => {
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
function resetActiveItem() {
    if (ctSelectResultListRef.value) {
        ctSelectResultListRef.value.setActiveItemIndex(0);
    }
}
function resetCriteria() {
    props.criteria.setPage(1);
    props.criteria.setLimit(props.resultLimit);
    props.criteria.setTerm(searchTerm.value);
}
const paginate = () => {
    if (!resultCollection.value || resultCollection.value.total < props.criteria.page * props.criteria.limit) {
        return;
    }

    props.criteria.setPage(props.criteria.page + 1);

    loadData();
};
const emitChanges = (newCollection) => {
    emit('update:entityCollection', newCollection);
};
const addItem = (item) => {
    if (isSelected(item)) {
        remove(item);
        return;
    }

    emit('item-add', item);

    const newCollection = EntityCollection.fromCollection(currentCollection.value);
    newCollection.add(item);

    emitChanges(newCollection);

    selectionList.value.focus();
    selectionList.value.select();
};
function remove(item) {
    emit('item-remove', item);
    const newCollection = EntityCollection.fromCollection(currentCollection.value);
    newCollection.remove(item.id);
    emitChanges(newCollection);
}
const removeLastItem = () => {
    if (!currentCollection.value.length) {
        return;
    }

    if (invisibleValueCount.value > 0) {
        expandValueLimit();
        return;
    }

    const lastSelection = currentCollection.value[currentCollection.value.length - 1];
    remove(lastSelection);
};
const onSelectExpanded = () => {
    resetCriteria();
    resultCollection.value = null;

    loadData();

    selectionList.value.focus();
};
const onSelectCollapsed = () => {
    searchTerm.value = '';
    selectionList.value.blur();
};
function expandValueLimit() {
    emit('display-values-expand');
    limit.value += limit.value;
}
const onSearchTermChange = (term) => {
    searchTerm.value = term;
    emit('search-term-change', term);
    debouncedSearch();
};
const debouncedSearch = debounce(() => {
    search();
}, 400);
const resetResultCollection = () => {
    resultCollection.value = null;

    // Direct new search if the select field is expanded
    if (selectBase.value.expanded) {
        loadData();
    }
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
const openAdvancedSelectionModal = () => {
    isAdvancedSelectionModalVisible.value = true;
};
const closeAdvancedSelectionModal = () => {
    isAdvancedSelectionModalVisible.value = false;
};
const onAdvancedSelectionSubmit = (selectedItems) => {
    const newCollection = createEmptyCollection();

    selectedItems.forEach((item) => {
        newCollection.add(item);
    });

    emitChanges(newCollection);

    selectionList.value.focus();
    selectionList.value.select();
};
const clearSelection = () => {
    emitChanges(createEmptyCollection());
    searchTerm.value = '';
    selectionList.value.blur();
};

watch(
    () => props.entityCollection,
    () => {
        refreshCurrentCollection();
    },
);

createdComponent();

ctDefinePublic({
    repositoryFactory,
    searchTerm,
    limit,
    searchCriteria,
    isLoading,
    currentCollection,
    resultCollection,
    isAdvancedSelectionModalVisible,
    repository,
    visibleValues,
    totalValuesCount,
    invisibleValueCount,
    isAdvancedSelectionActive,
    createdComponent,
    refreshCurrentCollection,
    createEmptyCollection,
    isSelected,
    loadData,
    search,
    displaySearch,
    displayLabelProperty,
    resetActiveItem,
    resetCriteria,
    paginate,
    emitChanges,
    addItem,
    remove,
    removeLastItem,
    onSelectExpanded,
    onSelectCollapsed,
    expandValueLimit,
    onSearchTermChange,
    debouncedSearch,
    resetResultCollection,
    getKey,
    isSelectionDisabled,
    openAdvancedSelectionModal,
    closeAdvancedSelectionModal,
    onAdvancedSelectionSubmit,
    clearSelection,
});

defineExpose({
    repositoryFactory,
    searchTerm,
    limit,
    searchCriteria,
    isLoading,
    currentCollection,
    resultCollection,
    isAdvancedSelectionModalVisible,
    repository,
    visibleValues,
    totalValuesCount,
    invisibleValueCount,
    isAdvancedSelectionActive,
    createdComponent,
    refreshCurrentCollection,
    createEmptyCollection,
    isSelected,
    loadData,
    search,
    displaySearch,
    displayLabelProperty,
    resetActiveItem,
    resetCriteria,
    paginate,
    emitChanges,
    addItem,
    remove,
    removeLastItem,
    onSelectExpanded,
    onSelectCollapsed,
    expandValueLimit,
    onSearchTermChange,
    debouncedSearch,
    resetResultCollection,
    getKey,
    isSelectionDisabled,
    openAdvancedSelectionModal,
    closeAdvancedSelectionModal,
    onAdvancedSelectionSubmit,
    clearSelection,
});
</script>

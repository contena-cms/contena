<template>
    <ct-block name="sw_entity_advanced_selection_modal">
        <ct-modal
            class="ct-entity-advanced-selection-modal"
            v-bind="$attrs"
            variant="large"
            :title="modalTitle"
            @modal-close="$emit('modal-close')"
        >
            <ct-block name="sw_entity_advanced_selection_modal_content_card">
                <mt-card
                    class="ct-entity-advanced-selection-modal__content"
                    position-identifier="ct-entity-advanced-selection-modal-content"
                    :large="true"
                    :is-loading="isLoading"
                >
                    <template #toolbar>
                        <ct-block name="sw_entity_advanced_selection_modal_toolbar">
                            <ct-card-filter
                                class="ct-entity-advanced-selection-modal__card-filter"
                                :placeholder="$t('global.ct-entity-advanced-selection-modal.searchPlaceholder')"
                                :initial-search-term="initialSearchTerm"
                                @ct-card-filter-term-change="onSearch"
                            >
                                <template #filter>
                                    <div class="ct-entity-advanced-selection-modal__filter-list-button">
                                        <ct-block name="sw_entity_advanced_selection_modal_toolbar_filter_button">
                                            <mt-button
                                                size="small"
                                                variant="secondary"
                                                @click="filterWindowOpen = !filterWindowOpen"
                                            >
                                                <template #iconFront>
                                                    <mt-icon name="regular-filter-s" size="16px" />
                                                </template>

                                                <i
                                                    v-if="activeFilterNumber > 0"
                                                    class="ct-entity-advanced-selection-modal__filter-badge"
                                                >
                                                    {{ activeFilterNumber }}
                                                </i>

                                                {{ $t('global.ct-entity-advanced-selection-modal.filter') }}
                                            </mt-button>
                                        </ct-block>

                                        <ct-block name="sw_entity_advanced_selection_modal_toolbar_filter_panel">
                                            <ct-context-menu v-show="filterWindowOpen">
                                                <ct-block
                                                    name="sw_entity_advanced_selection_modal_toolbar_filter_panel_headline"
                                                >
                                                    <h3 class="ct-entity-advanced-selection-modal__filter-headline">
                                                        {{ $t('global.ct-entity-advanced-selection-modal.filter') }}
                                                    </h3>
                                                </ct-block>

                                                <ct-block
                                                    name="sw_entity_advanced_selection_modal_toolbar_filter_panel_filters"
                                                >
                                                    <div class="ct-entity-advanced-selection-modal__filter-panel">
                                                        <ct-filter-panel
                                                            ref="filterPanel"
                                                            class="ct-entity-advanced-selection-modal__filter-panel"
                                                            :entity="entityName"
                                                            :store-key="storeKey"
                                                            :active-filter-number="activeFilterNumber"
                                                            :filters="listFilters"
                                                            :defaults="defaultFilters"
                                                            @criteria-changed="updateCriteria"
                                                        />
                                                    </div>
                                                </ct-block>

                                                <ct-block
                                                    name="sw_entity_advanced_selection_modal_toolbar_filter_panel_footer"
                                                >
                                                    <div class="ct-entity-advanced-selection-modal__filter-footer">
                                                        <!-- eslint-disable-next-line vuejs-accessibility/interactive-supports-focus vuejs-accessibility/click-events-have-key-events -->
                                                        <a
                                                            role="button"
                                                            class="ct-entity-advanced-selection-modal__filter-reset"
                                                            @click="clearFilters"
                                                        >
                                                            {{
                                                                $t('global.ct-entity-advanced-selection-modal.resetFilters')
                                                            }}
                                                        </a>
                                                    </div>
                                                </ct-block>
                                            </ct-context-menu>
                                        </ct-block>
                                    </div>
                                </template>
                            </ct-card-filter>
                        </ct-block>
                    </template>

                    <template #grid>
                        <ct-block name="sw_entity_advanced_selection_modal_list_grid">
                            <ct-entity-advanced-selection-modal-grid
                                v-if="entities && entities.length"
                                class="ct-entity-advanced-selection-modal__grid"
                                :items="entities"
                                :columns="entityColumns"
                                :repository="entityRepository"
                                :full-page="true"
                                :plain-appearance="true"
                                :compact-mode="true"
                                :show-selection="true"
                                :show-actions="true"
                                :show-settings="true"
                                :is-loading="isLoading"
                                :allow-view="acl.can(`${entityName}.viewer`)"
                                :allow-edit="false"
                                :allow-delete="false"
                                :allow-inline-edit="false"
                                :allow-bulk-edit="false"
                                :disable-data-fetching="true"
                                :sort-by="sortBy"
                                :sort-direction="sortDirection"
                                :maximum-select-items="isSingleSelect ? 1 : null"
                                :pre-selection="currentSelection"
                                :is-record-selectable-callback="isRecordSelectableCallback"
                                @selection-change="onSelectionChange"
                                @column-sort="onSortColumn"
                                @page-change="onPageChange"
                            >
                                <!-- Re-expose essential column slots -->
                                <template v-for="column in previewColumns" #[`preview-${column.property}`]="slotData">
                                    <slot :name="`preview-${column.property}`" v-bind="slotData"></slot>
                                </template>

                                <template v-for="column in entityColumns" #[`column-${column.property}`]="slotData">
                                    <slot :name="`column-${column.property}`" v-bind="{ ...slotData, aggregations }"></slot>
                                </template>
                            </ct-entity-advanced-selection-modal-grid>
                        </ct-block>

                        <ct-block name="sw_entity_advanced_selection_modal_list_empty_state">
                            <template v-if="entities && entities.length"
                                ><!-- Keeps the conditional chain connected across ct-block. --></template
                            >
                            <mt-empty-state
                                v-else
                                class="ct-entity-advanced-selection-modal__empty-state"
                                :icon="emptyIcon"
                                :headline="$t('ct-empty-state.messageNoResultTitle')"
                                :description="$t('ct-empty-state.messageNoResultSublineSimple')"
                            />
                        </ct-block>
                    </template>
                </mt-card>
            </ct-block>

            <template #modal-footer>
                <ct-block name="sw_entity_advanced_selection_modal_button_cancel">
                    <mt-button
                        size="small"
                        class="ct-entity-advanced-selection-modal__button-cancel"
                        variant="secondary"
                        @click="$emit('modal-close')"
                    >
                        {{ $t('global.default.cancel') }}
                    </mt-button>
                </ct-block>

                <ct-block name="sw_entity_advanced_selection_modal_button_apply">
                    <mt-button
                        variant="primary"
                        size="small"
                        class="ct-entity-advanced-selection-modal__button-apply"
                        :disabled="isLoading"
                        @click="onApply"
                    >
                        {{ $t('global.ct-entity-advanced-selection-modal.applySelection') }}
                    </mt-button>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-entity-advanced-selection-modal.scss';
const { debounce } = Contena.Utils;
const { Criteria } = Contena.Data;

const props = defineProps({
    entityName: {
        type: String,
        required: true,
    },
    // Translated entity name to display in the modal title.
    entityDisplayText: {
        type: String,
        required: true,
    },
    // A unique identifier for this kind of advanced selection.
    // The same uniquely configured modal for a single entity can have the same key.
    // It is passed to the ct-filter-panel and ct-entity-listing to retrieve user configured data
    // like visible columns, column order and the last filters that were applied.
    storeKey: {
        type: String,
        required: true,
    },
    // An array of column information. This is passed to the 'columns' property of the ct-entity-listing.
    entityColumns: {
        type: Array,
        required: true,
    },
    // A key-value object containing all the possible filter definitions under a unique identifier.
    // This is passed to the `filters` property of the ct-filter-panel after
    // a call to filterFactory.create(...)
    entityFilters: {
        type: Object,
        required: true,
    },
    // Meteor icon name that is used as an Icon for the empty state.
    emptyIcon: {
        type: String,
        required: false,
        default: 'solid-content',
    },
    // Additional associations which can't be inferred from the entityColumns or entityFilters.
    // This is most likely needed if the column slots are used for custom rendering and usage of associations.
    entityAssociations: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },
    isSingleSelect: {
        type: Boolean,
        required: false,
        default: false,
    },
    // Callback functions which receives one item of the entity and returns true or false,
    // depending on if the corresponding grid row should be selectable.
    // This is passed to the 'is-record-selectable-callback' property of the ct-entity-advanced-selection-modal-grid.
    isRecordSelectableCallback: {
        type: Function,
        required: false,
        // by default no callback function should be provided to the ct-entity-advanced-selection-modal-grid
        default: undefined,
    },
    // Additional criteria filters that should always apply.
    criteriaFilters: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },
    criteriaAggregations: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },
    // Custom context which is used for the search requests. If none is specified the default API context is used.
    entityContext: {
        type: Object,
        required: false,
        default() {
            return Contena.Context.api;
        },
    },
    // Optional search term which should be applied to the search field.
    initialSearchTerm: {
        type: String,
        required: false,
        default() {
            return '';
        },
    },
    // An array containing the already selected items.
    initialSelection: {
        type: Array,
        required: false,
        default() {
            return [];
        },
    },
    disablePreviews: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'modal-close',
    'selection-submit',
]);

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useListing } from 'src/app/composables/use-listing';

const { t } = useI18n();
const {
    page,
    limit,
    total,
    sortBy: sortBy2,
    sortDirection,
    naturalSorting,
    term,
    onPageChange,
    onSearch,
    onSortColumn,
    initializeListing,
} = useListing();
const sortBy = sortBy2;

const filterPanel = ref(null);

const acl = inject('acl');
const repositoryFactory = inject('repositoryFactory');
const filterFactory = inject('filterFactory');
const filterService = inject('filterService');

const isLoading = ref(true);
const entities = ref([]);
const aggregations = ref([]);
const currentSelection = ref({});
const filterCriteria = ref([]);
const disableRouteParams = ref(true);
const filterWindowOpen = ref(false);

const modalTitle = computed(() => {
    return t(
        'global.ct-entity-advanced-selection-modal.title',
        {
            entity: props.entityDisplayText,
        },
        1,
    );
});
const entityRepository = computed(() => {
    return repositoryFactory.create(props.entityName);
});
const entityDefinition = computed(() => {
    return Contena.EntityDefinition.get(props.entityName);
});
const assignmentProperties = computed(() => {
    const properties = [];

    Object.entries(entityDefinition.value.properties).forEach(
        ([
            propertyName,
            property,
        ]) => {
            if (property.relation === 'many_to_many' || property.relation === 'one_to_many') {
                properties.push(propertyName);
            }
        },
    );

    return properties;
});
const allEntityAssociations = computed(() => {
    // add all custom associations which might be needed in the template slots
    const allAssociations = new Set(props.entityAssociations);

    // get associations from property usage in entityColumns
    props.entityColumns.forEach((column) => {
        if (column.property && column.property.includes('.')) {
            const propertyDotIndex = column.property.lastIndexOf('.');
            allAssociations.add(column.property.slice(0, propertyDotIndex));
        }
    });

    // get associations from property usage in entityFilters
    Object.values(props.entityFilters).forEach((filter) => {
        if (filter.property && filter.property.includes('.')) {
            const propertyDotIndex = filter.property.lastIndexOf('.');
            allAssociations.add(filter.property.slice(0, propertyDotIndex));
        }
    });

    return allAssociations;
});
// Criteria is a local mutable query object, not component state.
const entityCriteria = computed(() => {
    // basic pagination + search criteria setup
    const defaultCriteria = new Criteria(page.value, limit.value);
    defaultCriteria.setTerm(term.value);

    if (sortBy2.value) {
        sortBy2.value.split(',').forEach((sortByValue) => {
            const sorting = Criteria.sort(sortByValue, sortDirection.value, naturalSorting.value);
            if (assignmentProperties.value.includes(sortBy2.value)) {
                sorting.field += '.id';
                sorting.type = 'count';
            }
            defaultCriteria.addSorting(sorting);
        });
    }

    // add all associations which are either provided or needed by the columns or filters
    allEntityAssociations.value.forEach((association) => {
        defaultCriteria.addAssociation(association);
    });

    // add custom filters which should always apply
    props.criteriaFilters.forEach((filter) => {
        defaultCriteria.addFilter(filter);
    });

    // add selected filters
    filterCriteria.value.forEach((filter) => {
        defaultCriteria.addFilter(filter);
    });

    // add aggregations
    props.criteriaAggregations.forEach((aggregation) => {
        defaultCriteria.addAggregation(aggregation);
    });

    return defaultCriteria;
});
const activeFilterNumber = computed(() => {
    return filterCriteria.value.length;
});
const defaultFilters = computed(() => {
    return Object.keys(props.entityFilters);
});
const listFilters = computed(() => {
    return filterFactory.create(props.entityName, props.entityFilters);
});
const previewColumns = computed(() => {
    if (props.disablePreviews) {
        return [];
    }

    return props.entityColumns;
});
const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});

const createdComponent = () => {
    isLoading.value = true;
    term.value = `${props.initialSearchTerm}`;
    props.initialSelection.forEach((selection) => {
        currentSelection.value[selection.id] = selection;
    });

    filterService.getStoredCriteria(props.storeKey).then((criteria) => {
        filterCriteria.value.push(...criteria);
        isLoading.value = false;
        return getList();
    });
};
async function getList() {
    if (isLoading.value) {
        // don't fetch if still in loading state
        // (for example on component creation the stored filter criteria must first be fetched)
        return Promise.resolve();
    }
    isLoading.value = true;
    return entityRepository.value
        .search(entityCriteria.value, props.entityContext)
        .then((items) => {
            total.value = items.total;
            entities.value = items;
            aggregations.value = items.aggregations;
            isLoading.value = false;
            return items;
        })
        .catch(() => {
            isLoading.value = false;
        });
}
const onSelectionChange = (selection) => {
    currentSelection.value = selection;
};
const onApply = () => {
    const items = Object.values(currentSelection.value);

    emit('selection-submit', items);
    emit('modal-close');
};
const updateCriteria = (criteria) => {
    page.value = 1;
    filterCriteria.value = criteria;

    debouncedGetList();
};
const debouncedGetList = debounce(() => {
    void getList();
}, 400);
const clearFilters = () => {
    filterPanel.value.resetAll();
};

initializeListing({
    getList,
    disableRouteParams,
    filterCriteria,
});

createdComponent();

swDefinePublic({
    acl,
    repositoryFactory,
    filterFactory,
    filterService,
    isLoading,
    entities,
    aggregations,
    currentSelection,
    filterCriteria,
    disableRouteParams,
    filterWindowOpen,
    modalTitle,
    entityRepository,
    entityDefinition,
    assignmentProperties,
    allEntityAssociations,
    entityCriteria,
    activeFilterNumber,
    defaultFilters,
    listFilters,
    previewColumns,
    assetFilter,
    createdComponent,
    getList,
    onSelectionChange,
    onApply,
    updateCriteria,
    debouncedGetList,
    clearFilters,
});

defineExpose({
    acl,
    repositoryFactory,
    filterFactory,
    filterService,
    isLoading,
    entities,
    aggregations,
    currentSelection,
    filterCriteria,
    disableRouteParams,
    filterWindowOpen,
    modalTitle,
    entityRepository,
    entityDefinition,
    assignmentProperties,
    allEntityAssociations,
    entityCriteria,
    activeFilterNumber,
    defaultFilters,
    listFilters,
    previewColumns,
    assetFilter,
    createdComponent,
    getList,
    onSelectionChange,
    onApply,
    updateCriteria,
    debouncedGetList,
    clearFilters,
});
</script>

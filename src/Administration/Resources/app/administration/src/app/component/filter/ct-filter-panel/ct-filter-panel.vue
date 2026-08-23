<template>
    <ct-block name="sw_filter_panel">
        <div class="ct-filter-panel">
            <ct-block name="sw_filter_panel_item">
                <div v-for="filter in listFilters" :id="filter.name" :key="filter.name" class="ct-filter-panel__item">
                    <ct-block name="sw_filter_panel_extension_point"></ct-block>

                    <ct-boolean-filter
                        v-if="showFilter(filter, 'boolean-filter')"
                        :filter="filter"
                        :active="!!activeFilters[filter.name]"
                        @filter-update="updateFilter"
                        @filter-reset="resetFilter"
                    />

                    <ct-existence-filter
                        v-else-if="showFilter(filter, 'existence-filter')"
                        :filter="filter"
                        :active="!!activeFilters[filter.name]"
                        @filter-update="updateFilter"
                        @filter-reset="resetFilter"
                    />

                    <ct-multi-select-filter
                        v-else-if="showFilter(filter, 'multi-select-filter')"
                        :filter="filter"
                        :active="!!activeFilters[filter.name]"
                        @filter-update="updateFilter"
                        @filter-reset="resetFilter"
                    >
                        <template v-if="filter.displayPath" #selection-label-property="{ item }">
                            <ct-block name="sw_multi_select_filter_content_path_label">
                                <ct-highlight-text
                                    :key="item.id"
                                    v-tooltip="{
                                        message: getBreadcrumb(item),
                                        width: 300,
                                    }"
                                    selected=""
                                    :text="getLabelName(item)"
                                />
                            </ct-block>
                        </template>

                        <template v-if="filter.displayPath" #result-item="{ item, index }">
                            <ct-block name="sw_multi_select_filter_content_path_result_item">
                                <ct-select-result v-bind="{ item, index }">
                                    <ct-block name="sw_multi_select_filter_content_path_result_label">
                                        <span class="ct-select-result__result-item-text">
                                            <ct-highlight-text
                                                :key="item.id"
                                                v-tooltip="{
                                                    message: getBreadcrumb(item),
                                                    width: 300,
                                                }"
                                                selected=""
                                                :text="getLabelName(item)"
                                            />
                                        </span>
                                    </ct-block>
                                </ct-select-result>
                            </ct-block>
                        </template>
                    </ct-multi-select-filter>

                    <ct-date-filter
                        v-else-if="showFilter(filter, 'date-filter')"
                        :filter="filter"
                        :active="!!activeFilters[filter.name]"
                        :config="filter.config"
                        @filter-update="updateFilter"
                        @filter-reset="resetFilter"
                    />

                    <ct-string-filter
                        v-else-if="showFilter(filter, 'string-filter')"
                        :filter="filter"
                        :active="!!activeFilters[filter.name]"
                        :criteria-filter-type="filter.criteriaFilterType"
                        @filter-update="updateFilter"
                        @filter-reset="resetFilter"
                    />

                    <ct-number-filter
                        v-else-if="showFilter(filter, 'number-filter')"
                        :filter="filter"
                        :active="!!activeFilters[filter.name]"
                        :step="filter.step"
                        :digits="filter.digits"
                        :number-type="filter.numberType"
                        :min="filter.min"
                        :max="filter.max"
                        allow-empty
                        @filter-update="updateFilter"
                        @filter-reset="resetFilter"
                    />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-filter-panel.scss';

const props = defineProps({
    filters: {
        type: Array,
        required: true,
    },

    defaults: {
        type: Array,
        required: true,
    },

    storeKey: {
        type: String,
        required: true,
    },
});
const emit = defineEmits(['criteria-changed']);

import { ref, computed, inject, watch } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const repositoryFactory = inject('repositoryFactory');

const activeFilters = ref({});
const filterChanged = ref(false);
const storedFilters = ref(null);
const storedFiltersRequestId = ref(0);

const criteria = computed(() => {
    const filters = [];

    Object.values(activeFilters.value).forEach((activeFilter) => {
        filters.push(...activeFilter);
    });

    return filters;
});
const isFilterActive = computed(() => {
    return activeFiltersNumber.value > 0;
});
const activeFiltersNumber = computed(() => {
    return Object.keys(activeFilters.value).length;
});
const listFilters = computed(() => {
    const savedFilters = { ...storedFilters.value };
    const filters = [];

    props.filters.forEach((el) => {
        const filter = { ...el };

        filter.value = savedFilters[filter.name] ? savedFilters[filter.name].value : null;
        filter.filterCriteria = savedFilters[filter.name] ? savedFilters[filter.name].criteria : null;

        filters.push(filter);
    });

    return filters;
});

const createdComponent = () => {
    const requestId = storedFiltersRequestId.value + 1;
    storedFiltersRequestId.value = requestId;

    Contena.Service('filterService')
        .getStoredFilters(props.storeKey)
        .then((filters) => {
            if (requestId !== storedFiltersRequestId.value || filterChanged.value) {
                return;
            }

            activeFilters.value = {};
            storedFilters.value = filters ?? {};

            listFilters.value.forEach((filter) => {
                const criteria = storedFilters.value[filter.name] ? storedFilters.value[filter.name].criteria : null;
                if (criteria) {
                    activeFilters.value[filter.name] = criteria;
                }
            });
        });
};
const updateFilter = (name, filter, value) => {
    filterChanged.value = true;
    storedFilters.value = storedFilters.value ?? {};

    activeFilters.value[name] = filter;
    storedFilters.value[name] = { value: value, criteria: filter };
};
const resetFilter = (name) => {
    filterChanged.value = true;
    storedFilters.value = storedFilters.value ?? {};

    delete activeFilters.value[name];
    storedFilters.value[name] = { value: null, criteria: null };
};
const resetAll = () => {
    filterChanged.value = true;
    storedFilters.value = storedFilters.value ?? {};
    activeFilters.value = {};

    Object.values(storedFilters.value).forEach((el) => {
        el.value = null;
        el.criteria = null;
    });
};
const showFilter = (filter, type) => {
    return filter.type === type && props.defaults.includes(filter.name);
};
const getBreadcrumb = (item) => {
    if (item.breadcrumb?.length > 0) {
        return item.breadcrumb.join(' / ');
    }
    return item.translated?.name || item.name;
};
const getLabelName = (item) => {
    if (item.breadcrumb && item.breadcrumb.length > 1) {
        return `.. / ${item.translated?.name || item.name} `;
    }

    return item.translated?.name || item.name;
};

watch(
    () => criteria.value,
    () => {
        if (filterChanged.value) {
            Contena.Service('filterService')
                .saveFilters(props.storeKey, storedFilters.value)
                .then((response) => {
                    storedFilters.value = response;
                    emit('criteria-changed', criteria.value);
                });
        }
    },
    { deep: true },
);
watch(
    () => ({ ...route, params: { ...route.params }, query: { ...route.query } }),
    () => {
        filterChanged.value = false;
        createdComponent();
    },
);

createdComponent();

swDefinePublic({
    repositoryFactory,
    activeFilters,
    filterChanged,
    storedFilters,
    storedFiltersRequestId,
    criteria,
    isFilterActive,
    activeFiltersNumber,
    listFilters,
    createdComponent,
    updateFilter,
    resetFilter,
    resetAll,
    showFilter,
    getBreadcrumb,
    getLabelName,
});

defineExpose({
    repositoryFactory,
    activeFilters,
    filterChanged,
    storedFilters,
    storedFiltersRequestId,
    criteria,
    isFilterActive,
    activeFiltersNumber,
    listFilters,
    createdComponent,
    updateFilter,
    resetFilter,
    resetAll,
    showFilter,
    getBreadcrumb,
    getLabelName,
});
</script>

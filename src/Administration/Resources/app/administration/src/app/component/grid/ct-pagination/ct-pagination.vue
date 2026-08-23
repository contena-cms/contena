<template>
    <ct-block name="sw_pagination">
        <div v-if="shouldBeVisible" class="ct-pagination">
            <ct-block name="sw_pagination_page_prev">
                <button
                    :disabled="currentPage === 1"
                    :aria-label="$t('global.ct-pagination.previousPage')"
                    class="ct-pagination__page-button ct-pagination__page-button-prev"
                    @click="prevPage"
                >
                    <mt-icon name="regular-chevron-left-xs" />
                </button>
            </ct-block>

            <ct-block name="sw_pagination_page_list">
                <ul class="ct-pagination__list">
                    <ct-block name="sw_pagination_page_list_item">
                        <li v-for="(pageNum, index) in displayedPages" :key="index" class="ct-pagination__list-item">
                            <ct-block name="sw_pagination_page_list_item_button">
                                <button
                                    v-if="typeof pageNum === 'number'"
                                    :aria-label="$t('global.ct-pagination.page', { page: pageNum })"
                                    class="ct-pagination__list-button"
                                    :class="{ 'is-active': currentPage === pageNum }"
                                    @click="changePageByPageNumber(pageNum)"
                                >
                                    {{ pageNum }}
                                </button>
                            </ct-block>

                            <ct-block name="sw_pagination_page_list_item_separator">
                                <template v-if="typeof pageNum === 'number'"
                                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                                >
                                <span v-else class="ct-pagination__list-separator">
                                    {{ pageNum }}
                                </span>
                            </ct-block>
                        </li>
                    </ct-block>
                </ul>
            </ct-block>

            <ct-block name="sw_pagination_page_next">
                <button
                    :disabled="currentPage === maxPage"
                    :aria-label="$t('global.ct-pagination.nextPage')"
                    class="ct-pagination__page-button ct-pagination__page-button-next"
                    @click="nextPage"
                >
                    <mt-icon name="regular-chevron-right-xs" size="16px" />
                </button>
            </ct-block>

            <ct-block name="sw_pagination_per_page_selection">
                <div v-if="steps.length > 1" class="ct-pagination__per-page">
                    <mt-select
                        size="small"
                        name="perPage"
                        :label="$t('global.ct-pagination.labelItemsPerPage')"
                        :model-value="String(perPage)"
                        :options="possibleStepsOptions"
                        hide-clearable-button
                        @update:model-value="onPageSizeChange"
                    />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-pagination.scss';

const props = defineProps({
    total: {
        type: Number,
        required: true,
    },

    limit: {
        type: Number,
        required: true,
    },

    page: {
        type: Number,
        required: true,
    },

    totalVisible: {
        type: Number,
        required: false,
        default: 7,
    },

    steps: {
        type: Array,
        required: false,
        default() {
            return [
                10,
                25,
                50,
                75,
                100,
            ];
        },
    },

    autoHide: {
        type: Boolean,
        required: false,
        default: true,
    },
});
const emit = defineEmits(['page-change']);

import { ref, computed, watch } from 'vue';

const currentPage = ref(props.page);
const perPage = ref(props.limit);

const maxPage = computed(() => {
    return Math.ceil(props.total / perPage.value);
});
const displayedPages = computed(() => {
    const maxLength = props.totalVisible;
    const selectedPage = currentPage.value;

    if (maxPage.value <= maxLength) {
        return range(1, maxPage.value);
    }

    const even = maxLength % 2 === 0 ? 1 : 0;
    const left = Math.floor(maxLength / 2);
    const right = maxPage.value - left + 1 + even;

    if (selectedPage === left || (left === 1 && selectedPage === left + 1)) {
        return [
            ...range(1, left + 1),
            '...',
            ...range(right, maxPage.value),
        ];
    }

    if (selectedPage === right || (right === maxPage.value && selectedPage === maxPage.value - 1)) {
        return [
            ...range(1, left),
            '...',
            ...range(right - 1, maxPage.value),
        ];
    }

    if (selectedPage > left && selectedPage < right) {
        const start = selectedPage - left + 2;
        const end = selectedPage + left - 2 - even;

        return [
            1,
            '...',
            ...(start > end ? [selectedPage] : range(start, end)),
            '...',
            maxPage.value,
        ];
    }

    return [
        ...range(1, left),
        '...',
        ...range(right, maxPage.value),
    ];
});
const shouldBeVisible = computed(() => {
    if (!props.autoHide) {
        return true;
    }

    return props.total > Math.min(...props.steps);
});
const possibleSteps = computed(() => {
    const total = props.total;
    const stepsSorted = [...props.steps].sort((a, b) => a - b);

    let lastStep;
    const possibleSteps = stepsSorted.filter((x) => {
        if (lastStep > total) return false;
        lastStep = x;
        return true;
    });

    return possibleSteps;
});
const possibleStepsOptions = computed(() => {
    return possibleSteps.value.map((step) => {
        return {
            value: String(step),
            label: String(step),
        };
    });
});

function range(from, to) {
    const range = [];
    from = from > 0 ? from : 1;
    for (let i = from; i <= to; i += 1) {
        range.push(i);
    }
    return range;
}
const pageChange = () => {
    emit('page-change', {
        page: currentPage.value,
        limit: perPage.value,
    });
};
const onPageSizeChange = (selectedLimit) => {
    perPage.value = Number(selectedLimit);
    firstPage();
};
function firstPage() {
    currentPage.value = 1;
    pageChange();
}
const prevPage = () => {
    currentPage.value -= 1;
    pageChange();
};
const nextPage = () => {
    currentPage.value += 1;
    pageChange();
};
const lastPage = () => {
    currentPage.value = maxPage.value;
    pageChange();
};
const changePageByPageNumber = (pageNum) => {
    currentPage.value = pageNum;
    pageChange();
};
const refresh = () => {
    pageChange();
};

watch(
    () => props.page,
    () => {
        currentPage.value = props.page;
    },
);
watch(
    () => props.limit,
    () => {
        perPage.value = props.limit;
    },
);
watch(
    () => maxPage.value,
    () => {
        if (maxPage.value === 0) {
            currentPage.value = 1;
            return;
        }

        if (currentPage.value > maxPage.value) {
            changePageByPageNumber(maxPage.value);
        }
    },
);

swDefinePublic({
    currentPage,
    perPage,
    maxPage,
    displayedPages,
    shouldBeVisible,
    possibleSteps,
    possibleStepsOptions,
    range,
    pageChange,
    onPageSizeChange,
    firstPage,
    prevPage,
    nextPage,
    lastPage,
    changePageByPageNumber,
    refresh,
});

defineExpose({
    currentPage,
    perPage,
    maxPage,
    displayedPages,
    shouldBeVisible,
    possibleSteps,
    possibleStepsOptions,
    range,
    pageChange,
    onPageSizeChange,
    firstPage,
    prevPage,
    nextPage,
    lastPage,
    changePageByPageNumber,
    refresh,
});
</script>

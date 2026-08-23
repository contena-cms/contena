<template>
    <ct-block name="sw_extension_my_extensions_listing_controls">
        <div class="ct-extension-my-extensions-listing-controls">
            <ct-block name="sw_extension_my_extensions_listing_controls_active_switch">
                <mt-switch
                    v-model="filterByActiveState"
                    class="ct-extension-my-extensions-listing-controls__active-filter-switch"
                    :label="$t('ct-extension.my-extensions.listing.controls.labelActiveStateSwitch')"
                />
            </ct-block>

            <ct-block name="sw_extension_my_extensions_listing_controls_sorting_select">
                <mt-select
                    v-model="selectedSortingOption"
                    class="ct-extension-my-extensions-listing-controls__sorting-dropdown"
                    small
                    :options="sortingOptions"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-extension-my-extensions-listing-controls.scss';

const props = defineProps({
    sortingOption: {
        type: String,
        default: 'updated-at',
    },
});
const emit = defineEmits([
    'update:active-state',
    'update:sorting-option',
]);

import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const filterByActiveState = ref(false);
const selectedSortingOption = ref(props.sortingOption);
const sortingOptions = ref([
    {
        id: 1,
        value: 'updated-at',
        label: t('ct-extension.my-extensions.listing.controls.filterOptions.last-updated'),
    },
    {
        id: 2,
        value: 'name-asc',
        label: t('ct-extension.my-extensions.listing.controls.filterOptions.name-asc'),
    },
    {
        id: 3,
        value: 'name-desc',
        label: t('ct-extension.my-extensions.listing.controls.filterOptions.name-desc'),
    },
]);

watch(
    () => props.sortingOption,
    (value) => {
        selectedSortingOption.value = value;
    },
);
watch(
    () => filterByActiveState.value,
    (value) => {
        emit('update:active-state', value);
    },
);
watch(
    () => selectedSortingOption.value,
    (value) => {
        if (value !== props.sortingOption) {
            emit('update:sorting-option', value);
        }
    },
);

swDefinePublic({
    filterByActiveState,
    selectedSortingOption,
    sortingOptions,
});

defineExpose({
    filterByActiveState,
    selectedSortingOption,
    sortingOptions,
});
</script>

<template>
    <ct-block name="sw_media_display_options">
        <div v-if="inline" class="ct-media-display-options__inline">
            <ct-block name="sw_media_display_options_sorting">
                <mt-select
                    name="sortType"
                    small
                    :options="sortOptionsSelect"
                    :label="$t('ct-media.sorting.labelSort')"
                    class="ct-media-display-options__label-sort"
                    :model-value="sortingConCat"
                    :disabled="disabled"
                    @update:model-value="onSortingChanged"
                />
            </ct-block>

            <ct-block name="sw_media_display_options_presentation">
                <div v-if="!hidePresentation" class="ct-media-display-options__view-buttons">
                    <mt-button
                        class="ct-media-display-options__view-button"
                        :class="{ 'is--active': presentation !== 'list-preview' }"
                        variant="secondary"
                        size="small"
                        square
                        :aria-label="$t('ct-media.presentation.labelGridView')"
                        :disabled="disabled"
                        @click="onPresentationChanged('medium-preview')"
                    >
                        <span class="ct-media-display-options__grid-icon" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                            <span></span>
                        </span>
                    </mt-button>

                    <mt-button
                        class="ct-media-display-options__view-button"
                        :class="{ 'is--active': presentation === 'list-preview' }"
                        variant="secondary"
                        size="small"
                        square
                        :aria-label="$t('ct-media.presentation.labelListView')"
                        :disabled="disabled"
                        @click="onPresentationChanged('list-preview')"
                    >
                        <mt-icon name="regular-bars-s" size="16px" />
                    </mt-button>
                </div>
            </ct-block>
        </div>

        <template v-else>
            <ct-block name="sw_media_display_options_presentation">
                <mt-select
                    v-if="!hidePresentation"
                    name="presentation"
                    small
                    :options="presentationOptions"
                    :label="$t('ct-media.presentation.labelPresentation')"
                    class="ct-media-display-options__label-presentation"
                    :model-value="presentation"
                    :disabled="disabled"
                    @update:model-value="onPresentationChanged"
                />
            </ct-block>

            <ct-block name="sw_media_display_options_sorting">
                <mt-select
                    name="sortType"
                    small
                    :options="sortOptionsSelect"
                    :label="$t('ct-media.sorting.labelSort')"
                    class="ct-media-display-options__label-sort"
                    :model-value="sortingConCat"
                    :disabled="disabled"
                    @update:model-value="onSortingChanged"
                />
            </ct-block>
        </template>
    </ct-block>
</template>

<script setup>
const props = defineProps({
    presentation: {
        type: String,
        required: false,
        default: 'medium-preview',
        validValues: [
            'small-preview',
            'medium-preview',
            'large-preview',
            'list-preview',
        ],
        validator(value) {
            return [
                'small-preview',
                'medium-preview',
                'large-preview',
                'list-preview',
            ].includes(value);
        },
    },

    sorting: {
        type: Object,
        required: false,
        default: () => ({
            sortBy: 'createdAt',
            sortDirection: 'desc',
        }),
    },

    hidePresentation: {
        type: Boolean,
        required: false,
        default: false,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    inline: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'media-sorting-change',
    'media-presentation-change',
]);

import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const sortingConCat = computed(() => {
    return `${props.sorting.sortBy}:${props.sorting.sortDirection}`;
});
const sortOptions = computed(() => {
    return [
        {
            value: 'createdAt:asc',
            name: t('ct-media.sorting.labelSortByCreatedAsc'),
        },
        {
            value: 'createdAt:desc',
            name: t('ct-media.sorting.labelSortByCreatedDsc'),
        },
        {
            value: 'fileName:asc',
            name: t('ct-media.sorting.labelSortByNameAsc'),
        },
        {
            value: 'fileName:desc',
            name: t('ct-media.sorting.labelSortByNameDsc'),
        },
        {
            value: 'fileSize:asc',
            name: t('ct-media.sorting.labelSortBySizeAsc'),
        },
        {
            value: 'fileSize:desc',
            name: t('ct-media.sorting.labelSortBySizeDsc'),
        },
    ];
});
const previewOptions = computed(() => {
    return [
        {
            value: 'small-preview',
            name: t('ct-media.presentation.labelPresentationSmall'),
        },
        {
            value: 'medium-preview',
            name: t('ct-media.presentation.labelPresentationMedium'),
        },
        {
            value: 'large-preview',
            name: t('ct-media.presentation.labelPresentationLarge'),
        },
        {
            value: 'list-preview',
            name: t('ct-media.presentation.labelPresentationList'),
        },
    ];
});
const presentationOptions = computed(() => {
    return (
        previewOptions.value?.map((item) => {
            return {
                id: item.value,
                value: item.value,
                label: item.name,
            };
        }) ?? []
    );
});
const sortOptionsSelect = computed(() => {
    return sortOptions.value.map((item) => {
        return {
            id: item.value,
            value: item.value,
            label: item.name,
        };
    });
});

const onSortingChanged = (value) => {
    const parts = value.split(':');
    emit('media-sorting-change', {
        sortBy: parts[0],
        sortDirection: parts[1],
    });
};
const onPresentationChanged = (value) => {
    emit('media-presentation-change', value);
};

swDefinePublic({
    sortingConCat,
    sortOptions,
    previewOptions,
    presentationOptions,
    sortOptionsSelect,
    onSortingChanged,
    onPresentationChanged,
});

defineExpose({
    sortingConCat,
    sortOptions,
    previewOptions,
    presentationOptions,
    sortOptionsSelect,
    onSortingChanged,
    onPresentationChanged,
});
</script>

<template>
    <ct-block name="ct_data_grid_settings">
        <ct-context-button
            v-tooltip="{ message: $t('global.ct-data-grid.tooltipListSettings') }"
            :disabled="disabled"
            :menu-width="300"
            :auto-close="false"
            :auto-close-outside-click="true"
            :additional-context-menu-classes="contextMenuClasses"
        >
            <template #button>
                <ct-block name="ct_data_grid_settings_trigger">
                    <mt-button
                        class="ct-data-grid-settings__trigger"
                        :disabled="disabled"
                        :aria-label="$t('global.ct-data-grid.tooltipListSettings')"
                        size="x-small"
                        square
                        variant="secondary"
                    >
                        <ct-block name="ct_data_grid_settings_trigger_icon">
                            <mt-icon name="regular-bars-s" size="14px" />
                        </ct-block>
                    </mt-button>
                </ct-block>
            </template>

            <ct-block name="ct_data_grid_settings_content">
                <ct-block name="ct_data_grid_settings_general">
                    <div class="ct-data-grid__settings-container">
                        <ct-block name="ct_data_grid_settings_compact_switch">
                            <mt-switch
                                :model-value="currentCompact"
                                remove-top-margin
                                :label="$t('global.ct-data-grid.labelSettingsCompactMode')"
                                @update:model-value="onChangeCompactMode"
                            />
                        </ct-block>

                        <ct-block name="ct_data_grid_settings_preview_switch">
                            <mt-switch
                                v-if="enablePreviews"
                                :model-value="currentPreviews"
                                remove-top-margin
                                :label="$t('global.ct-data-grid.labelSettingsPreviewImages')"
                                @update:model-value="onChangePreviews"
                            />
                        </ct-block>

                        <ct-block name="ct_data_grid_settings_additional_settings">
                            <slot name="additionalSettings">
                                <ct-block name="ct_data_grid_settings_additional_settings_slot"></ct-block>
                            </slot>
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="ct_data_grid_settings_devider">
                    <ct-context-menu-divider />
                </ct-block>

                <ct-block name="ct_data_grid_settings_columns">
                    <transition-group
                        name="columns-list"
                        tag="div"
                        class="ct-data-grid__settings-container ct-data-grid__settings-column-list"
                    >
                        <ct-block name="ct_data_grid_settings_column_element">
                            <div
                                v-for="(column, index) in currentColumns"
                                :key="column.property"
                                :class="['ct-data-grid__settings-column-item', 'ct-data-grid__settings-item--' + index]"
                            >
                                <ct-block name="ct_data_grid_settings_column_visibility_checkbox">
                                    <mt-checkbox
                                        :disabled="column.primary"
                                        :label="getColumnLabel(column)"
                                        :checked="currentColumns[index].visible"
                                        @update:checked="onChangeColumnVisibility($event, index)"
                                    />
                                </ct-block>

                                <ct-block name="ct_data_grid_settings_column_item_conrols">
                                    <ct-button-group
                                        v-if="currentColumns.length >= 2"
                                        class="ct-data-grid__settings-column-item-controls"
                                    >
                                        <ct-block name="ct_data_grid_settings_column_control_up">
                                            <mt-button
                                                size="x-small"
                                                square
                                                :disabled="index === 0"
                                                variant="secondary"
                                                @click="onClickChangeColumnOrderUp(column)"
                                            >
                                                <ct-block name="ct_data_grid_settings_column_control_up_icon">
                                                    <mt-icon name="regular-chevron-up-xxs" size="14px" />
                                                </ct-block>
                                            </mt-button>
                                        </ct-block>

                                        <ct-block name="ct_data_grid_settings_column_control_down">
                                            <mt-button
                                                size="x-small"
                                                square
                                                :disabled="index === currentColumns.length - 1"
                                                class="down"
                                                variant="secondary"
                                                @click="onClickChangeColumnOrderDown(column)"
                                            >
                                                <ct-block name="ct_data_grid_settings_column_control_down_icon">
                                                    <mt-icon name="regular-chevron-down-xxs" size="14px" />
                                                </ct-block>
                                            </mt-button>
                                        </ct-block>
                                    </ct-button-group>
                                </ct-block>
                            </div>
                        </ct-block>
                    </transition-group>
                </ct-block>
            </ct-block>
        </ct-context-button>
    </ct-block>
</template>

<script setup>
import './ct-data-grid-settings.scss';

const props = defineProps({
    columns: {
        type: Array,
        default() {
            return [];
        },
        required: true,
    },
    compact: {
        type: Boolean,
        required: true,
        default: false,
    },
    previews: {
        type: Boolean,
        required: true,
        default: false,
    },
    enablePreviews: {
        type: Boolean,
        required: true,
        default: false,
    },
    disabled: {
        type: Boolean,
        required: true,
        default: false,
    },
});
const emit = defineEmits([
    'change-compact-mode',
    'change-preview-images',
    'change-column-visibility',
    'change-column-order',
]);

import { ref, computed, watch } from 'vue';
import { useTranslateWithFallback } from 'src/app/composables/use-translate-with-fallback';

const { tWithFallback } = useTranslateWithFallback();

const currentCompact = ref(props.compact);
const currentPreviews = ref(props.previews);
const currentColumns = ref(props.columns);

const contextMenuClasses = computed(() => {
    return {
        'ct-data-grid-settings': true,
    };
});

const onChangeCompactMode = (value) => {
    currentCompact.value = value;
    emit('change-compact-mode', value);
};
const onChangePreviews = (value) => {
    currentPreviews.value = value;
    emit('change-preview-images', value);
};
const onChangeColumnVisibility = (value, index) => {
    emit('change-column-visibility', value, index);
};
const onClickChangeColumnOrderUp = (column) => {
    const columnIndex = currentColumns.value.findIndex((col) => col.property === column.property);

    emit('change-column-order', columnIndex, columnIndex - 1);
};
const onClickChangeColumnOrderDown = (column) => {
    const columnIndex = currentColumns.value.findIndex((col) => col.property === column.property);

    emit('change-column-order', columnIndex, columnIndex + 1);
};
const getColumnLabel = (column) => {
    return tWithFallback(column.label);
};

watch(
    () => props.columns,
    () => {
        currentColumns.value = props.columns;
    },
);
watch(
    () => props.compact,
    () => {
        currentCompact.value = props.compact;
    },
);
watch(
    () => props.previews,
    () => {
        currentPreviews.value = props.previews;
    },
);

ctDefinePublic({
    currentCompact,
    currentPreviews,
    currentColumns,
    contextMenuClasses,
    onChangeCompactMode,
    onChangePreviews,
    onChangeColumnVisibility,
    onClickChangeColumnOrderUp,
    onClickChangeColumnOrderDown,
    getColumnLabel,
});

defineExpose({
    currentCompact,
    currentPreviews,
    currentColumns,
    contextMenuClasses,
    onChangeCompactMode,
    onChangePreviews,
    onChangeColumnVisibility,
    onClickChangeColumnOrderUp,
    onClickChangeColumnOrderDown,
    getColumnLabel,
});
</script>

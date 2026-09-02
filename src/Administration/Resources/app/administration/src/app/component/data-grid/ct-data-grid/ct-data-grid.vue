<template>
    <ct-block name="ct_data_grid">
        <div class="ct-data-grid" :class="classes">
            <ct-block name="ct_data_grid_wrapper">
                <div class="ct-data-grid__wrapper">
                    <ct-block name="ct_data_grid_bulk">
                        <div v-if="selectionCount > 0" class="ct-data-grid__bulk">
                            <ct-block name="ct_data_grid_bulk_selected_count">
                                <span class="ct-data-grid__bulk-selected ct-data-grid__bulk-selected-label">{{
                                    $t('global.ct-data-grid.labelSelectionCount')
                                }}</span>
                                <span class="ct-data-grid__bulk-selected ct-data-grid__bulk-selected-count">{{
                                    selectionCount
                                }}</span>
                            </ct-block>

                            <ct-block name="ct_data_grid_bulk_selected_actions">
                                <span class="ct-data-grid__bulk-selected bulk-link">
                                    <a
                                        v-if="hasInvisibleSelection"
                                        class="link link-warning bulk-deselect-all"
                                        role="button"
                                        tabindex="0"
                                        @click="resetSelection"
                                        @keydown.enter="resetSelection"
                                    >
                                        {{ $t('global.ct-data-grid.labelDeSelectAll') }}
                                    </a>
                                    <slot name="bulk">
                                        <ct-block name="ct_data_grid_bulk_selected_actions_content"></ct-block>
                                    </slot>
                                </span>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="ct_data_grid_bulk_modals">
                        <slot name="bulk-modals" v-bind="{ selection }">
                            <ct-block name="ct_data_grid_slot_bulk_modals"></ct-block>
                        </slot>
                    </ct-block>

                    <ct-block name="ct_data_grid_table">
                        <table class="ct-data-grid__table">
                            <ct-block name="ct_data_grid_header">
                                <thead v-if="showHeader" class="ct-data-grid__header">
                                    <ct-block name="ct_data_grid_header_row">
                                        <tr class="ct-data-grid__row">
                                            <ct-block name="ct_data_grid_header_cell_selection">
                                                <th
                                                    v-if="showSelection"
                                                    class="ct-data-grid__cell ct-data-grid__cell--header ct-data-grid__cell--selection"
                                                >
                                                    <ct-block name="ct_data_grid_header_cell_selection_content">
                                                        <div class="ct-data-grid__cell-content">
                                                            <ct-block name="ct_data_grid_select_all_checkbox">
                                                                <mt-checkbox
                                                                    v-if="records && records.length > 0"
                                                                    v-tooltip="{
                                                                        message: $t(
                                                                            'global.ct-data-grid.maximumSelectionExceed',
                                                                        ),
                                                                        disabled: !reachMaximumSelectionExceed,
                                                                        showOnDisabledElements: true,
                                                                    }"
                                                                    :aria-label="
                                                                        $t(
                                                                            allSelectedChecked
                                                                                ? 'global.ct-data-grid.labelDeSelectAll'
                                                                                : 'global.ct-data-grid.labelSelectAll',
                                                                        )
                                                                    "
                                                                    :disabled="isSelectAllDisabled"
                                                                    class="ct-data-grid__select-all"
                                                                    :model-value="allSelectedChecked"
                                                                    @update:model-value="selectAll"
                                                                />
                                                            </ct-block>
                                                        </div>
                                                    </ct-block>
                                                </th>
                                            </ct-block>

                                            <ct-block name="ct_data_grid_header_columns">
                                                <th
                                                    v-for="(column, columnIndex) in currentColumns"
                                                    v-show="column.visible"
                                                    :key="`${column.property}-${columnIndex}`"
                                                    class="ct-data-grid__cell ct-data-grid__cell--header ct-data-grid__cell--property"
                                                    :class="getHeaderCellClasses(column, columnIndex)"
                                                    :style="{ width: column.width, minWidth: column.width }"
                                                    @click="onClickHeaderCell($event, column)"
                                                >
                                                    <ct-block name="ct_data_grid_header_columns_content">
                                                        <div class="ct-data-grid__cell-content">
                                                            <slot
                                                                :name="`column-label-${column.property}`"
                                                                v-bind="{ column, columnIndex }"
                                                            >
                                                                <ct-block name="ct_data_grid_header_columns_icon">
                                                                    <mt-icon
                                                                        v-if="column.iconLabel && column.iconTooltip"
                                                                        v-tooltip="column.iconTooltip"
                                                                        :name="column.iconLabel"
                                                                        :size="column.iconSize"
                                                                    >
                                                                        {{ getColumnLabel(column) }}
                                                                    </mt-icon>

                                                                    <mt-icon
                                                                        v-else-if="column.iconLabel"
                                                                        :name="column.iconLabel"
                                                                        :size="column.iconSize"
                                                                    >
                                                                        {{ getColumnLabel(column) }}
                                                                    </mt-icon>
                                                                </ct-block>

                                                                <ct-block name="ct_data_grid_header_columns_label">
                                                                    <template
                                                                        v-if="
                                                                            (column.iconLabel && column.iconTooltip) ||
                                                                            column.iconLabel
                                                                        "
                                                                        ><!-- Keeps the conditional chain connected across ct-block. --></template
                                                                    >
                                                                    <template v-else>
                                                                        {{ getColumnLabel(column) }}
                                                                    </template>
                                                                </ct-block>
                                                            </slot>

                                                            <ct-block name="ct_data_grid_column_actions">
                                                                <ct-context-button
                                                                    v-if="allowColumnEdit && !isInlineEditActive"
                                                                    class="ct-data-grid__action-edit-column"
                                                                    aria-label="global.ct-data-grid.columnsActions"
                                                                >
                                                                    <ct-block name="ct_data_grid_column_actions_hide">
                                                                        <ct-context-menu-item
                                                                            :disabled="column.primary"
                                                                            variant="danger"
                                                                            @click="hideColumn(columnIndex)"
                                                                        >
                                                                            {{ $t('global.ct-data-grid.labelColumnHide') }}
                                                                        </ct-context-menu-item>
                                                                    </ct-block>
                                                                </ct-context-button>
                                                            </ct-block>

                                                            <ct-block name="ct_data_grid_sort_indicator">
                                                                <span
                                                                    v-if="
                                                                        column.sortable && currentSortBy === column.dataIndex
                                                                    "
                                                                    class="ct-data-grid__sort-indicator"
                                                                >
                                                                    <ct-block name="ct_data_grid_sort_indicator_transition">
                                                                        <transition name="sort-indicator" mode="out-in">
                                                                            <template v-if="currentSortDirection === 'ASC'">
                                                                                <ct-block
                                                                                    name="ct_data_grid_sort_indicator_icon_asc"
                                                                                >
                                                                                    <mt-icon
                                                                                        key="ASC"
                                                                                        name="regular-chevron-up-xxs"
                                                                                        size="16px"
                                                                                    />
                                                                                </ct-block>
                                                                            </template>
                                                                            <template v-else>
                                                                                <ct-block
                                                                                    name="ct_data_grid_sort_indicator_icon_desc"
                                                                                >
                                                                                    <mt-icon
                                                                                        key="DESC"
                                                                                        name="regular-chevron-down-xxs"
                                                                                        size="16px"
                                                                                    />
                                                                                </ct-block>
                                                                            </template>
                                                                        </transition>
                                                                    </ct-block>
                                                                </span>
                                                            </ct-block>
                                                        </div>
                                                    </ct-block>

                                                    <ct-block name="ct_data_grid_header_columns_resize">
                                                        <!-- eslint-disable-next-line vuejs-accessibility/no-static-element-interactions -->
                                                        <span
                                                            v-if="column.allowResize"
                                                            class="ct-data-grid__cell-resize"
                                                            @mousedown="onStartResize($event, column, columnIndex)"
                                                        ></span>
                                                    </ct-block>
                                                </th>
                                            </ct-block>

                                            <ct-block name="ct_data_grid_header_cell_spacer">
                                                <th
                                                    v-if="hasResizeColumns"
                                                    aria-hidden="true"
                                                    class="ct-data-grid__cell ct-data-grid__cell--header ct-data-grid__cell-spacer"
                                                >
                                                    <div class="ct-data-grid__cell-content"></div>
                                                </th>
                                            </ct-block>

                                            <ct-block name="ct_data_grid_header_cell_actions">
                                                <th
                                                    v-if="showActions"
                                                    class="ct-data-grid__cell ct-data-grid__cell--header ct-data-grid__cell--actions ct-data-grid__cell-settings"
                                                    :class="{ 'is--loading': loading }"
                                                >
                                                    <ct-block name="ct_data_grid_header_cell_actions_content">
                                                        <div class="ct-data-grid__cell-content">
                                                            <ct-block name="ct_data_grid_settings">
                                                                <ct-data-grid-settings
                                                                    v-if="showSettings"
                                                                    :columns="currentColumns"
                                                                    :compact="compact"
                                                                    :previews="previews"
                                                                    :enable-previews="hasPreviewSlots"
                                                                    :disabled="isInlineEditActive"
                                                                    @change-compact-mode="onChangeCompactMode"
                                                                    @change-preview-images="onChangePreviews"
                                                                    @change-column-visibility="onChangeColumnVisibility"
                                                                    @change-column-order="onChangeColumnOrder"
                                                                >
                                                                    <template #additionalSettings>
                                                                        <ct-block
                                                                            name="ct_data_grid_settings_additional_settings"
                                                                        >
                                                                            <ct-block
                                                                                name="ct_data_grid_settings_additional_settings_inner"
                                                                            >
                                                                                <slot name="additionalSettings"></slot>
                                                                            </ct-block>
                                                                        </ct-block>
                                                                    </template>
                                                                </ct-data-grid-settings>

                                                                <ct-block name="ct_data_grid_settings_custom_settings">
                                                                    <slot name="customSettings">
                                                                        <ct-block
                                                                            name="ct_data_grid_settings_custom_settings_slot"
                                                                        ></ct-block>
                                                                    </slot>
                                                                </ct-block>
                                                            </ct-block>
                                                        </div>
                                                    </ct-block>
                                                </th>
                                            </ct-block>
                                        </tr>
                                    </ct-block>
                                </thead>
                            </ct-block>

                            <ct-block name="ct_data_grid_body">
                                <tbody v-if="!loading" class="ct-data-grid__body">
                                    <ct-block name="ct_data_grid_body_row">
                                        <tr
                                            v-for="(item, itemIndex) in records"
                                            :key="item.id"
                                            class="ct-data-grid__row"
                                            :class="getRowClasses(item, itemIndex)"
                                            @click="onRowClick($event, item)"
                                        >
                                            <ct-block name="ct_data_grid_body_cell_selection">
                                                <td
                                                    v-if="showSelection"
                                                    class="ct-data-grid__cell ct-data-grid__cell--selection"
                                                >
                                                    <slot
                                                        name="selection-content"
                                                        v-bind="{
                                                            item,
                                                            isSelected,
                                                            isRecordSelectable: recordIsSelectable,
                                                            selectItem,
                                                            itemIdentifierProperty,
                                                        }"
                                                    >
                                                        <ct-block name="ct_data_grid_body_cell_selection_content">
                                                            <div class="ct-data-grid__cell-content">
                                                                <ct-block name="ct_data_grid_select_item_checkbox">
                                                                    <mt-checkbox
                                                                        v-tooltip="{
                                                                            message: $t(
                                                                                'global.ct-data-grid.maximumSelectionExceed',
                                                                            ),
                                                                            disabled: !(
                                                                                reachMaximumSelectionExceed &&
                                                                                !isSelected(item[itemIdentifierProperty])
                                                                            ),
                                                                            showOnDisabledElements: true,
                                                                        }"
                                                                        :aria-label="$t('global.ct-data-grid.labelSelected')"
                                                                        :disabled="!recordIsSelectable(item)"
                                                                        :model-value="
                                                                            isSelected(item[itemIdentifierProperty])
                                                                        "
                                                                        @update:model-value="selectItem($event, item)"
                                                                    />
                                                                </ct-block>
                                                            </div>
                                                        </ct-block>
                                                    </slot>
                                                </td>
                                            </ct-block>

                                            <ct-block name="ct_data_grid_body_columns">
                                                <td
                                                    v-for="(column, columnIndex) in currentVisibleColumns"
                                                    :key="`${item.id}-${columnIndex}`"
                                                    class="ct-data-grid__cell"
                                                    :class="getCellClasses(column)"
                                                    role="gridcell"
                                                    @dblclick="onDbClickCell(item)"
                                                >
                                                    <ct-provide :aria-label="column.label">
                                                        <ct-block name="ct_data_grid_body_columns_content">
                                                            <div class="ct-data-grid__cell-content">
                                                                <ct-block name="ct_data_grid_preview_slot">
                                                                    <slot
                                                                        v-if="previews && !isInlineEdit(item)"
                                                                        :name="`preview-${column.property}`"
                                                                        v-bind="{ item, column, compact }"
                                                                    ></slot>
                                                                </ct-block>

                                                                <ct-block name="ct_data_grid_columns_slot">
                                                                    <slot
                                                                        :name="`column-${column.property}`"
                                                                        v-bind="{
                                                                            item,
                                                                            itemIndex,
                                                                            column,
                                                                            columnIndex,
                                                                            compact,
                                                                            isInlineEdit:
                                                                                isInlineEdit(item) &&
                                                                                column.hasOwnProperty('inlineEdit'),
                                                                            selectItem,
                                                                        }"
                                                                    >
                                                                        <template v-if="column.inlineEdit === 'boolean'">
                                                                            <ct-block name="ct_data_grid_columns_boolean">
                                                                                <ct-data-grid-column-boolean
                                                                                    v-model:value="item[column.property]"
                                                                                    :is-inline-edit="
                                                                                        isInlineEdit(item) &&
                                                                                        column.hasOwnProperty('inlineEdit')
                                                                                    "
                                                                                />
                                                                            </ct-block>
                                                                        </template>
                                                                        <template v-else>
                                                                            <ct-block
                                                                                name="ct_data_grid_columns_inline_edit"
                                                                            >
                                                                                <template
                                                                                    v-if="
                                                                                        isInlineEdit(item) &&
                                                                                        column.hasOwnProperty('inlineEdit')
                                                                                    "
                                                                                >
                                                                                    <ct-block
                                                                                        name="ct_data_grid_columns_render_inline_edit"
                                                                                    >
                                                                                        <ct-data-grid-inline-edit
                                                                                            v-model:value="
                                                                                                item[column.property]
                                                                                            "
                                                                                            :column="column"
                                                                                            :compact="compact"
                                                                                        />
                                                                                    </ct-block>
                                                                                </template>
                                                                            </ct-block>

                                                                            <ct-block name="ct_data_grid_columns_value">
                                                                                <template
                                                                                    v-if="
                                                                                        isInlineEdit(item) &&
                                                                                        column.hasOwnProperty('inlineEdit')
                                                                                    "
                                                                                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                                                                                >
                                                                                <template v-else>
                                                                                    <ct-block
                                                                                        name="ct_data_grid_columns_render_router_link"
                                                                                    >
                                                                                        <router-link
                                                                                            v-if="column.routerLink"
                                                                                            class="ct-data-grid__cell-value"
                                                                                            :to="{
                                                                                                name: column.routerLink,
                                                                                                params: { id: item.id },
                                                                                            }"
                                                                                        >
                                                                                            {{ renderColumn(item, column) }}
                                                                                        </router-link>
                                                                                    </ct-block>
                                                                                    <ct-block
                                                                                        name="ct_data_grid_columns_render_value"
                                                                                    >
                                                                                        <template v-if="column.routerLink"
                                                                                            ><!-- Keeps the conditional chain connected across ct-block. --></template
                                                                                        >
                                                                                        <span
                                                                                            v-else
                                                                                            class="ct-data-grid__cell-value"
                                                                                        >
                                                                                            {{ renderColumn(item, column) }}
                                                                                        </span>
                                                                                    </ct-block>
                                                                                </template>
                                                                            </ct-block>
                                                                        </template>
                                                                    </slot>
                                                                </ct-block>
                                                            </div>
                                                        </ct-block>
                                                    </ct-provide>
                                                </td>
                                            </ct-block>

                                            <ct-block name="ct_data_grid_body_cell_spacer">
                                                <td
                                                    v-if="hasResizeColumns"
                                                    aria-hidden="true"
                                                    class="ct-data-grid__cell ct-data-grid__cell-spacer"
                                                    @dblclick="onDbClickCell(item)"
                                                >
                                                    <div class="ct-data-grid__cell-content"></div>
                                                </td>
                                            </ct-block>

                                            <ct-block name="ct_data_grid_body_cell_actions">
                                                <td
                                                    v-if="showActions"
                                                    class="ct-data-grid__cell ct-data-grid__cell--actions"
                                                >
                                                    <ct-block name="ct_data_grid_body_cell_actions_content">
                                                        <div class="ct-data-grid__cell-content">
                                                            <ct-block name="ct_data_grid_inline_edit_actions">
                                                                <template
                                                                    v-if="
                                                                        isInlineEditActive &&
                                                                        currentInlineEditId === item[itemIdentifierProperty]
                                                                    "
                                                                >
                                                                    <ct-block name="ct_data_grid_inline_edit_actions_cancel">
                                                                        <mt-button
                                                                            class="ct-data-grid__inline-edit-cancel"
                                                                            size="x-small"
                                                                            :title="$t('global.default.cancel')"
                                                                            :aria-label="$t('global.default.cancel')"
                                                                            square
                                                                            variant="secondary"
                                                                            @click="onClickCancelInlineEdit(item)"
                                                                        >
                                                                            <ct-block
                                                                                name="ct_data_grid_inline_edit_actions_cancel_icon"
                                                                            >
                                                                                <mt-icon
                                                                                    name="regular-times-xs"
                                                                                    size="10px"
                                                                                />
                                                                            </ct-block>
                                                                        </mt-button>
                                                                    </ct-block>

                                                                    <ct-block name="ct_data_grid_inline_edit_actions_save">
                                                                        <mt-button
                                                                            class="ct-data-grid__inline-edit-save"
                                                                            variant="primary"
                                                                            size="x-small"
                                                                            :title="$t('global.default.save')"
                                                                            :aria-label="$t('global.default.save')"
                                                                            square
                                                                            @click="onClickSaveInlineEdit(item)"
                                                                        >
                                                                            <ct-block
                                                                                name="ct_data_grid_inline_edit_actions_save_icon"
                                                                            >
                                                                                <mt-icon
                                                                                    name="regular-checkmark-xxs"
                                                                                    size="10px"
                                                                                />
                                                                            </ct-block>
                                                                        </mt-button>
                                                                    </ct-block>
                                                                </template>
                                                            </ct-block>

                                                            <ct-block name="ct_data_grid_body_cell_actions_menu">
                                                                <template
                                                                    v-if="
                                                                        isInlineEditActive &&
                                                                        currentInlineEditId === item[itemIdentifierProperty]
                                                                    "
                                                                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                                                                >
                                                                <ct-context-button
                                                                    v-else
                                                                    :menu-width="contextButtonMenuWidth"
                                                                    class="ct-data-grid__actions-menu"
                                                                    aria-label="global.ct-data-grid.actionsMenu"
                                                                >
                                                                    <slot
                                                                        name="actions"
                                                                        :item="item"
                                                                        :item-index="itemIndex"
                                                                    >
                                                                        <ct-block
                                                                            name="ct_data_grid_slot_actions"
                                                                        ></ct-block>
                                                                    </slot>
                                                                </ct-context-button>
                                                            </ct-block>

                                                            <ct-block name="ct_data_grid_body_cell_action_modals">
                                                                <slot name="action-modals" :item="item">
                                                                    <ct-block
                                                                        name="ct_data_grid_slot_action_modals"
                                                                    ></ct-block>
                                                                </slot>
                                                            </ct-block>
                                                        </div>
                                                    </ct-block>
                                                </td>
                                            </ct-block>
                                        </tr>
                                    </ct-block>
                                </tbody>
                            </ct-block>

                            <ct-block name="ct_data_grid_skeleton">
                                <template v-if="!loading"
                                    ><!-- Keeps the conditional chain connected across ct-block. --></template
                                >
                                <ct-data-grid-skeleton
                                    v-else
                                    :show-selection="showSelection"
                                    :show-actions="showActions"
                                    :current-columns="currentColumns"
                                    :has-resize-columns="hasResizeColumns"
                                    :item-amount="skeletonItemAmount"
                                    class="ct-data-grid__body"
                                />
                            </ct-block>
                        </table>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_data_grid_pagination">
                <div class="ct-data-grid__pagination">
                    <ct-block name="ct_data_grid_pagination_inner">
                        <slot name="pagination">
                            <ct-block name="ct_data_grid_slot_pagination"></ct-block>
                        </slot>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-data-grid.scss';
const { Criteria } = Contena.Data;
const utils = Contena.Utils;

const props = defineProps({
    dataSource: {
        type: Array,
        required: true,
    },

    columns: {
        type: Array,
        required: true,
    },

    identifier: {
        type: String,
        required: false,
        default: '',
    },

    showSelection: {
        type: Boolean,
        default: true,
        required: false,
    },

    showActions: {
        type: Boolean,
        default: true,
        required: false,
    },

    showHeader: {
        type: Boolean,
        default: true,
        required: false,
    },

    showSettings: {
        type: Boolean,
        default: false,
        required: false,
    },

    fullPage: {
        type: Boolean,
        default: false,
        required: false,
    },

    allowInlineEdit: {
        type: Boolean,
        default: false,
        required: false,
    },

    allowColumnEdit: {
        type: Boolean,
        default: false,
        required: false,
    },

    isLoading: {
        type: Boolean,
        default: false,
        required: false,
    },

    skeletonItemAmount: {
        type: Number,
        required: false,
        default: 7,
    },

    sortBy: {
        type: String,
        required: false,
        default: null,
    },

    sortDirection: {
        type: String,
        required: false,
        default: 'ASC',
    },

    naturalSorting: {
        type: Boolean,
        required: false,
        default: false,
    },

    compactMode: {
        type: Boolean,
        required: false,
        default: true,
    },

    plainAppearance: {
        type: Boolean,
        required: false,
        default: false,
    },

    showPreviews: {
        type: Boolean,
        required: false,
        default: true,
    },

    isRecordEditable: {
        type: Function,
        required: false,
        default() {
            return true;
        },
    },

    isRecordSelectable: {
        type: Function,
        required: false,
        default: null,
    },

    rowsClickable: {
        type: Boolean,
        required: false,
        default: false,
    },

    itemIdentifierProperty: {
        type: String,
        required: false,
        default: 'id',
    },

    maximumSelectItems: {
        type: Number,
        required: false,
        default: null,
    },

    preSelection: {
        type: Object,
        required: false,
        default: null,
    },

    isRecordDisabled: {
        type: Function,
        required: false,
        default() {
            return false;
        },
    },

    contextButtonMenuWidth: {
        type: Number,
        required: false,
        default: 220,
    },
});
const emit = defineEmits([
    'selection-change',
    'select-all-items',
    'select-item',
    'inline-edit-assign',
    'inline-edit-save',
    'inline-edit-cancel',
    'column-sort',
    'row-click',
]);

import { ref, computed, inject, watch, useSlots, getCurrentInstance, onMounted, onBeforeUnmount } from 'vue';
import { useTranslateWithFallback } from 'src/app/composables/use-translate-with-fallback';

const slots = useSlots();
const { tWithFallback } = useTranslateWithFallback();

const componentInstance = getCurrentInstance();

const getGridRootElement = () => {
    const rootElement = componentInstance?.proxy?.$el;

    if (!rootElement) {
        return null;
    }

    return rootElement.matches?.('.ct-data-grid') ? rootElement : (rootElement.querySelector?.('.ct-data-grid') ?? null);
};
const getGridWrapperElement = () => getGridRootElement()?.querySelector('.ct-data-grid__wrapper') ?? null;
const getGridTableElement = () => getGridRootElement()?.querySelector('.ct-data-grid__table') ?? null;
const getGridColumnElements = () =>
    getGridRootElement()?.querySelectorAll('.ct-data-grid__cell--header.ct-data-grid__cell--property') ?? [];

const acl = inject('acl');
const repositoryFactory = inject('repositoryFactory');
const records = ref(props.dataSource);
const currentSortBy = ref(props.sortBy);
const currentSortDirection = ref(props.sortDirection);
const currentNaturalSorting = ref(props.naturalSorting);
const loading = ref(props.isLoading);
const currentSetting = ref({});
const currentColumns = ref([]);
const columnIndex = ref(null);
const selection = ref({ ...(props.preSelection || {}) });
const originalTarget = ref(null);
const compact = ref(props.compactMode);
const previews = ref(props.showPreviews);
const isInlineEditActive = ref(false);
const currentInlineEditId = ref('');
const hasPreviewSlots = ref(false);
const hasResizeColumns = ref(false);
const hasColumnsResizeState = ref(false);
const isResizing = ref(false);
const resizeX = ref(null);
const currentColumnWidth = ref(null);

const classes = computed(() => {
    return {
        'is--compact': compact.value,
        'ct-data-grid--full-page': props.fullPage,
        'ct-data-grid--actions': props.showActions,
        'ct-data-grid--plain-appearance': props.plainAppearance,
    };
});
const selectionCount = computed(() => {
    return Object.values(selection.value).length;
});
const reachMaximumSelectionExceed = computed(() => {
    if (!props.maximumSelectItems) {
        return false;
    }

    return selectionCount.value >= props.maximumSelectItems;
});
const recordIsSelectable = (item) => {
    if (props.isRecordSelectable) {
        return props.isRecordSelectable(item);
    }

    return !reachMaximumSelectionExceed.value || Object.keys(selection.value).includes(item[props.itemIdentifierProperty]);
};
const isSelectAllDisabled = computed(() => {
    if (!props.maximumSelectItems) {
        return false;
    }

    // When the selection maximum is reached, selecting every record is no longer possible,
    // so the select-all header checkbox is disabled (a tooltip explains why on hover).
    return reachMaximumSelectionExceed.value;
});
const allSelectedChecked = computed(() => {
    if (isSelectAllDisabled.value) {
        return false;
    }

    if (!records.value || records.value.length === 0) {
        return false;
    }

    if (selectionCount.value < records.value.length) {
        return false;
    }

    const selectedItems = Object.values(selection.value);

    return records.value.every((item) => {
        return selectedItems.some((selectionValue) => {
            return selectionValue[props.itemIdentifierProperty] === item[props.itemIdentifierProperty];
        });
    });
});
const userConfigRepository = computed(() => {
    return repositoryFactory.create('user_config');
});
const currentUser = computed(() => {
    return Contena.Store.get('session').currentUser;
});
const userGridSettingCriteria = computed(() => {
    const criteria = new Criteria(1, 25);
    const configurationKey = `grid.setting.${props.identifier}`;
    criteria.addFilter(Criteria.equals('key', configurationKey));
    criteria.addFilter(Criteria.equals('userId', currentUser.value && currentUser.value.id));

    return criteria;
});
const hasInvisibleSelection = computed(() => {
    if (!records.value) {
        return false;
    }

    const currentVisibleIds = records.value.map((record) => record.id);
    return selectionCount.value > 0 && Object.keys(selection.value).some((id) => !currentVisibleIds.includes(id));
});
const currentVisibleColumns = computed(() => {
    return currentColumns.value.filter((column) => column.visible);
});

const createdComponent = () => {
    initGridColumns();
};
const mountedComponent = () => {
    trackScrollX();
    findPreviewSlots();

    const mountedComponentInstance = getCurrentInstance()?.proxy;
    mountedComponentInstance?.$device.onResize({
        listener: trackScrollX,
        component: mountedComponentInstance,
    });
};
function initGridColumns() {
    currentColumns.value = getDefaultColumns();
    findResizeColumns();
    if (!props.identifier) {
        return;
    }
    findUserSetting();
}
function findUserSetting() {
    if (!acl.can('user_config:read')) {
        return Promise.resolve();
    }
    return userConfigRepository.value.search(userGridSettingCriteria.value, Contena.Context.api).then((response) => {
        if (!response.length) {
            return;
        }
        currentSetting.value = response[0];
        const userSetting = response[0].value;
        applyUserSettings({
            columns: userSetting?.columns ?? userSetting,
            compact: userSetting?.compact,
            previews: userSetting?.previews,
        });
    });
}
const findUserSettingById = () => {
    return userConfigRepository.value.get(currentSetting.value.id, Contena.Context.api).then((response) => {
        if (!response) {
            return;
        }

        currentSetting.value = response;
        const userSetting = response.value;

        applyUserSettings({
            columns: userSetting?.columns ?? userSetting,
            compact: userSetting?.compact,
            previews: userSetting?.previews,
        });
    });
};
function applyUserSettings(userSettings) {
    if (typeof userSettings.compact === 'boolean') {
        compact.value = userSettings.compact;
    }
    if (typeof userSettings.previews === 'boolean') {
        previews.value = userSettings.previews;
    }
    if (!userSettings.columns) {
        return;
    }
    const userColumnSettings = Object.fromEntries(
        userSettings.columns.map((column, index) => {
            return [
                column.dataIndex,
                {
                    width: column.width,
                    allowResize: column.allowResize,
                    sortable: column.sortable,
                    visible: column.visible,
                    align: column.align,
                    naturalSorting: column.naturalSorting,
                    position: index,
                },
            ];
        }),
    );
    currentColumns.value = currentColumns.value
        .map((column) => {
            if (userColumnSettings[column.dataIndex] === undefined) {
                return column;
            }
            return utils.object.mergeWith({}, column, userColumnSettings[column.dataIndex], (localValue, serverValue) => {
                if (serverValue !== undefined && serverValue !== null) {
                    return serverValue;
                }
                return localValue;
            });
        })
        .sort((column1, column2) => column1.position - column2.position);
}
function findResizeColumns() {
    hasResizeColumns.value = currentColumns.value.some((column) => {
        return column.allowResize;
    });
}
function findPreviewSlots() {
    let scopedSlots = [];
    scopedSlots = Object.keys(slots);
    hasPreviewSlots.value = scopedSlots.some((scopedSlot) => {
        return scopedSlot.includes('preview-');
    });
}
function getDefaultColumns() {
    return props.columns.map((column) => {
        const defaults = {
            width: 'auto',
            allowResize: false,
            sortable: true,
            visible: true,
            align: 'left',
            naturalSorting: false,
        };
        if (!column.property) {
            throw new Error('[ct-data-grid] Please specify a "property" to render a column.');
        }
        if (!column.dataIndex) {
            column.dataIndex = column.property;
        }
        return {
            ...defaults,
            ...column,
        };
    });
}
const createUserGridSetting = () => {
    const newUserGrid = userConfigRepository.value.create(Contena.Context.api);
    newUserGrid.key = `grid.setting.${props.identifier}`;
    newUserGrid.userId = currentUser.value && currentUser.value.id;
    currentSetting.value = newUserGrid;
};
const saveUserSettings = () => {
    if (!acl.can('user_config:create') || !acl.can('user_config:update')) {
        return;
    }

    if (!props.identifier) {
        return;
    }

    if (!currentSetting.value.id) {
        createUserGridSetting();
    }

    currentSetting.value.value = {
        columns: currentColumns.value,
        compact: compact.value,
        previews: previews.value,
    };
    userConfigRepository.value.save(currentSetting.value, Contena.Context.api).then(() => {
        findUserSettingById();
    });
};
const getHeaderCellClasses = (column, index) => {
    return [
        {
            'ct-data-grid__cell--sortable': column.sortable,
            'ct-data-grid__cell--icon-label': column.iconLabel,
        },
        `ct-data-grid__cell--${index}`,
        `ct-data-grid__cell--align-${column.align}`,
    ];
};
const getColumnLabel = (column) => {
    return tWithFallback(column.label);
};
const getRowClasses = (item, itemIndex) => {
    return [
        {
            'is--inline-edit': isInlineEdit(item),
            'is--selected': isSelected(item.id),
            'is--disabled': props.isRecordDisabled(item),
            'is--clickable': props.rowsClickable,
        },
        `ct-data-grid__row--${itemIndex}`,
    ];
};
const getCellClasses = (column) => {
    return [
        `ct-data-grid__cell--${column.property.replace(/\./g, '-')}`,
        `ct-data-grid__cell--align-${column.align}`,
        {
            'ct-data-grid__cell--multi-line': column.multiLine,
        },
    ];
};
const onChangeCompactMode = (value) => {
    compact.value = value;
    saveUserSettings();
};
const onChangePreviews = (value) => {
    previews.value = value;
    saveUserSettings();
};
const onChangeColumnVisibility = (value, index) => {
    currentColumns.value[index].visible = value;

    saveUserSettings();
};
const onChangeColumnOrder = (currentColumnIndex, newColumnIndex) => {
    currentColumns.value = orderColumns(currentColumns.value, currentColumnIndex, newColumnIndex);

    saveUserSettings();
};
function orderColumns(columns, oldColumnIndex, newColumnIndex) {
    columns.splice(newColumnIndex, 0, columns.splice(oldColumnIndex, 1)[0]);
    return columns;
}
const enableInlineEdit = () => {
    isInlineEditActive.value = hasColumnWithInlineEdit();
    setAllColumnElementWidths();
};
function hasColumnWithInlineEdit() {
    return currentColumns.value.some((item) => {
        return item.hasOwnProperty('inlineEdit');
    });
}
function isInlineEdit(item) {
    return isInlineEditActive.value && currentInlineEditId.value === item[props.itemIdentifierProperty];
}
const disableInlineEdit = () => {
    isInlineEditActive.value = false;
    currentInlineEditId.value = '';
};
const hideColumn = (columnIndex) => {
    currentColumns.value[columnIndex].visible = false;

    saveUserSettings();
};
const renderColumn = (item, column) => {
    // horror (pseudo) example: deliveries[0].stateMachineState.transactions.last().name
    // (name is a translated field - developer forgot translated accessor)
    // pointer is now the order
    const accessor = column.property.split('.');
    let pointer = item;

    // parts:  [`deliveries[0]`, `type`, `name`]
    accessor.forEach((part) => {
        // #1 loop: part=deliveries[0]      pointer=order object
        // #2 loop: part=stateMachineState  pointer=delivery object
        // #3 loop: part=transactions       pointer=stateMachineState
        // #4 loop: part=last()             pointer=transactions
        // #5 loop: part=name               pointer=last entity in transaction collection

        if (typeof pointer !== 'object' || pointer === null) {
            utils.debug.warn(`[ct-data-grid] Can not resolve accessor: ${column.property}`);
            return false;
        }

        // check if the current accessor part is a function call like e.g. entity collection "last()"
        if (part.includes('()')) {
            part = part.replace('()', '');
        }

        if (typeof pointer[part] === 'function') {
            pointer = pointer[part]();
        } else if (pointer.hasOwnProperty('translated') && pointer.translated.hasOwnProperty(part)) {
            pointer = pointer.translated[part];
        } else {
            // resolve dynamic accessor part: (name, deliveries[0], translated)
            pointer = utils.get(pointer, part);
        }

        return true;
    });

    return pointer;
};
const selectAll = (selected) => {
    records.value.forEach((item) => {
        if (isSelected(item[props.itemIdentifierProperty]) !== selected) {
            selectItem(selected, item);
        }
    });

    emit('select-all-items', selection.value);
};
function selectItem(selected, item) {
    if (selected && reachMaximumSelectionExceed.value) {
        return;
    }
    if (!recordIsSelectable(item)) {
        return;
    }
    const key = item[props.itemIdentifierProperty];
    if (selected) {
        selection.value = {
            ...selection.value,
            [key]: item,
        };
    } else {
        selection.value = Object.fromEntries(
            Object.entries(selection.value).filter(([selectionKey]) => selectionKey !== key),
        );
    }
    emit('select-item', selection.value, item, selected);
}
function isSelected(itemId) {
    return typeof selection.value[itemId] !== 'undefined';
}
const resetSelection = () => {
    selection.value = {};
};
const onClickSaveInlineEdit = (item) => {
    emit('inline-edit-assign', item);
    save(item);

    disableInlineEdit();
};
const onClickCancelInlineEdit = (item) => {
    revert(item);

    disableInlineEdit();
};
const onDbClickCell = (record) => {
    if (!props.allowInlineEdit || !props.isRecordEditable(record)) {
        return;
    }

    const recordId = record[props.itemIdentifierProperty];

    // Keep the currently edited row stable until the user explicitly saves or cancels it.
    if (isInlineEditActive.value && currentInlineEditId.value !== '' && currentInlineEditId.value !== recordId) {
        return;
    }

    enableInlineEdit();
    currentInlineEditId.value = recordId;
};
const onClickHeaderCell = (event, column) => {
    if (isResizing.value) {
        return;
    }

    if (!column.sortable) {
        return;
    }

    if (event.target.closest('.ct-context-button') || event.target.closest('.ct-data-grid__cell-resize')) {
        return;
    }

    setAllColumnElementWidths();

    sort(column);
};
const onRowClick = (event, item) => {
    if (!props.rowsClickable) {
        return;
    }

    const target = event.target;

    const blockedSelectors = [
        '.ct-data-grid__cell--selection',
        '.ct-data-grid__cell--actions',
        '.ct-context-button',
        'button',
        'a',
        'input',
    ];

    if (blockedSelectors.some((selector) => target.closest(selector))) {
        return;
    }

    if (props.showSelection) {
        const itemId = item[props.itemIdentifierProperty];
        const isCurrentlySelected = isSelected(itemId);

        selectItem(!isCurrentlySelected, item);
    }

    emit('row-click', item);
};
const onStartResize = (event, _column, index) => {
    resizeX.value = event.pageX;
    originalTarget.value = event.target;
    columnIndex.value = index;
    isResizing.value = true;

    handleColumnResizeClasses('add');
    enableResizeMode();
    window.addEventListener('mousemove', onResize, false);
    window.addEventListener('mouseup', onStopResize, false);
};
function onStopResize() {
    resizeX.value = null;
    handleColumnResizeClasses('remove');
    currentColumns.value[columnIndex.value].width = `${currentColumnWidth.value}px`;
    currentColumnWidth.value = null;
    originalTarget.value = null;
    columnIndex.value = null;
    utils.debounce(() => {
        isResizing.value = false;
    }, 50)();
    window.removeEventListener('mouseup', onStopResize, false);
    window.removeEventListener('mousemove', onResize, false);
}
function onResize(event) {
    if (resizeX.value === null) {
        return;
    }
    const currentColumnElement = originalTarget.value.parentNode;
    const diffX = event.pageX - resizeX.value;
    const newColumnWidth = currentColumnElement.offsetWidth + diffX;
    resizeX.value = event.pageX;
    trackScrollX();
    if (newColumnWidth < 65) {
        return;
    }
    currentColumnElement.style.width = `${newColumnWidth}px`;
    currentColumnElement.style.minWidth = `${newColumnWidth}px`;
    currentColumnWidth.value = newColumnWidth;
}
function handleColumnResizeClasses(operation) {
    const resizeElement = originalTarget.value;
    const columnElement = resizeElement.parentNode;
    getGridRootElement()?.classList[operation]('is--resizing');
    resizeElement.classList[operation]('is--column-resizing');
    columnElement.classList[operation]('is--column-resizing');
    columnElement.nextElementSibling.classList[operation]('is--column-resizing');
}
function enableResizeMode() {
    if (hasColumnsResizeState.value) {
        return;
    }
    setAllColumnElementWidths();
    const tableElement = getGridTableElement();
    if (!tableElement) {
        return;
    }
    tableElement.style.tableLayout = 'fixed';
    hasColumnsResizeState.value = true;
}
function setAllColumnElementWidths() {
    getGridColumnElements().forEach((element) => {
        const currentWidth = `${element.offsetWidth}px`;
        if (element.offsetWidth) {
            element.style.width = currentWidth;
            element.style.minWidth = currentWidth;
        }
    });
}
function trackScrollX() {
    const el = getGridRootElement();
    const wrapperEl = getGridWrapperElement();
    if (!el || !wrapperEl) {
        return;
    }
    if (wrapperEl.clientWidth < wrapperEl.scrollWidth) {
        el.classList.add('is--scroll-x');
    } else {
        el.classList.remove('is--scroll-x');
    }
}
function save(item) {
    emit('inline-edit-save', item);
}
function revert(item) {
    emit('inline-edit-cancel', item);
}
function sort(column) {
    emit('column-sort', column);
}

watch(
    () => props.columns,
    () => {
        initGridColumns();
    },
);
watch(
    () => props.sortBy,
    () => {
        currentSortBy.value = props.sortBy;
    },
);
watch(
    () => props.sortDirection,
    () => {
        currentSortDirection.value = props.sortDirection;
    },
);
watch(
    () => props.naturalSorting,
    () => {
        currentNaturalSorting.value = props.naturalSorting;
    },
);
watch(
    () => props.isLoading,
    () => {
        loading.value = props.isLoading;
    },
);
watch(
    () => props.dataSource,
    () => {
        records.value = props.dataSource;
    },
);
watch(
    () => props.showSelection,
    () => {
        selection.value = props.showSelection ? selection.value : {};
    },
);
watch(
    () => props.compactMode,
    () => {
        compact.value = props.compactMode;
    },
);
watch(
    () => selection.value,
    () => {
        emit('selection-change', selection.value, selectionCount.value);
    },
);

createdComponent();
onMounted(mountedComponent);
onBeforeUnmount(() => {
    window.removeEventListener('mouseup', onStopResize, false);
    window.removeEventListener('mousemove', onResize, false);
});

ctDefinePublic({
    acl,
    repositoryFactory,
    records,
    currentSortBy,
    currentSortDirection,
    currentNaturalSorting,
    loading,
    currentSetting,
    currentColumns,
    columnIndex,
    selection,
    originalTarget,
    compact,
    previews,
    isInlineEditActive,
    currentInlineEditId,
    hasPreviewSlots,
    hasResizeColumns,
    hasColumnsResizeState,
    isResizing,
    resizeX,
    currentColumnWidth,
    classes,
    selectionCount,
    reachMaximumSelectionExceed,
    recordIsSelectable,
    isSelectAllDisabled,
    allSelectedChecked,
    userConfigRepository,
    currentUser,
    userGridSettingCriteria,
    hasInvisibleSelection,
    currentVisibleColumns,
    createdComponent,
    mountedComponent,
    initGridColumns,
    findUserSetting,
    findUserSettingById,
    applyUserSettings,
    findResizeColumns,
    findPreviewSlots,
    getDefaultColumns,
    createUserGridSetting,
    saveUserSettings,
    getHeaderCellClasses,
    getColumnLabel,
    getRowClasses,
    getCellClasses,
    onChangeCompactMode,
    onChangePreviews,
    onChangeColumnVisibility,
    onChangeColumnOrder,
    orderColumns,
    enableInlineEdit,
    hasColumnWithInlineEdit,
    isInlineEdit,
    disableInlineEdit,
    hideColumn,
    renderColumn,
    selectAll,
    selectItem,
    isSelected,
    resetSelection,
    onClickSaveInlineEdit,
    onClickCancelInlineEdit,
    onDbClickCell,
    onClickHeaderCell,
    onRowClick,
    onStartResize,
    onStopResize,
    onResize,
    handleColumnResizeClasses,
    enableResizeMode,
    setAllColumnElementWidths,
    trackScrollX,
    save,
    revert,
    sort,
});

defineExpose({
    acl,
    repositoryFactory,
    records,
    currentSortBy,
    currentSortDirection,
    currentNaturalSorting,
    loading,
    currentSetting,
    currentColumns,
    columnIndex,
    selection,
    originalTarget,
    compact,
    previews,
    isInlineEditActive,
    currentInlineEditId,
    hasPreviewSlots,
    hasResizeColumns,
    hasColumnsResizeState,
    isResizing,
    resizeX,
    currentColumnWidth,
    classes,
    selectionCount,
    reachMaximumSelectionExceed,
    recordIsSelectable,
    isSelectAllDisabled,
    allSelectedChecked,
    userConfigRepository,
    currentUser,
    userGridSettingCriteria,
    hasInvisibleSelection,
    currentVisibleColumns,
    createdComponent,
    mountedComponent,
    initGridColumns,
    findUserSetting,
    findUserSettingById,
    applyUserSettings,
    findResizeColumns,
    findPreviewSlots,
    getDefaultColumns,
    createUserGridSetting,
    saveUserSettings,
    getHeaderCellClasses,
    getColumnLabel,
    getRowClasses,
    getCellClasses,
    onChangeCompactMode,
    onChangePreviews,
    onChangeColumnVisibility,
    onChangeColumnOrder,
    orderColumns,
    enableInlineEdit,
    hasColumnWithInlineEdit,
    isInlineEdit,
    disableInlineEdit,
    hideColumn,
    renderColumn,
    selectAll,
    selectItem,
    isSelected,
    resetSelection,
    onClickSaveInlineEdit,
    onClickCancelInlineEdit,
    onDbClickCell,
    onClickHeaderCell,
    onRowClick,
    onStartResize,
    onStopResize,
    onResize,
    handleColumnResizeClasses,
    enableResizeMode,
    setAllColumnElementWidths,
    trackScrollX,
    save,
    revert,
    sort,
});
</script>

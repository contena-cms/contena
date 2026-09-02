<template>
    <ct-block name="ct_data_grid">
        <div class="ct-data-grid" :class="classes">
            <ct-block name="ct_data_grid_wrapper">
                <div ref="wrapper" class="ct-data-grid__wrapper">
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
                                        <ct-block name="ct_data_grid_bulk_selected_actions_content"
                                            ><ct-block name="ct_data_grid_bulk_edit_content">
                                                <a
                                                    v-if="allowBulkEdit"
                                                    class="link link-primary"
                                                    role="button"
                                                    tabindex="0"
                                                    @click="onClickBulkEdit"
                                                    @keydown.enter="onClickBulkEdit"
                                                >
                                                    {{ $t('global.default.bulkEdit') }}
                                                </a>
                                            </ct-block>

                                            <ct-block name="ct_data_grid_bulk_delete_content">
                                                <a
                                                    v-if="allowDelete"
                                                    class="link link-danger"
                                                    role="button"
                                                    tabindex="0"
                                                    @click="showBulkDeleteModal = true"
                                                    @keydown.enter="showBulkDeleteModal = true"
                                                >
                                                    {{ $t('global.default.delete') }}
                                                </a>
                                            </ct-block>

                                            <slot name="bulk-additional" v-bind="{ selectionCount }"></slot
                                        ></ct-block>
                                    </slot>
                                </span>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="ct_data_grid_bulk_modals">
                        <slot name="bulk-modals" v-bind="{ selection }">
                            <ct-block name="ct_data_grid_slot_bulk_modals"
                                ><ct-block name="ct_data_grid_bulk_edit_modal">
                                    <slot name="bulk-edit-modal" v-bind="{ selection }">
                                        <ct-block name="ct_data_grid_slot_bulk_edit_modal">
                                            <ct-bulk-edit-modal
                                                v-if="showBulkEditModal"
                                                :selection="selection"
                                                :bulk-grid-edit-columns="bulkGridEditColumns"
                                                @modal-close="onCloseBulkEditModal"
                                            />
                                        </ct-block>
                                    </slot>
                                </ct-block>

                                <ct-block name="ct_data_grid_bulk_delete_modal">
                                    <ct-modal
                                        v-if="showBulkDeleteModal"
                                        class="ct-entity-listing__confirm-bulk-delete-modal"
                                        variant="small"
                                        :title="$t('global.default.warning')"
                                        @modal-close="showBulkDeleteModal = false"
                                    >
                                        <p class="ct-data-grid__confirm-bulk-delete-text">
                                            <slot name="bulk-modal-delete-confirm-text" v-bind="{ selectionCount }">
                                                {{
                                                    $t(
                                                        'global.entity-components.deleteMessage',
                                                        { count: selectionCount },
                                                        selectionCount,
                                                    )
                                                }}
                                            </slot>
                                        </p>

                                        <template #modal-footer>
                                            <slot name="bulk-modal-cancel">
                                                <mt-button
                                                    size="small"
                                                    variant="secondary"
                                                    @click="showBulkDeleteModal = false"
                                                >
                                                    {{ $t('global.default.cancel') }}
                                                </mt-button>
                                            </slot>

                                            <slot name="bulk-modal-delete-items" v-bind="{ isBulkLoading, deleteItems }">
                                                <mt-button
                                                    variant="critical"
                                                    size="small"
                                                    :is-loading="isBulkLoading"
                                                    @click="deleteItems"
                                                >
                                                    {{ $t('global.default.delete') }}
                                                </mt-button>
                                            </slot>
                                        </template>
                                    </ct-modal>
                                </ct-block>

                                <slot
                                    name="bulk-modals-additional"
                                    v-bind="{ selection, ids: Object.keys(selection) }"
                                ></slot
                            ></ct-block>
                        </slot>
                    </ct-block>

                    <ct-block name="ct_data_grid_table">
                        <table ref="tableRef" class="ct-data-grid__table">
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
                                                    ref="column"
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
                                                                        v-tooltip="getSelectableTooltip(item)"
                                                                        :disabled="!recordIsSelectable(item)"
                                                                        :checked="isSelected(item[itemIdentifierProperty])"
                                                                        @update:checked="selectItem($event, item)"
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
                                                                        <ct-block name="ct_data_grid_slot_actions"
                                                                            ><slot name="detail-action" v-bind="{ item }">
                                                                                <ct-context-menu-item
                                                                                    v-if="detailRoute"
                                                                                    v-tooltip="{
                                                                                        message:
                                                                                            $t(
                                                                                                'ct-privileges.tooltip.warning',
                                                                                            ),
                                                                                        disabled: allowEdit || allowView,
                                                                                        showOnDisabledElements: true,
                                                                                        zIndex: 9050,
                                                                                    }"
                                                                                    class="ct-entity-listing__context-menu-edit-action"
                                                                                    :disabled="
                                                                                        (!allowEdit && !allowView) ||
                                                                                        undefined
                                                                                    "
                                                                                    :router-link="{
                                                                                        name: detailRoute,
                                                                                        params: { id: item.id },
                                                                                    }"
                                                                                >
                                                                                    {{ detailPageLinkText }}
                                                                                </ct-context-menu-item>
                                                                            </slot>

                                                                            <slot
                                                                                name="more-actions"
                                                                                v-bind="{ item }"
                                                                            ></slot>

                                                                            <slot
                                                                                name="delete-action"
                                                                                v-bind="{ item, showDelete, allowDelete }"
                                                                            >
                                                                                <ct-context-menu-item
                                                                                    v-tooltip.bottom="{
                                                                                        message:
                                                                                            $t(
                                                                                                'ct-privileges.tooltip.warning',
                                                                                            ),
                                                                                        disabled: allowDelete,
                                                                                        showOnDisabledElements: true,
                                                                                        zIndex: 9050,
                                                                                    }"
                                                                                    :disabled="!allowDelete || undefined"
                                                                                    class="ct-entity-listing__context-menu-edit-delete"
                                                                                    variant="danger"
                                                                                    @click="showDelete(item.id)"
                                                                                >
                                                                                    {{ $t('global.default.delete') }}
                                                                                </ct-context-menu-item>
                                                                            </slot></ct-block
                                                                        >
                                                                    </slot>
                                                                </ct-context-button>
                                                            </ct-block>

                                                            <ct-block name="ct_data_grid_body_cell_action_modals">
                                                                <slot name="action-modals" :item="item">
                                                                    <ct-block name="ct_data_grid_slot_action_modals"
                                                                        ><ct-modal
                                                                            v-if="deleteId === item.id"
                                                                            :title="$t('global.default.warning')"
                                                                            variant="small"
                                                                            @modal-close="closeModal"
                                                                        >
                                                                            <p class="ct-listing__confirm-delete-text">
                                                                                <slot
                                                                                    name="delete-confirm-text"
                                                                                    v-bind="{ item }"
                                                                                >
                                                                                    {{
                                                                                        $t(
                                                                                            'global.entity-components.deleteMessage',
                                                                                        )
                                                                                    }}
                                                                                </slot>
                                                                            </p>

                                                                            <template #modal-footer>
                                                                                <slot
                                                                                    name="delete-modal-footer"
                                                                                    v-bind="{ item }"
                                                                                >
                                                                                    <slot
                                                                                        name="delete-modal-cancel"
                                                                                        v-bind="{ item }"
                                                                                    >
                                                                                        <mt-button
                                                                                            size="small"
                                                                                            variant="secondary"
                                                                                            @click="closeModal"
                                                                                        >
                                                                                            {{ $t('global.default.cancel') }}
                                                                                        </mt-button>
                                                                                    </slot>

                                                                                    <slot
                                                                                        name="delete-modal-delete-item"
                                                                                        v-bind="{ item }"
                                                                                    >
                                                                                        <mt-button
                                                                                            variant="critical"
                                                                                            size="small"
                                                                                            @click="deleteItem(item.id)"
                                                                                        >
                                                                                            {{ $t('global.default.delete') }}
                                                                                        </mt-button>
                                                                                    </slot>
                                                                                </slot>
                                                                            </template>
                                                                        </ct-modal></ct-block
                                                                    >
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
                            <ct-block name="ct_data_grid_slot_pagination"
                                ><ct-pagination
                                    v-bind="{ page, limit, total, steps }"
                                    :total-visible="7"
                                    @page-change="paginate"
                            /></ct-block>
                        </slot>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
const props = defineProps({
    isRecordSelectable: {
        type: Function,
        required: false,
        default: null,
    },

    isRecordSelectableCallback: {
        type: Function,
        required: false,
        default() {
            return true;
        },
    },
});

import { computed } from 'vue';

const { records, selection, reachMaximumSelectionExceed } = Contena.Component.getExtensionParentSetup();
const recordIsSelectable = (item) => {
    const isSelectableByDefault =
        !reachMaximumSelectionExceed.value || Object.keys(selection.value).includes(item[props.itemIdentifierProperty]);
    let isSelectableByRestrictions = true;

    if (props.isRecordSelectable) {
        return props.isRecordSelectable(item);
    }

    if (props.isRecordSelectableCallback) {
        const callbackResult = props.isRecordSelectableCallback(item);
        if (callbackResult.isSelectable !== null && callbackResult.isSelectable !== undefined) {
            isSelectableByRestrictions = callbackResult.isSelectable;
        }
    }

    return isSelectableByDefault && isSelectableByRestrictions;
};
const isSelectAllDisabled = computed(() => {
    if (!props.maximumSelectItems || !records.value) {
        return false;
    }

    if (!records.value.some((item) => recordIsSelectable(item))) {
        return true;
    }

    const currentVisibleIds = records.value.map((record) => record.id);

    return reachMaximumSelectionExceed.value && Object.keys(selection.value).every((id) => !currentVisibleIds.includes(id));
});
const allSelectedChecked = computed(() => {
    if (isSelectAllDisabled.value) {
        return false;
    }

    if (reachMaximumSelectionExceed.value) {
        return true;
    }

    if (!records.value?.length) {
        return false;
    }

    const selectedItems = Object.values(selection.value);
    if (!records.value.some((item) => recordIsSelectable(item))) {
        return false;
    }

    return records.value.every((item) => {
        if (!recordIsSelectable(item)) {
            return true;
        }

        return selectedItems.some(
            (selectedItem) => selectedItem[props.itemIdentifierProperty] === item[props.itemIdentifierProperty],
        );
    });
});
const getSelectableTooltip = (item) => {
    if (props.isRecordSelectableCallback === null || props.isRecordSelectableCallback === undefined) {
        return { message: '', disabled: true };
    }

    const callbackResult = props.isRecordSelectableCallback(item);

    if (!callbackResult.tooltip) {
        return { message: '', disabled: true };
    }

    return callbackResult.tooltip;
};

ctDefinePublic({
    recordIsSelectable,
    isSelectAllDisabled,
    allSelectedChecked,
    getSelectableTooltip,
});

defineExpose({
    recordIsSelectable,
    isSelectAllDisabled,
    allSelectedChecked,
    getSelectableTooltip,
});
</script>

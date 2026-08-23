<template>
    <ct-block name="sw_settings_list">
        <ct-block name="sw_settings_tag_index">
            <ct-page class="ct-settings-tag-list">
                <template #search-bar>
                    <ct-block name="sw_settings_tag_list_search_bar">
                        <mt-search
                            :model-value="term"
                            :placeholder="$t('ct-settings-tag.general.placeholderSearchBar')"
                            @change="onSearch"
                        />
                    </ct-block>
                </template>

                <template #smart-bar-header>
                    <ct-block name="sw_settings_tag_list_smart_bar_header">
                        <ct-block name="sw_settings_tag_list_smart_bar_header_title">
                            <h2>
                                <ct-block name="sw_settings_tag_list_smart_bar_header_title_text">
                                    {{ $t('ct-settings.index.title') }}
                                    <mt-icon name="regular-chevron-right-xs" size="12px" />
                                    {{ $t('ct-settings-tag.list.textHeadline') }}
                                </ct-block>

                                <ct-block name="sw_settings_tag_list_smart_bar_header_amount">
                                    <span v-if="!isLoading" class="ct-page__smart-bar-amount"> ({{ total }}) </span>
                                </ct-block>
                            </h2>
                        </ct-block>
                    </ct-block>
                </template>

                <template #smart-bar-actions>
                    <ct-block name="sw_settings_tag_list_smart_bar_actions">
                        <ct-block name="sw_settings_tag_list_grid_toolbar_filter">
                            <mt-popover width="medium" :title="$t('ct-settings-tag.list.filter')">
                                <template #trigger="{ toggleFloatingUi }">
                                    <ct-block name="sw_settings_tag_list_grid_toolbar_filter_menu_trigger">
                                        <mt-button
                                            class="ct-settings-tag-list__filter-menu-trigger"
                                            variant="secondary"
                                            size="default"
                                            @click.stop="toggleFloatingUi"
                                        >
                                            <mt-icon name="regular-filter-s" size="16" />
                                            {{ $t('ct-settings-tag.list.filter') }}
                                            <i v-if="filterCount > 0" class="filter-badge">
                                                {{ filterCount }}
                                            </i>
                                        </mt-button>
                                    </ct-block>
                                </template>

                                <template #popover-items__base>
                                    <ct-block name="sw_settings_tag_list_grid_toolbar_filter_duplicate">
                                        <mt-popover-item
                                            show-switch
                                            :switch-value="duplicateFilter"
                                            :label="$t('ct-settings-tag.list.filterDuplicate')"
                                            @change-switch="setDuplicateFilter"
                                        />
                                    </ct-block>

                                    <ct-block name="sw_settings_tag_list_grid_toolbar_filter_empty">
                                        <mt-popover-item
                                            show-switch
                                            :switch-value="emptyFilter"
                                            :label="$t('ct-settings-tag.list.filterEmpty')"
                                            @change-switch="setEmptyFilter"
                                        />
                                    </ct-block>

                                    <ct-block name="sw_settings_tag_list_grid_toolbar_filter_assignment">
                                        <mt-select
                                            :model-value="assignmentFilter"
                                            class="ct-settings-tag-list__filter-assignment-select"
                                            :label="$t('ct-settings-tag.list.filterAssignemnt')"
                                            :placeholder="$t('ct-settings-tag.list.placeholderFilterAssignemnt')"
                                            :options="assignmentFilterOptions"
                                            enable-multi-selection
                                            :disabled="emptyFilter || undefined"
                                            @update:model-value="setAssignmentFilter"
                                        />
                                    </ct-block>

                                    <ct-block name="sw_settings_tag_list_grid_toolbar_filter_footer">
                                        <mt-popover-item
                                            type="critical"
                                            icon="solid-undo"
                                            :label="$t('ct-settings-tag.list.resetFilters')"
                                            :on-label-click="resetFilters"
                                        />
                                    </ct-block>
                                </template>
                            </mt-popover>
                        </ct-block>

                        <ct-block name="sw_settings_tag_list_smart_bar_actions_add">
                            <mt-button
                                v-tooltip.bottom="{
                                    message: $t('ct-privileges.tooltip.warning'),
                                    disabled: acl.can('tag.creator'),
                                    showOnDisabledElements: true,
                                }"
                                class="ct-settings-tag-list__button-create"
                                variant="primary"
                                :disabled="!acl.can('tag.creator') || undefined"
                                size="default"
                                @click="onDetail(null)"
                            >
                                {{ $t('global.default.add') }}
                            </mt-button>
                        </ct-block>
                    </ct-block>
                </template>

                <template #content>
                    <ct-block name="sw_settings_tag_list_content">
                        <ct-card-view>
                            <ct-block name="sw_settings_tag_list_content_card">
                                <ct-block name="sw_settings_tag_list_grid">
                                    <mt-data-table
                                        class="ct-settings-tag-list__content ct-settings-tag-list__grid"
                                        layout="full"
                                        :caption="$t('ct-settings-tag.list.textHeadline')"
                                        :data-source="tags ?? []"
                                        :columns="tagColumns"
                                        :is-loading="isLoading"
                                        :pagination-total-items="total"
                                        :current-page="page"
                                        :pagination-limit="limit"
                                        :sort-by="sortBy"
                                        :sort-direction="sortDirection"
                                        :selected-rows="selectedTagIds"
                                        :show-outlines="showOutlines"
                                        :show-stripes="showStripes"
                                        :enable-outline-framing="enableOutlineFraming"
                                        :enable-row-numbering="enableRowNumbering"
                                        :allow-row-selection="true"
                                        :disable-search="true"
                                        :disable-edit="true"
                                        :disable-delete="!acl.can('tag.deleter')"
                                        :additional-context-buttons="additionalContextButtons"
                                        @reload="getList"
                                        @pagination-current-page-change="onCurrentPageChange"
                                        @pagination-limit-change="onLimitChange"
                                        @sort-change="onSortChange"
                                        @selection-change="onSelectionChange"
                                        @multiple-selection-change="onMultipleSelectionChange"
                                        @change-show-outlines="showOutlines = $event"
                                        @change-show-stripes="showStripes = $event"
                                        @change-outline-framing="enableOutlineFraming = $event"
                                        @change-enable-row-numbering="enableRowNumbering = $event"
                                        @item-delete="onItemDelete"
                                        @context-select="onContextSelect"
                                    >
                                        <template
                                            v-if="
                                                selectedTagIds.length > 1 && acl.can('tag.creator') && acl.can('tag.deleter')
                                            "
                                            #toolbar
                                        >
                                            <ct-block name="sw_settings_tag_list_grid_bulk">
                                                <mt-button variant="secondary" @click="showBulkMergeModal = true">
                                                    {{ $t('ct-settings-tag.list.bulkMerge') }}
                                                </mt-button>
                                            </ct-block>
                                        </template>

                                        <template #column-name="{ data: item }">
                                            <ct-block name="sw_settings_tag_list_grid_column_default_name">
                                                <mt-badge v-tooltip="{ message: item.name }">
                                                    {{ item.name }}
                                                </mt-badge>
                                            </ct-block>
                                        </template>

                                        <!-- ct-block preserves this slot variable at runtime. -->
                                        <!-- eslint-disable vue/no-unused-vars -->
                                        <template
                                            v-for="(propertyName, index) in assignmentProperties"
                                            :key="index"
                                            #[`column-${propertyName}`]="assignmentData"
                                        >
                                            <ct-block name="sw_settings_tag_list_grid_column_assignments">
                                                <span class="ct-settings-tag-list__assignment-count">
                                                    {{ getPropertyCounting(propertyName, assignmentData.data.id) }}
                                                    {{
                                                        $t(
                                                            `ct-settings-tag.list.assignments.content.${propertyName}`,
                                                            getPropertyCounting(propertyName, assignmentData.data.id),
                                                        )
                                                    }}
                                                </span>
                                            </ct-block>
                                        </template>
                                        <!-- eslint-enable vue/no-unused-vars -->

                                        <template #empty-state>
                                            <ct-block name="sw_settings_tag_list_empty_state">
                                                <mt-empty-state
                                                    :icon="$route.meta.$module.icon"
                                                    :headline="$t('ct-settings-tag.list.titleEmptyStateList')"
                                                />
                                            </ct-block>
                                        </template>
                                    </mt-data-table>

                                    <ct-block name="sw_settings_tag_list_grid_action_modals">
                                        <ct-block name="sw_settings_tag_list_delete_modal">
                                            <mt-modal-root v-if="tagToDelete" :is-open="true" @change="onCloseDeleteModal">
                                                <mt-modal :title="$t('global.default.warning')" width="s">
                                                    <ct-block name="sw_settings_tag_list_delete_modal_confirm_delete_text">
                                                        <p class="ct-settings-tag-list__confirm-delete-text">
                                                            {{
                                                                $t(
                                                                    'ct-settings-tag.list.textDeleteConfirm',
                                                                    { name: tagToDelete.name },
                                                                    0,
                                                                )
                                                            }}
                                                        </p>
                                                    </ct-block>

                                                    <template #footer>
                                                        <div class="ct-settings-tag-list__modal-footer">
                                                            <ct-block name="sw_settings_tag_list_delete_modal_footer">
                                                                <ct-block name="sw_settings_tag_list_delete_modal_cancel">
                                                                    <mt-modal-close
                                                                        as="mt-button"
                                                                        size="small"
                                                                        variant="secondary"
                                                                    >
                                                                        {{ $t('global.default.cancel') }}
                                                                    </mt-modal-close>
                                                                </ct-block>

                                                                <ct-block name="sw_settings_tag_list_delete_modal_confirm">
                                                                    <mt-modal-action
                                                                        as="mt-button"
                                                                        variant="critical"
                                                                        size="small"
                                                                        @click="
                                                                            (done) => onConfirmDelete(tagToDelete.id, done)
                                                                        "
                                                                    >
                                                                        {{ $t('global.default.delete') }}
                                                                    </mt-modal-action>
                                                                </ct-block>
                                                            </ct-block>
                                                        </div>
                                                    </template>
                                                </mt-modal>
                                            </mt-modal-root>
                                        </ct-block>

                                        <ct-block name="sw_settings_tag_list_duplicate_modal">
                                            <mt-modal-root
                                                v-if="tagToDuplicate"
                                                :is-open="true"
                                                @change="onCloseDuplicateModal"
                                            >
                                                <mt-modal :title="$t('global.default.duplicate')" width="s">
                                                    <ct-block
                                                        name="sw_settings_tag_list_delete_modal_confirm_duplicate_input"
                                                    >
                                                        <p class="ct-settings-tag-list__confirm-duplicate-input">
                                                            <mt-text-field
                                                                v-model="duplicateName"
                                                                :label="$t('ct-settings-tag.list.columnName')"
                                                                :placeholder="$t('ct-settings-tag.list.placeholderTagName')"
                                                                maxlength="255"
                                                                required
                                                            />
                                                        </p>
                                                    </ct-block>

                                                    <template #footer>
                                                        <div class="ct-settings-tag-list__modal-footer">
                                                            <ct-block name="sw_settings_tag_list_duplicate_modal_footer">
                                                                <ct-block name="sw_settings_tag_list_duplicate_modal_cancel">
                                                                    <mt-modal-close
                                                                        as="mt-button"
                                                                        size="small"
                                                                        variant="secondary"
                                                                    >
                                                                        {{ $t('global.default.cancel') }}
                                                                    </mt-modal-close>
                                                                </ct-block>

                                                                <ct-block
                                                                    name="sw_settings_tag_list_duplicate_modal_confirm"
                                                                >
                                                                    <mt-modal-action
                                                                        as="mt-button"
                                                                        variant="primary"
                                                                        size="small"
                                                                        :disabled="!duplicateName"
                                                                        @click="
                                                                            (done) =>
                                                                                onConfirmDuplicate(tagToDuplicate.id, done)
                                                                        "
                                                                    >
                                                                        {{ $t('global.default.add') }}
                                                                    </mt-modal-action>
                                                                </ct-block>
                                                            </ct-block>
                                                        </div>
                                                    </template>
                                                </mt-modal>
                                            </mt-modal-root>
                                        </ct-block>

                                        <ct-block name="sw_settings_tag_list_detail_edit_modal">
                                            <ct-settings-tag-detail-modal
                                                v-if="tagToEdit"
                                                :edited-tag="tagToEdit"
                                                :counts="getCounts(tagToEdit.id)"
                                                :property="detailProperty"
                                                :entity="detailEntity"
                                                @finish="onSaveFinish"
                                                @close="onCloseDetailModal"
                                            />
                                        </ct-block>
                                    </ct-block>

                                    <ct-block name="sw_settings_tag_list_grid_bulk_merge_modal">
                                        <mt-modal-root
                                            v-if="showBulkMergeModal"
                                            :is-open="true"
                                            @change="onCloseBulkMergeModal"
                                        >
                                            <mt-modal :title="$t('ct-settings-tag.list.bulkMergeTitle')" width="l">
                                                <slot
                                                    name="bulk-modal-merge-confirm-text"
                                                    v-bind="{ selection: tagSelection, ids: selectedTagIds }"
                                                >
                                                    <mt-banner variant="info">
                                                        {{ $t('ct-settings-tag.list.bulkMergeNotice') }}
                                                    </mt-banner>
                                                    <p class="ct-settings-tag-list__confirm-bulk-merge-text">
                                                        <span v-if="!bulkMergeProgress.isRunning">
                                                            {{ $t('ct-settings-tag.list.bulkMergeMessage') }}
                                                        </span>
                                                        <span v-else>
                                                            {{ $t('ct-settings-tag.list.bulkMerging') }}
                                                        </span>
                                                        <span
                                                            v-for="id in selectedTagIds"
                                                            :key="id"
                                                            class="confirm-bulk-merge-text__label"
                                                        >
                                                            <mt-badge v-tooltip="{ message: tagSelection[id].name }">
                                                                {{ tagSelection[id].name }} </mt-badge
                                                            >&nbsp;{{ getBulkMergeMessageGlue(selectedTagIds, id) }}
                                                        </span>
                                                        <span
                                                            v-if="bulkMergeProgress.isRunning"
                                                            class="confirm-bulk-merge-text__label-into"
                                                        >
                                                            <mt-badge v-tooltip="{ message: duplicateName }">{{
                                                                duplicateName
                                                            }}</mt-badge
                                                            >&nbsp;.
                                                        </span>
                                                    </p>
                                                    <p v-if="bulkMergeProgress.isRunning">
                                                        {{ $t('ct-settings-tag.list.bulkMergeTimeNotice') }}
                                                    </p>
                                                    <p v-else>
                                                        {{ $t('ct-settings-tag.list.bulkMergeNoUndoNotice') }}
                                                    </p>
                                                </slot>

                                                <ct-block name="sw_settings_tag_list_merge_modal_confirm_name_input">
                                                    <slot name="bulk-modal-merge-confirm-name-input">
                                                        <p
                                                            v-if="!bulkMergeProgress.isRunning"
                                                            class="ct-settings-tag-list__confirm-bulk-merge-name-input"
                                                        >
                                                            <mt-text-field
                                                                v-model="duplicateName"
                                                                :label="$t('ct-settings-tag.list.bulkMergeName')"
                                                                :placeholder="$t('ct-settings-tag.list.placeholderTagName')"
                                                                maxlength="255"
                                                                required
                                                            />
                                                        </p>
                                                    </slot>
                                                </ct-block>

                                                <ct-block name="sw_settings_tag_list_merge_modal_progress">
                                                    <slot name="bulk-modal-merge-progress">
                                                        <div
                                                            v-if="bulkMergeProgress.isRunning"
                                                            class="ct-settings-tag-list__bulk-merge-progress"
                                                        >
                                                            <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                                                            <mt-loader
                                                                class="ct-settings-tag-list__bulk-merge-progress-icon"
                                                                size="44px"
                                                            />
                                                        </div>
                                                    </slot>
                                                </ct-block>

                                                <template #footer>
                                                    <div class="ct-settings-tag-list__modal-footer">
                                                        <slot
                                                            name="bulk-modal-merge-footer"
                                                            v-bind="{ selection: tagSelection }"
                                                        >
                                                            <mt-modal-close as="mt-button" size="small" variant="secondary">
                                                                {{ $t('global.default.cancel') }}
                                                            </mt-modal-close>

                                                            <mt-modal-action
                                                                as="mt-button"
                                                                variant="primary"
                                                                size="small"
                                                                :is-loading="isLoading"
                                                                :disabled="
                                                                    !duplicateName ||
                                                                    bulkMergeProgress.isRunning ||
                                                                    undefined
                                                                "
                                                                @click="(done) => onMergeTags(tagSelection, done)"
                                                            >
                                                                {{ $t('ct-settings-tag.list.bulkMerge') }}
                                                            </mt-modal-action>
                                                        </slot>
                                                    </div>
                                                </template>
                                            </mt-modal>
                                        </mt-modal-root>
                                    </ct-block>

                                    <ct-block name="sw_settings_tag_list_detail_add_modal">
                                        <ct-settings-tag-detail-modal
                                            v-if="showDetailModal === true"
                                            @finish="onSaveFinish"
                                            @close="onCloseDetailModal"
                                        />
                                    </ct-block>
                                </ct-block>
                            </ct-block>
                        </ct-card-view>
                    </ct-block>
                </template>
            </ct-page>
        </ct-block>
    </ct-block>
</template>

<script setup>
import './ct-settings-tag-list.scss';
const { Criteria } = Contena.Data;

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});

defineProps({});

import { ref, computed, inject, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { page, limit, total: total2, term, onPageChange, onSearch, updateRoute, initializeListing } = useListing();
const total = total2;
const { createNotificationError } = useNotification();

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');
const tagApiService = inject('tagApiService');

const tags = ref(null);
const sortBy = ref('name');
const isLoading = ref(false);
const sortDirection = ref('ASC');
const showDeleteModal = ref(false);
const showDuplicateModal = ref(false);
const showBulkMergeModal = ref(false);
const duplicateName = ref(null);
const showDetailModal = ref(false);
const detailProperty = ref(null);
const detailEntity = ref(null);
const assignmentFilter = ref(null);
const emptyFilter = ref(false);
const duplicateFilter = ref(false);
const showOutlines = ref(true);
const showStripes = ref(true);
const enableOutlineFraming = ref(false);
const enableRowNumbering = ref(false);
const selectedTagIds = ref([]);
const tagSelection = ref({});
const bulkMergeProgress = ref({
    isRunning: false,
    currentAssignment: null,
    progress: 0,
    total: 0,
});

const tagRepository = computed(() => {
    return repositoryFactory.create('tag');
});
const tagDefinition = computed(() => {
    return Contena.EntityDefinition.get('tag');
});
const getTagById = (id) => {
    return tags.value?.find((tag) => tag.id === id) ?? null;
};
const tagToDelete = computed(() => getTagById(showDeleteModal.value));
const tagToDuplicate = computed(() => getTagById(showDuplicateModal.value));
const tagToEdit = computed(() => getTagById(showDetailModal.value));
const assignmentProperties = computed(() => {
    const properties = [];

    Object.entries(tagDefinition.value.properties).forEach(
        ([
            propertyName,
            property,
        ]) => {
            if (property.relation !== 'many_to_many') {
                return;
            }

            properties.push(propertyName);
        },
    );

    return properties;
});
const tagCriteria = computed(() => {
    const criteria = new Criteria(page.value, limit.value);

    criteria.setTerm(term.value);

    setAggregations(criteria);

    const naturalSort = sortBy.value === 'createdAt';
    // Criteria is a local mutable query object, not component state.
    // eslint-disable-next-line vue/no-side-effects-in-computed-properties
    const sorting = Criteria.sort(sortBy.value, sortDirection.value, naturalSort);

    if (assignmentProperties.value.includes(sortBy.value)) {
        sorting.field += '.id';
        sorting.type = 'count';
    }
    criteria.addSorting(sorting);

    return criteria;
});
const tagColumns = computed(() => {
    const columns = [
        {
            property: 'name',
            label: t('ct-settings-tag.list.columnName'),
            renderer: 'text',
            position: 100,
            width: 200,
            allowResize: true,
            sortable: true,
        },
    ];

    assignmentProperties.value.forEach((propertyName, index) => {
        columns.push({
            property: `${propertyName}`,
            label: t(`ct-settings-tag.list.assignments.header.${propertyName}`),
            renderer: 'text',
            position: (index + 2) * 100,
            width: 250,
            allowResize: true,
            sortable: true,
        });
    });

    return columns;
});
const additionalContextButtons = computed(() => {
    const buttons = [];

    if (acl.can('tag.editor')) {
        buttons.push({
            key: 'edit',
            label: t('global.default.edit'),
        });
    }

    if (acl.can('tag.creator')) {
        buttons.push({
            key: 'duplicate',
            label: t('global.default.duplicate'),
        });
    }

    return buttons;
});
const assignmentFilterOptions = computed(() => {
    const options = [];

    Object.entries(tagDefinition.value.properties).forEach(
        ([
            propertyName,
            property,
        ]) => {
            if (property.relation !== 'many_to_many') {
                return;
            }

            options.push({
                value: propertyName,
                label: t(`ct-settings-tag.list.assignments.filter.${propertyName}`),
            });
        },
    );
    options.sort((a, b) => {
        if (a.label > b.label) {
            return 1;
        }
        if (b.label > a.label) {
            return -1;
        }
        return 0;
    });

    return options;
});
const hasAssignmentFilter = computed(() => {
    return assignmentFilter.value && assignmentFilter.value.length > 0;
});
const filterCount = computed(() => {
    let count = 0;

    if (hasAssignmentFilter.value || emptyFilter.value) {
        count += 1;
    }

    if (duplicateFilter.value) {
        count += 1;
    }

    return count;
});

const setAggregations = (criteria) => {
    Object.entries(tagDefinition.value.properties).forEach(
        ([
            propertyName,
            property,
        ]) => {
            if (property.relation !== 'many_to_many') {
                return;
            }

            criteria.addAggregation(
                Criteria.terms(propertyName, 'id', null, null, Criteria.count(propertyName, `tag.${propertyName}.id`)),
            );
        },
    );
};
const getList = () => {
    isLoading.value = true;
    if (duplicateFilter.value || emptyFilter.value || hasAssignmentFilter.value) {
        tagApiService
            .filterIds(tagCriteria.value.parse(), {
                duplicateFilter: duplicateFilter.value,
                emptyFilter: emptyFilter.value,
                assignmentFilter: assignmentFilter.value,
            })
            .then(({ total: totalValue, ids }) => {
                total2.value = totalValue;

                if (totalValue === 0) {
                    tags.value = null;
                    isLoading.value = false;

                    return;
                }

                const criteria = new Criteria(1, limit.value);
                criteria.setIds(ids);
                criteria.setTotalCountMode(0);
                criteria.aggregations = tagCriteria.value.aggregations;
                criteria.associations = tagCriteria.value.associations;

                tagRepository.value
                    .search(criteria)
                    .then((items) => {
                        items.total = totalValue;
                        tags.value = sortByIdsOrder(items, ids);
                        isLoading.value = false;

                        return items;
                    })
                    .catch(() => {
                        isLoading.value = false;
                    });
            })
            .catch(() => {
                isLoading.value = false;
            });

        return;
    }
    tagRepository.value
        .search(tagCriteria.value)
        .then((items) => {
            total2.value = items.total;
            tags.value = items;
            isLoading.value = false;

            return items;
        })
        .catch(() => {
            isLoading.value = false;
        });
};
const sortByIdsOrder = (items, ids) => {
    items.sort((a, b) => {
        if (ids.indexOf(a.id) > ids.indexOf(b.id)) {
            return 1;
        }

        return -1;
    });

    return items;
};
const getCounts = (id) => {
    const counts = {};

    Object.entries(tagDefinition.value.properties).forEach(
        ([
            propertyName,
            property,
        ]) => {
            if (property.relation === 'many_to_many') {
                const countBucket = tags.value.aggregations[propertyName]?.buckets.filter((bucket) => {
                    return bucket.key === id;
                })[0];

                if (!countBucket?.[propertyName] || !countBucket?.[propertyName].count) {
                    return;
                }

                counts[propertyName] = countBucket?.[propertyName].count;
            }
        },
    );

    return counts;
};
const getPropertyCounting = (propertyName, id) => {
    if (!tags.value.aggregations[propertyName]) {
        return 0;
    }

    const countBucket = tags.value.aggregations[propertyName].buckets.filter((bucket) => {
        return bucket.key === id;
    })[0];

    if (!countBucket || !countBucket[propertyName] || !countBucket[propertyName].count) {
        return 0;
    }

    return countBucket[propertyName].count;
};
const updateTagSelection = (ids, value) => {
    const updatedIds = new Set(selectedTagIds.value);
    const updatedSelection = { ...tagSelection.value };

    ids.forEach((id) => {
        if (!value) {
            updatedIds.delete(id);
            delete updatedSelection[id];

            return;
        }

        const tag = getTagById(id);
        if (tag) {
            updatedIds.add(id);
            updatedSelection[id] = tag;
        }
    });

    selectedTagIds.value = [...updatedIds];
    tagSelection.value = updatedSelection;
};
const onSelectionChange = ({ id, value }) => {
    updateTagSelection([id], value);
};
const onMultipleSelectionChange = ({ selections, value }) => {
    updateTagSelection(selections, value);
};
const resetTagSelection = () => {
    selectedTagIds.value = [];
    tagSelection.value = {};
};
const onOpenDetails = (item) => {
    onDetail(item.id);
};
const onItemDelete = (item) => {
    onDelete(item.id);
};
const onContextSelect = ({ key, data }) => {
    if (key === 'edit') {
        onDetail(data.id);
    }

    if (key === 'duplicate') {
        onDuplicate(data);
    }
};
const onDelete = (id) => {
    showDeleteModal.value = id;
};
const onCloseDeleteModal = () => {
    showDeleteModal.value = false;
};
const onConfirmDelete = (id, done) => {
    void nextTick().then(() => {
        isLoading.value = true;
    });

    return tagRepository.value.delete(id).then(() => {
        getList();

        if (done) {
            done();
        } else {
            onCloseDeleteModal();
        }
    });
};
const onDuplicate = (item) => {
    showDuplicateModal.value = item.id;
    duplicateName.value = `${item.name} ${t('global.default.copy')}`;
};
const onCloseDuplicateModal = () => {
    showDuplicateModal.value = false;
    duplicateName.value = null;
};
const onConfirmDuplicate = (id, done) => {
    void nextTick().then(() => {
        isLoading.value = true;
    });

    const behavior = {
        cloneChildren: false,
        overwrites: {
            name: duplicateName.value,
        },
    };

    return tagRepository.value
        .clone(id, behavior, Contena.Context.api)
        .then(() => {
            duplicateName.value = null;
            getList();

            if (done) {
                done();
            } else {
                onCloseDuplicateModal();
            }
        })
        .catch(() => {
            isLoading.value = false;
            duplicateName.value = null;

            createNotificationError({
                message: t('global.notification.unspecifiedSaveErrorMessage'),
            });
        });
};
const onDetail = (id, property, entity) => {
    showDetailModal.value = id ?? true;

    if (property && entity) {
        detailProperty.value = property;
        detailEntity.value = entity;
    }
};
const onCloseDetailModal = () => {
    showDetailModal.value = false;
    detailProperty.value = null;
    detailEntity.value = null;
};
const onCloseBulkMergeModal = () => {
    bulkMergeProgress.value.isRunning = false;
    showBulkMergeModal.value = false;
    duplicateName.value = null;
};
const onMergeTags = (selection, done) => {
    return tagApiService
        .merge(Object.keys(selection), duplicateName.value, tagDefinition.value.properties, bulkMergeProgress.value)
        .then(() => {
            duplicateName.value = null;
            resetTagSelection();

            bulkMergeProgress.value.isRunning = false;
            void nextTick().then(() => {
                isLoading.value = true;
            });

            onFilter();

            if (done) {
                done();
            } else {
                onCloseBulkMergeModal();
            }
        })
        .catch(() => {
            bulkMergeProgress.value.isRunning = false;
            createNotificationError({
                message: t('global.notification.unspecifiedSaveErrorMessage'),
            });
        });
};
const getBulkMergeMessageGlue = (ids, id) => {
    if (ids.length - 1 === ids.indexOf(id)) {
        return bulkMergeProgress.value.isRunning
            ? t('ct-settings-tag.list.bulkMergeInto')
            : t('ct-settings-tag.list.bulkMergeMessageFinal');
    }

    if (ids.length - 2 === ids.indexOf(id)) {
        return t('ct-settings-tag.list.bulkMergeMessageAnd');
    }

    return ',';
};
const onSaveFinish = () => {
    onCloseDetailModal();

    void nextTick().then(() => {
        getList();
    });
};
const onFilter = () => {
    if (assignmentFilter.value && emptyFilter.value) {
        assignmentFilter.value = null;
    }

    page.value = 1;
    getList();
};
const setDuplicateFilter = (value) => {
    duplicateFilter.value = value;
    onFilter();
};
const setEmptyFilter = (value) => {
    emptyFilter.value = value;
    onFilter();
};
const setAssignmentFilter = (value) => {
    assignmentFilter.value = value;
    onFilter();
};
const resetFilters = () => {
    assignmentFilter.value = null;
    emptyFilter.value = false;
    duplicateFilter.value = false;

    onFilter();
};
const onCurrentPageChange = (currentPage) => {
    onPageChange({ page: currentPage, limit: limit.value });
};
const onLimitChange = (currentLimit) => {
    onPageChange({ page: 1, limit: currentLimit });
};
const onSortChange = (property, direction) => {
    sortBy.value = property;
    sortDirection.value = direction;
    updateRoute({ sortBy: property, sortDirection: direction });
};

initializeListing({
    getList,
    sortBy,
    sortDirection,
});

swDefinePublic({
    repositoryFactory,
    acl,
    tagApiService,
    tags,
    sortBy,
    isLoading,
    sortDirection,
    showDeleteModal,
    showDuplicateModal,
    showBulkMergeModal,
    duplicateName,
    showDetailModal,
    detailProperty,
    detailEntity,
    assignmentFilter,
    emptyFilter,
    duplicateFilter,
    showOutlines,
    showStripes,
    enableOutlineFraming,
    enableRowNumbering,
    bulkMergeProgress,
    selectedTagIds,
    tagSelection,
    tagRepository,
    tagDefinition,
    tagToDelete,
    tagToDuplicate,
    tagToEdit,
    assignmentProperties,
    tagCriteria,
    tagColumns,
    additionalContextButtons,
    assignmentFilterOptions,
    hasAssignmentFilter,
    filterCount,
    setAggregations,
    getList,
    sortByIdsOrder,
    getCounts,
    getPropertyCounting,
    onDelete,
    onCloseDeleteModal,
    onConfirmDelete,
    onDuplicate,
    onCloseDuplicateModal,
    onConfirmDuplicate,
    onDetail,
    onCloseDetailModal,
    onCloseBulkMergeModal,
    onMergeTags,
    getBulkMergeMessageGlue,
    onSaveFinish,
    onFilter,
    setDuplicateFilter,
    setEmptyFilter,
    setAssignmentFilter,
    resetFilters,
    onCurrentPageChange,
    onLimitChange,
    onSortChange,
    onSelectionChange,
    onMultipleSelectionChange,
    resetTagSelection,
    onOpenDetails,
    onItemDelete,
    onContextSelect,
});

defineExpose({
    repositoryFactory,
    acl,
    tagApiService,
    tags,
    sortBy,
    isLoading,
    sortDirection,
    showDeleteModal,
    showDuplicateModal,
    showBulkMergeModal,
    duplicateName,
    showDetailModal,
    detailProperty,
    detailEntity,
    assignmentFilter,
    emptyFilter,
    duplicateFilter,
    showOutlines,
    showStripes,
    enableOutlineFraming,
    enableRowNumbering,
    bulkMergeProgress,
    selectedTagIds,
    tagSelection,
    tagRepository,
    tagDefinition,
    tagToDelete,
    tagToDuplicate,
    tagToEdit,
    assignmentProperties,
    tagCriteria,
    tagColumns,
    additionalContextButtons,
    assignmentFilterOptions,
    hasAssignmentFilter,
    filterCount,
    setAggregations,
    getList,
    sortByIdsOrder,
    getCounts,
    getPropertyCounting,
    onDelete,
    onCloseDeleteModal,
    onConfirmDelete,
    onDuplicate,
    onCloseDuplicateModal,
    onConfirmDuplicate,
    onDetail,
    onCloseDetailModal,
    onCloseBulkMergeModal,
    onMergeTags,
    getBulkMergeMessageGlue,
    onSaveFinish,
    onFilter,
    setDuplicateFilter,
    setEmptyFilter,
    setAssignmentFilter,
    resetFilters,
    onCurrentPageChange,
    onLimitChange,
    onSortChange,
    onSelectionChange,
    onMultipleSelectionChange,
    resetTagSelection,
    onOpenDetails,
    onItemDelete,
    onContextSelect,
});
</script>

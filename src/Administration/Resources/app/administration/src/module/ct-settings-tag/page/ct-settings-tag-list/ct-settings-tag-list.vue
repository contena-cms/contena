<template>
    <ct-block name="ct_settings_list">
        <ct-block name="ct_settings_tag_index">
            <ct-page class="ct-settings-tag-list">
                <template #search-bar>
                    <ct-block name="ct_settings_tag_list_search_bar">
                        <mt-search
                            :model-value="term"
                            :placeholder="$t('ct-settings-tag.general.placeholderSearchBar')"
                            @change="onSearch"
                        />
                    </ct-block>
                </template>

                <template #smart-bar-header>
                    <ct-block name="ct_settings_tag_list_smart_bar_header">
                        <ct-block name="ct_settings_tag_list_smart_bar_header_title">
                            <h2>
                                <ct-block name="ct_settings_tag_list_smart_bar_header_title_text">
                                    {{ $t('ct-settings.index.title') }}
                                    <mt-icon name="regular-chevron-right-xs" size="12px" />
                                    {{ $t('ct-settings-tag.list.textHeadline') }}
                                </ct-block>

                                <ct-block name="ct_settings_tag_list_smart_bar_header_amount">
                                    <span v-if="!isLoading" class="ct-page__smart-bar-amount"> ({{ total }}) </span>
                                </ct-block>
                            </h2>
                        </ct-block>
                    </ct-block>
                </template>

                <template #smart-bar-actions>
                    <ct-block name="ct_settings_tag_list_smart_bar_actions">
                        <ct-block name="ct_settings_tag_list_grid_toolbar_filter">
                            <mt-popover width="medium" :title="$t('ct-settings-tag.list.filter')">
                                <template #trigger="{ toggleFloatingUi }">
                                    <ct-block name="ct_settings_tag_list_grid_toolbar_filter_menu_trigger">
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
                                    <ct-block name="ct_settings_tag_list_grid_toolbar_filter_duplicate">
                                        <mt-popover-item
                                            show-switch
                                            :switch-value="duplicateFilter"
                                            :label="$t('ct-settings-tag.list.filterDuplicate')"
                                            @change-switch="setDuplicateFilter"
                                        />
                                    </ct-block>

                                    <ct-block name="ct_settings_tag_list_grid_toolbar_filter_empty">
                                        <mt-popover-item
                                            show-switch
                                            :switch-value="emptyFilter"
                                            :label="$t('ct-settings-tag.list.filterEmpty')"
                                            @change-switch="setEmptyFilter"
                                        />
                                    </ct-block>

                                    <ct-block name="ct_settings_tag_list_grid_toolbar_filter_assignment">
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

                                    <ct-block name="ct_settings_tag_list_grid_toolbar_filter_footer">
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

                        <ct-block name="ct_settings_tag_list_smart_bar_actions_add">
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
                    <ct-block name="ct_settings_tag_list_content">
                        <ct-card-view>
                            <ct-block name="ct_settings_tag_list_content_card">
                                <ct-block name="ct_settings_tag_list_grid">
                                    <ct-meteor-entity-data-table
                                        ref="tagTable"
                                        class="ct-settings-tag-list__content ct-settings-tag-list__grid"
                                        layout="full"
                                        entity="tag"
                                        :repository="tagTableRepository"
                                        :caption="$t('ct-settings-tag.list.textHeadline')"
                                        :columns="tagColumns"
                                        :criteria="tagCriteria"
                                        :criteria-transform="transformTagCriteria"
                                        :search-term="term"
                                        default-sort-by="name"
                                        :allow-edit="acl.can('tag.editor')"
                                        :allow-delete="acl.can('tag.deleter')"
                                        :show-selections="acl.can('tag.deleter')"
                                        :disable-search="true"
                                        :additional-context-buttons="additionalContextButtons"
                                        @load-success="onTableLoadSuccess"
                                        @loading-change="onTableLoadingChange"
                                        @total-change="onTableTotalChange"
                                        @selected-ids-change="onSelectedIdsChange"
                                        @open-detail="onOpenDetails"
                                        @context-select="onContextSelect"
                                    >
                                        <template
                                            v-if="
                                                selectedTagIds.length > 1 && acl.can('tag.creator') && acl.can('tag.deleter')
                                            "
                                            #toolbar
                                        >
                                            <ct-block name="ct_settings_tag_list_grid_bulk">
                                                <mt-button variant="secondary" @click="showBulkMergeModal = true">
                                                    {{ $t('ct-settings-tag.list.bulkMerge') }}
                                                </mt-button>
                                            </ct-block>
                                        </template>

                                        <template #column-name="{ data: item }">
                                            <ct-block name="ct_settings_tag_list_grid_column_default_name">
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
                                            <ct-block name="ct_settings_tag_list_grid_column_assignments">
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

                                        <template #delete-confirm-text="{ item }">
                                            <p class="ct-settings-tag-list__confirm-delete-text">
                                                {{
                                                    $t(
                                                        'ct-settings-tag.list.textDeleteConfirm',
                                                        { name: item.name },
                                                        0,
                                                    )
                                                }}
                                            </p>
                                        </template>

                                        <template #empty-state>
                                            <ct-block name="ct_settings_tag_list_empty_state">
                                                <mt-empty-state
                                                    :icon="$route.meta.$module.icon"
                                                    :headline="$t('ct-settings-tag.list.titleEmptyStateList')"
                                                />
                                            </ct-block>
                                        </template>
                                    </ct-meteor-entity-data-table>

                                    <ct-block name="ct_settings_tag_list_grid_action_modals">
                                        <ct-block name="ct_settings_tag_list_duplicate_modal">
                                            <mt-modal-root
                                                v-if="tagToDuplicate"
                                                :is-open="true"
                                                @change="onCloseDuplicateModal"
                                            >
                                                <mt-modal :title="$t('global.default.duplicate')" width="s">
                                                    <ct-block
                                                        name="ct_settings_tag_list_delete_modal_confirm_duplicate_input"
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
                                                            <ct-block name="ct_settings_tag_list_duplicate_modal_footer">
                                                                <ct-block name="ct_settings_tag_list_duplicate_modal_cancel">
                                                                    <mt-modal-close
                                                                        as="mt-button"
                                                                        size="small"
                                                                        variant="secondary"
                                                                    >
                                                                        {{ $t('global.default.cancel') }}
                                                                    </mt-modal-close>
                                                                </ct-block>

                                                                <ct-block
                                                                    name="ct_settings_tag_list_duplicate_modal_confirm"
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

                                        <ct-block name="ct_settings_tag_list_detail_edit_modal">
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

                                    <ct-block name="ct_settings_tag_list_grid_bulk_merge_modal">
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

                                                <ct-block name="ct_settings_tag_list_merge_modal_confirm_name_input">
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

                                                <ct-block name="ct_settings_tag_list_merge_modal_progress">
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

                                    <ct-block name="ct_settings_tag_list_detail_add_modal">
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
const { page, limit, total: total2, term, onSearch, initializeListing } = useListing();
const total = total2;
const { createNotificationError } = useNotification();

const repositoryFactory = inject('repositoryFactory');
const acl = inject('acl');
const tagApiService = inject('tagApiService');

const tags = ref(null);
const tagTable = ref(null);
const sortBy = ref('name');
const isLoading = ref(false);
const sortDirection = ref('ASC');
const showDuplicateModal = ref(false);
const showBulkMergeModal = ref(false);
const duplicateName = ref(null);
const showDetailModal = ref(false);
const detailProperty = ref(null);
const detailEntity = ref(null);
const assignmentFilter = ref(null);
const emptyFilter = ref(false);
const duplicateFilter = ref(false);
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
const tagTableRepository = computed(() => {
    const repository = tagRepository.value;

    return {
        search: async (criteria, context) => {
            const result = await repository.search(criteria, context);
            tags.value = result;

            return result;
        },
        delete: repository.delete?.bind(repository),
        syncDeleted: repository.syncDeleted?.bind(repository),
    };
});
const tagDefinition = computed(() => {
    return Contena.EntityDefinition.get('tag');
});
const getTagById = (id) => {
    return tags.value?.find((tag) => tag.id === id) ?? null;
};
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
const transformTagCriteria = async (criteria) => {
    if (!duplicateFilter.value && !emptyFilter.value && !hasAssignmentFilter.value) {
        return criteria;
    }

    const { ids } = await tagApiService.filterIds(criteria.parse(), {
        duplicateFilter: duplicateFilter.value,
        emptyFilter: emptyFilter.value,
        assignmentFilter: assignmentFilter.value,
    });

    criteria.setIds(ids);

    return criteria;
};
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
    return tagTable.value?.reload?.() ?? Promise.resolve([]);
};
const onTableLoadSuccess = ({ records, total: totalValue }) => {
    if (!tags.value) {
        tags.value = records;
    }
    total2.value = totalValue;
};
const onTableLoadingChange = (loading) => {
    isLoading.value = loading;
};
const onTableTotalChange = (totalValue) => {
    total2.value = totalValue;
};
const onSelectedIdsChange = (ids) => {
    const selection = {};

    ids.forEach((id) => {
        const tag = getTagById(id);

        if (tag) {
            selection[id] = tag;
        }
    });

    selectedTagIds.value = ids;
    tagSelection.value = selection;
};
const getCounts = (id) => {
    const counts = {};
    const aggregations = tags.value?.aggregations ?? {};

    Object.entries(tagDefinition.value.properties).forEach(
        ([
            propertyName,
            property,
        ]) => {
            if (property.relation === 'many_to_many') {
                const countBucket = aggregations[propertyName]?.buckets.filter((bucket) => {
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
    const aggregation = tags.value?.aggregations?.[propertyName];

    if (!aggregation) {
        return 0;
    }

    const countBucket = aggregation.buckets.filter((bucket) => {
        return bucket.key === id;
    })[0];

    if (!countBucket || !countBucket[propertyName] || !countBucket[propertyName].count) {
        return 0;
    }

    return countBucket[propertyName].count;
};
const resetTagSelection = () => {
    selectedTagIds.value = [];
    tagSelection.value = {};
};
const onOpenDetails = (item) => {
    onDetail(item.id);
};
const onContextSelect = ({ key, data }) => {
    if (key === 'edit') {
        onDetail(data.id);
    }

    if (key === 'duplicate') {
        onDuplicate(data);
    }
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
initializeListing({
    getList,
    sortBy,
    sortDirection,
});

ctDefinePublic({
    repositoryFactory,
    acl,
    tagApiService,
    tags,
    sortBy,
    isLoading,
    sortDirection,
    showDuplicateModal,
    showBulkMergeModal,
    duplicateName,
    showDetailModal,
    detailProperty,
    detailEntity,
    assignmentFilter,
    emptyFilter,
    duplicateFilter,
    bulkMergeProgress,
    selectedTagIds,
    tagSelection,
    tagRepository,
    tagDefinition,
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
    getCounts,
    getPropertyCounting,
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
    resetTagSelection,
    onOpenDetails,
    onContextSelect,
});
</script>

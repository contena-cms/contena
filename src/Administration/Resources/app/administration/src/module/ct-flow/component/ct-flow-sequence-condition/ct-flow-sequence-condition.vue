<template>
    <ct-block name="sw_flow_sequence_condition">
        <div class="ct-flow-sequence-condition">
            <ct-block name="sw_flow_sequence_condition_true_arrow">
                <template v-if="!showHelpElement">
                    <div class="ct-flow-sequence-condition__true-arrow" :class="{ 'has--selector': isSelector(true) }">
                        <div class="ct-flow-sequence-condition__true-line"></div>
                        <div class="ct-flow-sequence-condition__oval"></div>
                        <mt-icon v-if="showArrowIcon(true)" name="regular-chevron-right-xs" size="12px" />
                        <mt-dropdown-menu-root v-else>
                            <mt-dropdown-menu-trigger as-child>
                                <mt-button
                                    class="ct-flow-sequence-condition__branch-add is--true"
                                    variant="secondary"
                                    size="x-small"
                                    square
                                    :disabled="branchIsDisabled(true) || undefined"
                                    :aria-label="$t('ct-flow.sequence.addTrueBranch')"
                                >
                                    <mt-icon name="regular-plus-xs" size="12px" />
                                </mt-button>
                            </mt-dropdown-menu-trigger>
                            <mt-dropdown-menu-portal>
                                <mt-action-menu>
                                    <mt-action-menu-item @select="chooseBranch('true', 'condition')">
                                        {{ $t('ct-flow.sequence.addConditionIf') }}
                                    </mt-action-menu-item>
                                    <mt-action-menu-item @select="chooseBranch('true', 'action')">
                                        {{ $t('ct-flow.sequence.addActionThen') }}
                                    </mt-action-menu-item>
                                </mt-action-menu>
                            </mt-dropdown-menu-portal>
                        </mt-dropdown-menu-root>
                        <span class="ct-flow-sequence-condition__true-label">
                            {{ $t('ct-flow.sequence.trueBranch') }}
                        </span>
                    </div>
                </template>
            </ct-block>

            <ct-block name="sw_flow_sequence_condition_false_arrow">
                <template v-if="!showHelpElement">
                    <div class="ct-flow-sequence-condition__false-arrow" :class="{ 'has--selector': isSelector(false) }">
                        <div class="ct-flow-sequence-condition__false-line"></div>
                        <div class="ct-flow-sequence-condition__oval"></div>
                        <span class="ct-flow-sequence-condition__false-label">
                            {{ $t('ct-flow.sequence.falseBranch') }}
                        </span>
                        <mt-icon v-if="showArrowIcon(false)" name="regular-chevron-down-xs" size="12px" />
                        <mt-dropdown-menu-root v-else>
                            <mt-dropdown-menu-trigger as-child>
                                <mt-button
                                    class="ct-flow-sequence-condition__branch-add is--false"
                                    variant="secondary"
                                    size="x-small"
                                    square
                                    :disabled="branchIsDisabled(false) || undefined"
                                    :aria-label="$t('ct-flow.sequence.addFalseBranch')"
                                >
                                    <mt-icon name="regular-plus-xs" size="12px" />
                                </mt-button>
                            </mt-dropdown-menu-trigger>
                            <mt-dropdown-menu-portal>
                                <mt-action-menu>
                                    <mt-action-menu-item @select="chooseBranch('false', 'condition')">
                                        {{ $t('ct-flow.sequence.addConditionIf') }}
                                    </mt-action-menu-item>
                                    <mt-action-menu-item @select="chooseBranch('false', 'action')">
                                        {{ $t('ct-flow.sequence.addActionThen') }}
                                    </mt-action-menu-item>
                                </mt-action-menu>
                            </mt-dropdown-menu-portal>
                        </mt-dropdown-menu-root>
                    </div>
                </template>
            </ct-block>

            <ct-block name="sw_flow_sequence_condition_container">
                <div class="ct-flow-sequence-condition__container">
                    <div class="ct-flow-sequence-condition__card">
                        <div class="ct-flow-sequence-condition__header">
                            <div class="ct-flow-sequence-condition__title-description">
                                <h3 class="ct-flow-sequence-condition__title">{{ $t('ct-flow.sequence.condition') }}</h3>
                                <p class="ct-flow-sequence-condition__description">({{ $t('ct-flow.sequence.if') }})</p>
                            </div>

                            <mt-dropdown-menu-root>
                                <mt-dropdown-menu-trigger as-child>
                                    <mt-button
                                        class="ct-flow-sequence-condition__context-button"
                                        variant="tertiary"
                                        square
                                        :disabled="disabled || undefined"
                                        :aria-label="$t('ct-flow.sequence.openMenu')"
                                    >
                                        <mt-icon name="ellipsis-h" size="16px" />
                                    </mt-button>
                                </mt-dropdown-menu-trigger>
                                <mt-dropdown-menu-portal>
                                    <mt-action-menu>
                                        <mt-action-menu-item variant="critical" @select="removeCondition">
                                            {{ $t('ct-flow.sequence.deleteCondition') }}
                                        </mt-action-menu-item>
                                    </mt-action-menu>
                                </mt-dropdown-menu-portal>
                            </mt-dropdown-menu-root>
                        </div>

                        <div class="ct-flow-sequence-condition__content">
                            <div
                                class="ct-flow-sequence-condition__rule"
                                :role="sequence.ruleId ? 'button' : undefined"
                                :tabindex="sequence.ruleId ? 0 : undefined"
                                @click="openRuleModal(sequence.ruleId)"
                                @keydown.enter="openRuleModal(sequence.ruleId)"
                            >
                                <div v-if="!sequence.ruleId" class="ct-flow-sequence-condition__rule-empty">
                                    {{ $t('ct-flow.sequence.noRule') }}
                                </div>
                                <div v-else class="ct-flow-sequence-condition__rule-info">
                                    <div class="ct-flow-sequence-condition__rule-header">
                                        <div class="ct-flow-sequence-condition__rule-icon">
                                            <mt-icon name="regular-rule-s" size="12px" />
                                        </div>
                                        <div class="ct-flow-sequence-condition__rule-name">
                                            <h3>{{ sequence.rule?.name || sequence.ruleId }}</h3>
                                        </div>
                                        <div @click.stop @keydown.stop>
                                            <mt-dropdown-menu-root>
                                                <mt-dropdown-menu-trigger as-child>
                                                    <mt-button
                                                        class="ct-flow-sequence-condition__rule-context-button"
                                                        variant="tertiary"
                                                        square
                                                        :disabled="disabled || undefined"
                                                        :aria-label="$t('ct-flow.sequence.openMenu')"
                                                    >
                                                        <mt-icon name="ellipsis-h" size="16px" />
                                                    </mt-button>
                                                </mt-dropdown-menu-trigger>
                                                <mt-dropdown-menu-portal>
                                                    <mt-action-menu>
                                                        <mt-action-menu-item @select="openRuleModal(sequence.ruleId)">
                                                            {{ $t('ct-flow.sequence.editRule') }}
                                                        </mt-action-menu-item>
                                                        <mt-action-menu-item @select="showRuleSelection = true">
                                                            {{ $t('ct-flow.sequence.changeRule') }}
                                                        </mt-action-menu-item>
                                                        <mt-action-menu-item variant="critical" @select="deleteRule">
                                                            {{ $t('ct-flow.sequence.deleteRule') }}
                                                        </mt-action-menu-item>
                                                    </mt-action-menu>
                                                </mt-dropdown-menu-portal>
                                            </mt-dropdown-menu-root>
                                        </div>
                                    </div>
                                    <p
                                        v-if="sequence.rule?.description"
                                        class="ct-flow-sequence-condition__rule-description"
                                    >
                                        {{ sequence.rule.description }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="showRuleSelection || !sequence.ruleId" class="ct-flow-sequence-condition__select">
                                <mt-entity-select
                                    entity="rule"
                                    :model-value="sequence.ruleId"
                                    :placeholder="$t('ct-flow.sequence.rulePlaceholder')"
                                    :disabled="disabled || undefined"
                                    @update:model-value="setRule"
                                >
                                    <template #before-item-list>
                                        <div class="ct-flow-sequence-condition__rule-select-actions">
                                            <mt-button
                                                class="ct-flow-sequence-condition__advanced-rule"
                                                variant="tertiary"
                                                :disabled="disabled || undefined"
                                                @click="openAdvancedRuleSelection"
                                            >
                                                {{ $t('ct-flow.sequence.advancedRuleSelection') }}
                                            </mt-button>
                                            <mt-button
                                                class="ct-flow-sequence-condition__create-rule"
                                                variant="tertiary"
                                                :disabled="disabled || undefined"
                                                @click="openRuleModal(null)"
                                            >
                                                {{ $t('ct-flow.sequence.createRule') }}
                                            </mt-button>
                                        </div>
                                    </template>
                                </mt-entity-select>
                            </div>
                        </div>
                    </div>

                    <div v-if="showHelpElement" class="ct-flow-sequence-condition__explains">
                        <h4>{{ $t('ct-flow.sequence.conditionHelpTitle') }}</h4>
                        <p>{{ $t('ct-flow.sequence.conditionHelp') }}</p>
                    </div>
                </div>
            </ct-block>

            <ct-flow-rule-modal
                v-if="showRuleModal"
                :rule-id="selectedRuleId"
                @process-finish="onRuleSaved"
                @modal-close="closeRuleModal"
            />

            <mt-modal-root v-if="showAdvancedRuleSelection" :is-open="true" @change="onAdvancedModalChange">
                <mt-modal :title="$t('ct-flow.sequence.advancedRuleTitle')" width="l">
                    <mt-data-table
                        layout="full"
                        :data-source="advancedRules"
                        :columns="ruleColumns"
                        :current-page="advancedRulePage"
                        :pagination-limit="advancedRuleLimit"
                        :pagination-total-items="advancedRuleTotal"
                        :search-value="advancedRuleSearch"
                        :sort-by="advancedRuleSortBy"
                        :sort-direction="advancedRuleSortDirection"
                        :selected-rows="selectedAdvancedRuleIds"
                        :filters="advancedRuleFilters"
                        :applied-filters="advancedRuleAppliedFilters"
                        :is-loading="isLoadingAdvancedRules"
                        :number-of-results="advancedRuleTotal"
                        allow-row-selection
                        :disable-delete="true"
                        :disable-edit="true"
                        :disable-settings-table="true"
                        @pagination-current-page-change="onAdvancedRulePageChange"
                        @pagination-limit-change="onAdvancedRuleLimitChange"
                        @search-value-change="onAdvancedRuleSearchChange"
                        @sort-change="onAdvancedRuleSortChange"
                        @selection-change="onAdvancedRuleSelectionChange"
                        @multiple-selection-change="onAdvancedRuleMultipleSelectionChange"
                        @update:applied-filters="onAdvancedRuleFiltersChange"
                        @open-details="selectAdvancedRule"
                    />
                    <template #footer>
                        <div class="ct-flow-modal__footer-actions">
                            <mt-button variant="secondary" @click="closeAdvancedRuleSelection">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                            <mt-button
                                variant="primary"
                                :disabled="selectedAdvancedRuleIds.length !== 1 || undefined"
                                @click="applyAdvancedRuleSelection"
                            >
                                {{ $t('ct-flow.sequence.applyRuleSelection') }}
                            </mt-button>
                        </div>
                    </template>
                </mt-modal>
            </mt-modal-root>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, inject, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import { createSelectorSequence, type EditableFlowSequence } from '../flow-sequence.types';

const { cloneDeep } = Contena.Utils.object;
const i18n = useI18n();
const props = defineProps({
    sequence: { type: Object as PropType<EditableFlowSequence>, required: true },
    isRoot: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits<{
    'update:sequence': [sequence: EditableFlowSequence];
    remove: [];
    'choose-branch': [branch: 'true' | 'false', type: 'condition' | 'action'];
}>();

interface AdvancedRuleRow {
    id: string;
    name: string;
    priority: number;
    description: string;
    createdAt: string;
    status: string;
}

interface AdvancedRuleFilter {
    id: string;
    label: string;
    type: {
        id: string;
        options: Array<{ id: string; label: string }>;
    };
}

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory', undefined);
const ruleRepository = repositoryFactory?.create('rule');
const showRuleSelection = ref(false);
const showRuleModal = ref(false);
const showAdvancedRuleSelection = ref(false);
const selectedRuleId = ref<string | null>(null);
const advancedRules = ref<AdvancedRuleRow[]>([]);
const advancedRulePage = ref(1);
const advancedRuleLimit = ref(25);
const advancedRuleTotal = ref(0);
const advancedRuleSearch = ref('');
const advancedRuleSortBy = ref('createdAt');
const advancedRuleSortDirection = ref<'ASC' | 'DESC'>('DESC');
const selectedAdvancedRuleIds = ref<string[]>([]);
const advancedRuleAppliedFilters = ref<AdvancedRuleFilter[]>([]);
const isLoadingAdvancedRules = ref(false);
const ruleColumns = [
    {
        label: i18n.t('ct-flow.sequence.ruleName'),
        property: 'name',
        renderer: 'text',
        position: 100,
        sortable: true,
        clickable: true,
    },
    {
        label: i18n.t('ct-flow.sequence.rulePriority'),
        property: 'priority',
        renderer: 'number',
        position: 200,
        sortable: true,
    },
    {
        label: i18n.t('ct-flow.sequence.ruleDescription'),
        property: 'description',
        renderer: 'text',
        position: 300,
        sortable: false,
    },
    {
        label: i18n.t('ct-flow.sequence.ruleCreatedAt'),
        property: 'createdAt',
        renderer: 'text',
        position: 400,
        sortable: true,
    },
    {
        label: i18n.t('ct-flow.sequence.ruleStatus'),
        property: 'status',
        renderer: 'text',
        position: 500,
        sortable: true,
    },
];
const advancedRuleFilters = computed<AdvancedRuleFilter[]>(() => [
    {
        id: 'status',
        label: i18n.t('ct-flow.sequence.ruleStatus'),
        type: {
            id: 'options',
            options: [
                { id: 'valid', label: i18n.t('ct-flow.sequence.ruleValid') },
                { id: 'invalid', label: i18n.t('ct-flow.sequence.ruleInvalid') },
            ],
        },
    },
]);
const showHelpElement = computed(
    () => props.isRoot && !props.sequence.ruleId && !props.sequence.trueChild && !props.sequence.falseChild,
);
const childFor = (trueCase: boolean): EditableFlowSequence | null =>
    trueCase ? props.sequence.trueChild : props.sequence.falseChild;
const isSelector = (trueCase: boolean): boolean => childFor(trueCase)?.type === 'selector';
const showArrowIcon = (trueCase: boolean): boolean => {
    const child = childFor(trueCase);
    return Boolean(child && child.type !== 'selector');
};
const branchIsDisabled = (trueCase: boolean): boolean => props.disabled || (props.isRoot && isSelector(trueCase));
const setRule = async (ruleId: string | null): Promise<void> => {
    const updated = cloneDeep(props.sequence);
    updated.ruleId = ruleId;
    updated.rule = ruleId ? { id: ruleId, name: ruleId, description: null } : null;
    if (ruleId && props.isRoot) {
        updated.trueChild ??= createSelectorSequence();
        updated.falseChild ??= createSelectorSequence();
    }
    emit('update:sequence', updated);
    showRuleSelection.value = false;

    if (!ruleId || !ruleRepository) return;
    try {
        const rule = (await ruleRepository.get(ruleId, Contena.Context.api)) as Entity<'rule'>;
        updated.rule = { id: rule.id, name: rule.name, description: rule.description ?? null };
        emit('update:sequence', updated);
    } catch {
        // Keep the stable technical id visible if the selected rule cannot be reloaded.
    }
};
const openRuleModal = (ruleId: string | null): void => {
    selectedRuleId.value = ruleId;
    showRuleModal.value = true;
};
const closeRuleModal = (): void => {
    selectedRuleId.value = null;
    showRuleModal.value = false;
};
const formatRuleDate = (value: string | null | undefined): string => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat(i18n.locale.value, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(date);
};
const loadAdvancedRules = async (): Promise<void> => {
    if (!ruleRepository) return;
    isLoadingAdvancedRules.value = true;
    try {
        const criteria = new Contena.Data.Criteria(advancedRulePage.value, advancedRuleLimit.value);
        criteria.setTerm(advancedRuleSearch.value);
        const sortField = advancedRuleSortBy.value === 'status' ? 'invalid' : advancedRuleSortBy.value;
        criteria.addSorting(Contena.Data.Criteria.sort(sortField, advancedRuleSortDirection.value));
        const status = advancedRuleAppliedFilters.value.find((filter) => filter.id === 'status')?.type.options.at(0)?.id;
        if (status === 'valid' || status === 'invalid') {
            criteria.addFilter(Contena.Data.Criteria.equals('invalid', status === 'invalid'));
        }
        const result = await ruleRepository.search(criteria, Contena.Context.api);
        advancedRuleTotal.value = result.total;
        advancedRules.value = [...result].map((rule) => ({
            id: rule.id,
            name: rule.name,
            priority: rule.priority,
            description: rule.description ?? '',
            createdAt: formatRuleDate(rule.updatedAt ?? rule.createdAt),
            status: i18n.t(rule.invalid ? 'ct-flow.sequence.ruleInvalid' : 'ct-flow.sequence.ruleValid'),
        }));
    } catch {
        advancedRules.value = [];
        advancedRuleTotal.value = 0;
    } finally {
        isLoadingAdvancedRules.value = false;
    }
};
const openAdvancedRuleSelection = (): void => {
    advancedRulePage.value = 1;
    advancedRuleSearch.value = '';
    advancedRuleAppliedFilters.value = [];
    selectedAdvancedRuleIds.value = props.sequence.ruleId ? [props.sequence.ruleId] : [];
    showAdvancedRuleSelection.value = true;
    void loadAdvancedRules();
};
const closeAdvancedRuleSelection = (): void => {
    showAdvancedRuleSelection.value = false;
};
const onAdvancedModalChange = (isOpen: boolean): void => {
    if (!isOpen) closeAdvancedRuleSelection();
};
const selectAdvancedRule = (rule: { id: string }): void => {
    selectedAdvancedRuleIds.value = [rule.id];
};
const applyAdvancedRuleSelection = async (): Promise<void> => {
    const ruleId = selectedAdvancedRuleIds.value[0];
    if (!ruleId) return;
    await setRule(ruleId);
    closeAdvancedRuleSelection();
};
const onAdvancedRulePageChange = (page: number): void => {
    advancedRulePage.value = page;
    void loadAdvancedRules();
};
const onAdvancedRuleLimitChange = (limit: number): void => {
    advancedRuleLimit.value = limit;
    advancedRulePage.value = 1;
    void loadAdvancedRules();
};
const onAdvancedRuleSearchChange = (search: string): void => {
    advancedRuleSearch.value = search;
    advancedRulePage.value = 1;
    void loadAdvancedRules();
};
const onAdvancedRuleSortChange = (sortBy: string, sortDirection: 'ASC' | 'DESC'): void => {
    advancedRuleSortBy.value = sortBy;
    advancedRuleSortDirection.value = sortDirection;
    void loadAdvancedRules();
};
const onAdvancedRuleSelectionChange = ({ id, value }: { id: string; value: boolean }): void => {
    selectedAdvancedRuleIds.value = value ? [id] : [];
};
const onAdvancedRuleMultipleSelectionChange = ({ selections, value }: { selections: string[]; value: boolean }): void => {
    selectedAdvancedRuleIds.value = value && selections[0] ? [selections[0]] : [];
};
const onAdvancedRuleFiltersChange = (filters: AdvancedRuleFilter[]): void => {
    advancedRuleAppliedFilters.value = filters;
    advancedRulePage.value = 1;
    void loadAdvancedRules();
};
const onRuleSaved = (rule: { id: string; name: string; description?: string | null }): void => {
    const updated = cloneDeep(props.sequence);
    updated.ruleId = rule.id;
    updated.rule = { id: rule.id, name: rule.name, description: rule.description ?? null };
    if (props.isRoot) {
        updated.trueChild ??= createSelectorSequence();
        updated.falseChild ??= createSelectorSequence();
    }
    emit('update:sequence', updated);
    showRuleSelection.value = false;
    closeRuleModal();
};
const deleteRule = (): void => {
    const updated = cloneDeep(props.sequence);
    updated.ruleId = null;
    updated.rule = null;
    emit('update:sequence', updated);
    showRuleSelection.value = true;
};
const chooseBranch = (branch: 'true' | 'false', type: 'condition' | 'action'): void => {
    emit('choose-branch', branch, type);
};
const removeCondition = (): void => emit('remove');

swDefinePublic({
    showRuleSelection,
    showRuleModal,
    showAdvancedRuleSelection,
    selectedRuleId,
    ruleColumns,
    advancedRules,
    advancedRulePage,
    advancedRuleLimit,
    advancedRuleTotal,
    advancedRuleSearch,
    advancedRuleSortBy,
    advancedRuleSortDirection,
    selectedAdvancedRuleIds,
    advancedRuleFilters,
    advancedRuleAppliedFilters,
    isLoadingAdvancedRules,
    showHelpElement,
    isSelector,
    showArrowIcon,
    branchIsDisabled,
    setRule,
    openRuleModal,
    closeRuleModal,
    openAdvancedRuleSelection,
    closeAdvancedRuleSelection,
    onAdvancedModalChange,
    selectAdvancedRule,
    applyAdvancedRuleSelection,
    onAdvancedRulePageChange,
    onAdvancedRuleLimitChange,
    onAdvancedRuleSearchChange,
    onAdvancedRuleSortChange,
    onAdvancedRuleSelectionChange,
    onAdvancedRuleMultipleSelectionChange,
    onAdvancedRuleFiltersChange,
    onRuleSaved,
    deleteRule,
    chooseBranch,
    removeCondition,
});

defineExpose({
    showRuleSelection,
    showRuleModal,
    showAdvancedRuleSelection,
    selectedRuleId,
    ruleColumns,
    advancedRules,
    advancedRulePage,
    advancedRuleLimit,
    advancedRuleTotal,
    advancedRuleSearch,
    advancedRuleSortBy,
    advancedRuleSortDirection,
    selectedAdvancedRuleIds,
    advancedRuleFilters,
    advancedRuleAppliedFilters,
    isLoadingAdvancedRules,
    showHelpElement,
    isSelector,
    showArrowIcon,
    branchIsDisabled,
    setRule,
    openRuleModal,
    closeRuleModal,
    openAdvancedRuleSelection,
    closeAdvancedRuleSelection,
    onAdvancedModalChange,
    selectAdvancedRule,
    applyAdvancedRuleSelection,
    onAdvancedRulePageChange,
    onAdvancedRuleLimitChange,
    onAdvancedRuleSearchChange,
    onAdvancedRuleSortChange,
    onAdvancedRuleSelectionChange,
    onAdvancedRuleMultipleSelectionChange,
    onAdvancedRuleFiltersChange,
    onRuleSaved,
    deleteRule,
    chooseBranch,
    removeCondition,
});
</script>

<style scoped>
.ct-flow-sequence-condition {
    position: relative;
    display: grid;
    grid-template-columns: min-content;
    grid-template-rows: min-content;
}

.ct-flow-sequence-condition__container {
    grid-row: 1;
    grid-column: 1;
    width: 20.5rem;
}

.ct-flow-sequence-condition__card {
    max-width: 20.5rem;
    overflow: hidden;
    border: 1px solid var(--color-border-secondary-default, #d8dde6);
    border-radius: var(--border-radius-card, 8px);
    background: var(--color-elevation-surface-raised, #fff);
    box-shadow: 0 3px 6px 0 var(--color-elevation-shadow-default, rgb(0 0 0 / 10%));
}

.ct-flow-sequence-condition__header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: var(--scale-size-16, 16px) var(--scale-size-12, 12px) var(--scale-size-12, 12px) var(--scale-size-20, 20px);
    border-bottom: 1px solid var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-condition__title-description {
    display: flex;
    gap: var(--scale-size-4, 4px);
}

.ct-flow-sequence-condition__title,
.ct-flow-sequence-condition__description {
    margin: 0;
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
    font-weight: var(--font-weight-semibold, 600);
}

.ct-flow-sequence-condition__rule-empty {
    padding: var(--scale-size-16, 16px) var(--scale-size-20, 20px);
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
}

.ct-flow-sequence-condition__rule-info {
    padding: var(--scale-size-12, 12px) var(--scale-size-12, 12px) var(--scale-size-16, 16px) var(--scale-size-20, 20px);
}

.ct-flow-sequence-condition__rule:has(.ct-flow-sequence-condition__rule-info) {
    cursor: pointer;
}

.ct-flow-sequence-condition__rule:has(.ct-flow-sequence-condition__rule-info):hover {
    background: var(--color-elevation-surface-sunken, #f4f6f8);
}

.ct-flow-sequence-condition__rule-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ct-flow-sequence-condition__rule-icon {
    display: flex;
    color: var(--color-icon-primary-default, #52667a);
}

.ct-flow-sequence-condition__rule-name {
    flex-basis: 100%;
    overflow: hidden;
    overflow-wrap: anywhere;
}

.ct-flow-sequence-condition__rule-name h3 {
    margin: 0 0 0 var(--scale-size-6, 6px);
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
    font-weight: var(--font-weight-medium, 500);
}

.ct-flow-sequence-condition__rule-description {
    margin: var(--scale-size-6, 6px) 0 var(--scale-size-2, 2px);
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
    line-height: var(--font-line-height-xs, 20px);
}

.ct-flow-sequence-condition__select {
    display: grid;
    gap: var(--scale-size-8, 8px);
    width: 100%;
    padding: var(--scale-size-16, 16px) var(--scale-size-20, 20px);
    border-top: 1px solid var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-condition__rule-select-actions {
    display: flex;
    flex-direction: column;
    padding: var(--scale-size-4, 4px);
    border-bottom: 1px solid var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-condition__rule-select-actions .mt-button {
    justify-content: flex-start;
    width: 100%;
}

.ct-flow-sequence-condition__select :deep(.mt-field),
.ct-flow-sequence-condition__select :deep(.mt-entity-select) {
    margin-bottom: 0;
}

.ct-flow-sequence-condition__true-arrow {
    position: relative;
    top: 40px;
    grid-row: 1;
    grid-column: 2;
    width: 100%;
    min-width: var(--scale-size-128, 128px);
}

.ct-flow-sequence-condition__false-arrow {
    position: relative;
    left: 50%;
    grid-row: 2;
    grid-column: 1;
    width: 50%;
    height: 100%;
    min-height: var(--scale-size-72, 72px);
}

.ct-flow-sequence-condition__true-line {
    position: absolute;
    width: calc(100% - var(--scale-size-6, 6px));
    border-top: 2px dashed var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-condition__false-line {
    position: absolute;
    left: -2px;
    height: calc(100% - var(--scale-size-6, 6px));
    border-left: 2px dashed var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-condition__oval {
    position: absolute;
    width: var(--scale-size-12, 12px);
    height: var(--scale-size-12, 12px);
    border: 1px solid var(--color-border-secondary-default, #d8dde6);
    border-radius: var(--border-radius-round, 50%);
    background: var(--color-elevation-surface-raised, #fff);
}

.ct-flow-sequence-condition__true-arrow .ct-flow-sequence-condition__oval {
    top: -5px;
    left: -6px;
}

.ct-flow-sequence-condition__false-arrow .ct-flow-sequence-condition__oval {
    top: -7px;
    left: -7px;
}

.ct-flow-sequence-condition__true-arrow > .mt-icon {
    position: absolute;
    top: -5px;
    right: -2px;
    color: var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-condition__false-arrow > .mt-icon {
    position: absolute;
    bottom: -2px;
    left: -7px;
    color: var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-condition__true-label,
.ct-flow-sequence-condition__false-label {
    position: absolute;
    display: inline-flex;
    align-items: center;
    height: var(--scale-size-24, 24px);
    padding: 0 var(--scale-size-10, 10px);
    border-radius: var(--border-radius-pill, 999px);
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-2xs, 12px);
    font-weight: var(--font-weight-medium, 500);
}

.ct-flow-sequence-condition__true-label {
    left: 50px;
    border: 1px solid var(--color-border-positive-default, #37d046);
    background: var(--color-background-positive-default, #e9fbe9);
    transform: translate(-50%, -55%);
}

.ct-flow-sequence-condition__false-label {
    top: 30px;
    border: 1px solid var(--color-border-critical-default, #de294c);
    background: var(--color-background-critical-default, #fde9ed);
    transform: translate(-50%, -50%);
}

.ct-flow-sequence-condition__branch-add.is--true {
    position: absolute;
    z-index: 2;
    top: -12px;
    right: -12px;
}

.ct-flow-sequence-condition__branch-add.is--false {
    position: absolute;
    z-index: 2;
    bottom: -14px;
    left: -13px;
}

.ct-flow-sequence-condition__branch-add {
    width: var(--scale-size-24, 24px);
    height: var(--scale-size-24, 24px);
    border-radius: 50%;
}

.ct-flow-sequence-condition__explains {
    position: absolute;
    top: 0;
    left: 360px;
    width: 20.5rem;
    padding: var(--scale-size-24, 24px);
    border: 2px dashed var(--color-border-secondary-default, #d8dde6);
    border-radius: var(--border-radius-card, 8px);
    color: var(--color-text-primary-default, #2b3137);
}

.ct-flow-sequence-condition__explains h4 {
    margin: 0 0 var(--scale-size-4, 4px);
    font-size: var(--font-size-s, 16px);
    font-weight: var(--font-weight-medium, 500);
}

.ct-flow-sequence-condition__explains p {
    margin: 0;
    font-size: var(--font-size-xs, 14px);
    line-height: var(--font-line-height-xs, 20px);
}
</style>

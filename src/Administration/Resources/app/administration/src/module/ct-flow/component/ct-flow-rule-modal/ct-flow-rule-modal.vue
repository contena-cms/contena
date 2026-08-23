<template>
    <ct-block name="sw_flow_rule_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal :title="modalTitle" width="l">
                <ct-block name="sw_flow_rule_modal_tabs">
                    <mt-tabs
                        position-identifier="ct-flow-rule-modal"
                        :default-item="activeTab"
                        :items="tabs"
                        :use-routes-for-extensions="false"
                        :small="true"
                        @new-item-active="activeTab = $event"
                    />
                </ct-block>

                <ct-block name="sw_flow_rule_modal_content">
                    <div class="ct-flow-rule-modal__content">
                        <template v-if="activeTab === 'detail' && rule">
                            <ct-block name="sw_flow_rule_modal_detail">
                                <ct-container columns="2fr 1fr" gap="0 32px">
                                    <mt-text-field
                                        v-model="rule.name"
                                        class="ct-flow-rule-modal__name"
                                        required
                                        :label="$t('ct-settings-rule.detail.name')"
                                        :disabled="!canEdit || undefined"
                                    />
                                    <mt-number-field
                                        v-model="rule.priority"
                                        class="ct-flow-rule-modal__priority"
                                        required
                                        number-type="int"
                                        :min="1"
                                        :label="$t('ct-settings-rule.detail.priority')"
                                        :disabled="!canEdit || undefined"
                                    />
                                </ct-container>
                                <mt-textarea
                                    v-model="rule.description"
                                    class="ct-flow-rule-modal__description"
                                    :label="$t('ct-settings-rule.detail.description')"
                                    :disabled="!canEdit || undefined"
                                />
                            </ct-block>
                        </template>

                        <ct-block name="sw_flow_rule_modal_conditions">
                            <template v-if="activeTab !== 'detail' || !rule">
                                <ct-rule-condition-editor
                                    v-model:mode="matchMode"
                                    v-model:conditions="conditions"
                                    class="ct-flow-rule-modal__rule"
                                    :disabled="!canEdit"
                                />
                            </template>
                        </ct-block>
                    </div>
                </ct-block>

                <template #footer>
                    <ct-block name="sw_flow_rule_modal_footer">
                        <div class="ct-flow-modal__footer-actions">
                            <mt-button variant="secondary" @click="onClose">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                            <mt-button
                                variant="primary"
                                :is-loading="isLoading"
                                :disabled="!canSave || undefined"
                                @click="onSave"
                            >
                                {{ $t('global.default.save') }}
                            </mt-button>
                        </div>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
/* global Entity, EntityCollection */
import { computed, inject, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import { useNotification } from 'src/app/composables/use-notification';

import type { EditableRuleCondition } from '../../../ct-settings-rule/component/ct-rule-condition-editor/ct-rule-condition-editor.vue';
import type { FlowRuleSummary } from '../flow-sequence.types';

const { Criteria } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;
const props = defineProps({ ruleId: { type: String, default: null } });
const emit = defineEmits<{ 'process-finish': [rule: FlowRuleSummary]; 'modal-close': [] }>();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const ruleRepository = computed(() => repositoryFactory?.create('rule'));
const conditionRepository = computed(() => repositoryFactory?.create('rule_condition'));
const rule = ref<Entity<'rule'> | null>(null);
const persistedConditionIds = ref<string[]>([]);
const conditions = ref<EditableRuleCondition[]>([]);
const matchMode = ref<'all' | 'any'>('all');
const activeTab = ref<'detail' | 'rule'>('detail');
const isLoading = ref(false);
const canEdit = computed(() => Boolean(rule.value?.isNew() ? acl?.can('rule.creator') : acl?.can('rule.editor')));
const canSave = computed(() =>
    Boolean(canEdit.value && rule.value?.name?.trim() && conditions.value.length > 0 && !isLoading.value),
);
const tabs = computed(() => [
    { label: t('ct-flow.ruleModal.detailTab'), name: 'detail' as const },
    { label: t('ct-flow.ruleModal.ruleTab'), name: 'rule' as const },
]);
const modalTitle = computed(() => (props.ruleId ? t('ct-flow.ruleModal.editTitle') : t('ct-flow.ruleModal.createTitle')));

const toEditorCondition = (condition: Entity<'rule_condition'>, all: Entity<'rule_condition'>[]): EditableRuleCondition => ({
    key: condition.id,
    type: condition.type as EditableRuleCondition['type'],
    value: cloneDeep((condition.value as Record<string, unknown>) ?? {}),
    children: condition.type.endsWith('Container')
        ? all
              .filter((child) => child.parentId === condition.id)
              .sort((left, right) => (left.position ?? 0) - (right.position ?? 0))
              .map((child) => toEditorCondition(child, all))
        : undefined,
});
const hydrateConditions = (stored: EntityCollection<'rule_condition'> | undefined): void => {
    const all = stored ? [...stored] : [];
    persistedConditionIds.value = all.map((condition) => condition.id);
    const containerRoot = all.find((condition) => condition.parentId === null && condition.type.endsWith('Container'));
    if (containerRoot) {
        matchMode.value = containerRoot.type === 'orContainer' ? 'any' : 'all';
        conditions.value = all
            .filter((condition) => condition.parentId === containerRoot.id)
            .sort((left, right) => (left.position ?? 0) - (right.position ?? 0))
            .map((condition) => toEditorCondition(condition, all));
        return;
    }
    conditions.value = all
        .filter((condition) => condition.parentId === null)
        .sort((left, right) => (left.position ?? 0) - (right.position ?? 0))
        .map((condition) => toEditorCondition(condition, all));
};
const createRuleCriteria = (): InstanceType<typeof Criteria> => {
    const criteria = new Criteria(1, 1);
    const conditionCriteria = criteria.getAssociation('conditions');
    conditionCriteria.setLimit(500);
    conditionCriteria.addSorting(Criteria.sort('parentId'));
    conditionCriteria.addSorting(Criteria.sort('position'));
    conditionCriteria.addSorting(Criteria.sort('id'));

    return criteria;
};
const loadRemainingConditions = async (stored: EntityCollection<'rule_condition'>): Promise<void> => {
    if (stored.total === null || stored.total === undefined || stored.total <= stored.length || !conditionRepository.value) {
        return;
    }

    const currentPage = stored.criteria?.page ?? 1;
    const criteria = new Criteria(currentPage + 1, 500);
    criteria.addSorting(Criteria.sort('parentId'));
    criteria.addSorting(Criteria.sort('position'));
    criteria.addSorting(Criteria.sort('id'));

    const nextPage = await conditionRepository.value.search(criteria, stored.context ?? Contena.Context.api);
    stored.push(...nextPage);
    stored.criteria = nextPage.criteria;

    await loadRemainingConditions(stored);
};
const load = async (): Promise<void> => {
    if (!ruleRepository.value) return;
    isLoading.value = true;
    try {
        if (!props.ruleId) {
            const created = ruleRepository.value.create();
            created.priority = 1;
            rule.value = created;
            conditions.value = [
                {
                    key: Contena.Utils.createId(),
                    type: 'timeRange',
                    value: { fromTime: '09:00', toTime: '18:00', timezone: null },
                },
            ];
            return;
        }
        const criteria = createRuleCriteria();
        criteria.addAssociation('conditions');
        rule.value = await ruleRepository.value.get(props.ruleId, Contena.Context.api, criteria);
        if (rule.value?.conditions) {
            await loadRemainingConditions(rule.value.conditions);
        }
        hydrateConditions(rule.value?.conditions);
    } finally {
        isLoading.value = false;
    }
};
const normalizedValue = (condition: EditableRuleCondition): Record<string, unknown> | null => {
    if (condition.type.endsWith('Container')) return null;
    const value = cloneDeep(condition.value);
    if (condition.type === 'dateRange') {
        for (const field of [
            'fromDate',
            'toDate',
        ]) {
            if (typeof value[field] === 'string' && value[field].length === 16) value[field] += ':00';
        }
        value.useTime = true;
        value.timezone ??= null;
    }
    return value;
};
interface PendingCondition {
    id: string;
    parentId: string | null;
    item: EditableRuleCondition;
    position: number;
}
const flattenConditions = (items: EditableRuleCondition[], parentId: string | null): PendingCondition[] =>
    items.flatMap((item, index) => {
        const id = Contena.Utils.createId();
        return [
            { id, parentId, item, position: index + 1 },
            ...flattenConditions(item.children ?? [], id),
        ];
    });
const saveConditions = async (): Promise<void> => {
    if (!conditionRepository.value || !rule.value) return;
    await Promise.all(persistedConditionIds.value.map((id) => Promise.resolve(conditionRepository.value?.delete(id))));
    let pendingConditions = conditions.value.map((condition) => cloneDeep(condition));
    if (matchMode.value === 'any') {
        pendingConditions = [
            {
                key: Contena.Utils.createId(),
                type: 'orContainer',
                value: {},
                children: pendingConditions,
            },
        ];
    }
    for (const pending of flattenConditions(pendingConditions, null)) {
        const condition = conditionRepository.value.create(Contena.Context.api, pending.id);
        condition.ruleId = rule.value.id;
        condition.parentId = pending.parentId;
        condition.type = pending.item.type;
        condition.value = normalizedValue(pending.item);
        condition.position = pending.position;
        await conditionRepository.value.save(condition);
    }
};
const onSave = async (): Promise<void> => {
    if (!ruleRepository.value || !rule.value || !canSave.value) return;
    isLoading.value = true;
    try {
        await ruleRepository.value.save(rule.value);
        await saveConditions();
        emit('process-finish', {
            id: rule.value.id,
            name: rule.value.name,
            description: rule.value.description ?? null,
        });
        onClose();
    } catch {
        createNotificationError({ message: t('ct-settings-rule.detail.saveError') });
    } finally {
        isLoading.value = false;
    }
};
const onClose = (): void => emit('modal-close');
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) onClose();
};
onMounted(() => void load());

swDefinePublic({
    rule,
    conditions,
    matchMode,
    activeTab,
    tabs,
    modalTitle,
    isLoading,
    canEdit,
    canSave,
    load,
    onSave,
    onClose,
    onModalChange,
});

defineExpose({
    rule,
    conditions,
    matchMode,
    activeTab,
    tabs,
    modalTitle,
    isLoading,
    canEdit,
    canSave,
    load,
    onSave,
    onClose,
    onModalChange,
});
</script>

<style scoped>
.ct-flow-rule-modal__content {
    min-height: 28rem;
    padding-top: var(--scale-size-24, 24px);
}

.ct-flow-rule-modal__rule {
    padding-bottom: var(--scale-size-16, 16px);
}
</style>

<template>
    <ct-block name="ct_settings_rule_detail">
        <ct-page class="ct-settings-rule-detail">
            <template #smart-bar-header>
                <ct-block name="ct_settings_rule_detail_header">
                    <h2>
                        {{
                            rule?.isNew()
                                ? $t('ct-settings-rule.detail.createTitle')
                                : rule?.name || $t('ct-settings-rule.detail.editTitle')
                        }}
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_rule_detail_actions">
                    <mt-button variant="secondary" @click="onCancel">{{ $t('global.default.cancel') }}</mt-button>
                    <ct-button-process
                        v-model:process-success="isSaveSuccessful"
                        variant="primary"
                        :is-loading="isLoading"
                        :disabled="!canSave || undefined"
                        @click="onSave"
                    >
                        {{ $t('global.default.save') }}
                    </ct-button-process>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_rule_detail_content">
                    <ct-card-view>
                        <mt-card
                            position-identifier="ct-settings-rule-detail-general"
                            :title="$t('ct-settings-rule.detail.generalCard')"
                            :is-loading="isLoading"
                        >
                            <ct-container v-if="rule" columns="2fr 1fr" gap="0 24px">
                                <mt-text-field
                                    v-model="rule.name"
                                    required
                                    :label="$t('ct-settings-rule.detail.name')"
                                    :disabled="!canEdit || undefined"
                                />
                                <mt-number-field
                                    v-model="rule.priority"
                                    number-type="int"
                                    :min="1"
                                    :label="$t('ct-settings-rule.detail.priority')"
                                    :disabled="!canEdit || undefined"
                                />
                            </ct-container>
                            <mt-textarea
                                v-if="rule"
                                v-model="rule.description"
                                :label="$t('ct-settings-rule.detail.description')"
                                :disabled="!canEdit || undefined"
                            />
                        </mt-card>

                        <mt-card
                            position-identifier="ct-settings-rule-detail-conditions"
                            :title="$t('ct-settings-rule.detail.conditionCard')"
                            :is-loading="isLoading"
                        >
                            <ct-rule-condition-editor
                                v-model:mode="matchMode"
                                v-model:conditions="conditions"
                                :disabled="!canEdit"
                            />
                        </mt-card>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
/* global Entity, EntityCollection */
import { computed, inject, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import { useNotification } from 'src/app/composables/use-notification';

import type { EditableRuleCondition } from '../../component/ct-rule-condition-editor/ct-rule-condition-editor.vue';

const { Criteria } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;
const props = defineProps({ ruleId: { type: String, default: null } });
const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const router = useRouter();
const ruleRepository = computed(() => repositoryFactory?.create('rule'));
const conditionRepository = computed(() => repositoryFactory?.create('rule_condition'));
const rule = ref<Entity<'rule'> | null>(null);
const persistedConditionIds = ref<string[]>([]);
const conditions = ref<EditableRuleCondition[]>([]);
const matchMode = ref<'all' | 'any'>('all');
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const canEdit = computed(() => Boolean(rule.value?.isNew() ? acl?.can('rule.creator') : acl?.can('rule.editor')));
const canSave = computed(() =>
    Boolean(canEdit.value && rule.value?.name?.trim() && conditions.value.length > 0 && !isLoading.value),
);

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
    matchMode.value = 'all';
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
    if (condition.type.endsWith('Container')) {
        return null;
    }

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
        const current = { id, parentId, item, position: index + 1 };

        return [
            current,
            ...flattenConditions(item.children ?? [], id),
        ];
    });
const saveConditions = async (pendingConditions: EditableRuleCondition[]): Promise<void> => {
    if (!conditionRepository.value || !rule.value) return;
    await Promise.all(persistedConditionIds.value.map((id) => Promise.resolve(conditionRepository.value?.delete(id))));

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
        const wasNew = rule.value.isNew();
        const pendingConditions = conditions.value.map((condition) => cloneDeep(condition));
        await ruleRepository.value.save(rule.value);
        await saveConditions(pendingConditions);
        isSaveSuccessful.value = true;
        createNotificationSuccess({ message: t('ct-settings-rule.detail.saveSuccess') });
        if (wasNew) {
            await router.replace({ name: 'ct.settings.rule.detail', params: { id: rule.value.id } });
        }
        await load();
    } catch {
        createNotificationError({ message: t('ct-settings-rule.detail.saveError') });
    } finally {
        isLoading.value = false;
    }
};
const onCancel = (): void => {
    void router.push({ name: 'ct.settings.rule.index' });
};
onMounted(() => {
    void load();
});

ctDefinePublic({
    acl,
    rule,
    conditions,
    matchMode,
    isLoading,
    isSaveSuccessful,
    canEdit,
    canSave,
    load,
    onSave,
    onCancel,
});

defineExpose({
    rule,
    conditions,
    matchMode,
    isLoading,
    isSaveSuccessful,
    canEdit,
    canSave,
    onSave,
    onCancel,
});
</script>

<style scoped>
.ct-settings-rule-detail :deep(.ct-card-view__content) {
    max-width: 980px;
}
</style>

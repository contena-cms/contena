<template>
    <ct-block name="sw_flow_detail">
        <ct-page class="ct-flow-detail">
            <template #smart-bar-header>
                <ct-block name="sw_flow_detail_header">
                    <h2>
                        {{ flow?.isNew() ? $t('ct-flow.detail.createTitle') : flow?.name || $t('ct-flow.detail.editTitle') }}
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_flow_detail_actions">
                    <mt-button variant="secondary" @click="onCancel">
                        {{ $t('global.default.cancel') }}
                    </mt-button>
                    <mt-button variant="primary" :is-loading="isLoading" :disabled="!canSave || undefined" @click="onSave">
                        {{ $t('global.default.save') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_flow_detail_content">
                    <div class="ct-flow-detail__card-view">
                        <ct-block name="sw_flow_detail_tabs">
                            <mt-tabs
                                class="ct-flow-detail__tabs"
                                position-identifier="ct-flow-detail"
                                :items="detailTabs"
                                :default-item="activeTab"
                                :small="true"
                                @new-item-active="onTabChange"
                            />
                        </ct-block>

                        <ct-block name="sw_flow_detail_general">
                            <template v-if="activeTab === 'general'">
                                <mt-card position-identifier="ct-flow-detail-general" :is-loading="isLoading">
                                    <div v-if="flow" class="ct-flow-detail__general-grid">
                                        <mt-text-field
                                            v-model="flow.name"
                                            required
                                            :label="$t('ct-flow.detail.name')"
                                            :disabled="!canEdit || undefined"
                                        />
                                        <mt-number-field
                                            v-model="flow.priority"
                                            number-type="int"
                                            :min="1"
                                            :label="$t('ct-flow.detail.priority')"
                                            :disabled="!canEdit || undefined"
                                        />
                                        <mt-switch
                                            v-model="flow.active"
                                            :label="$t('ct-flow.detail.active')"
                                            :disabled="!canEdit || undefined"
                                        />
                                    </div>
                                    <mt-textarea
                                        v-if="flow"
                                        v-model="flow.description"
                                        :label="$t('ct-flow.detail.description')"
                                        :disabled="!canEdit || undefined"
                                    />
                                </mt-card>
                            </template>
                        </ct-block>

                        <ct-block name="sw_flow_detail_builder">
                            <template v-if="activeTab !== 'general'">
                                <div class="ct-flow-detail__builder" position-identifier="ct-flow-detail-builder">
                                    <div class="ct-flow-detail__trigger-row">
                                        <div class="ct-flow-detail__trigger-card">
                                            <ct-flow-trigger
                                                v-if="flow"
                                                :event-name="flow.eventName"
                                                :events="triggerEvents"
                                                :has-sequences="hasConfiguredSequences"
                                                :disabled="!canEdit"
                                                @update:event-name="onEventChange"
                                            />
                                        </div>
                                        <div v-if="!flow?.eventName" class="ct-flow-detail__trigger-help">
                                            <h4>{{ $t('ct-flow.detail.triggerTitle') }}</h4>
                                            <p>{{ $t('ct-flow.detail.triggerHelp') }}</p>
                                        </div>
                                    </div>

                                    <ct-flow-sequence-editor
                                        v-if="flow?.eventName"
                                        v-model:sequences="sequences"
                                        :available-actions="availableActions"
                                        :disabled="!canEdit"
                                    />
                                </div>
                            </template>
                        </ct-block>
                    </div>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, inject, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import type BusinessEventsApiService from 'src/core/service/api/business-events.api.service';
import type FlowActionsApiService from 'src/core/service/api/flow-actions.api.service';

import { useNotification } from 'src/app/composables/use-notification';
import {
    createSelectorSequence,
    type EditableFlowAction,
    type EditableFlowSequence,
    type FlowActionOption,
    type FlowEventOption,
    type FlowRuleSummary,
} from '../../component/flow-sequence.types';

interface FlowActionDefinition {
    name: string;
    requirements?: string[];
}

interface FlowTemplateConfig {
    eventName?: string;
    description?: string | null;
    sequences?: StoredFlowSequence[];
}

interface StoredFlowSequence {
    id: string;
    parentId?: string | null;
    ruleId?: string | null;
    rule?: FlowRuleSummary | null;
    actionName?: string | null;
    config?: Record<string, unknown>;
    position?: number;
    displayGroup?: number;
    trueCase?: boolean;
}

const { Criteria } = Contena.Data;
const { cloneDeep } = Contena.Utils.object;
const props = defineProps({
    flowId: { type: String, default: null },
    flowTemplateId: { type: String, default: null },
});
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const businessEventService = inject<BusinessEventsApiService>('businessEventService');
const flowActionService = inject<FlowActionsApiService>('flowActionService');
const router = useRouter();

if (!repositoryFactory || !acl || !businessEventService || !flowActionService) {
    throw new Error('The services required by the flow editor are unavailable.');
}

const flowRepository = repositoryFactory.create('flow');
const sequenceRepository = repositoryFactory.create('flow_sequence');
const templateRepository = repositoryFactory.create('flow_template');
const flow = ref<Entity<'flow'> | null>(null);
const sequences = ref<EditableFlowSequence[]>([]);
const persistedRootSequenceIds = ref<string[]>([]);
const businessEvents = ref<FlowEventOption[]>([]);
const flowActions = ref<FlowActionDefinition[]>([]);
const activeTab = ref<'general' | 'flow'>('general');
const isLoading = ref(false);
const isSaveSuccessful = ref(false);
const canEdit = computed(() => Boolean(flow.value?.isNew() ? acl.can('flow.creator') : acl.can('flow.editor')));
const triggerEvents = computed(() => businessEvents.value);
const hasConfiguredSequences = computed(() => sequences.value.some((sequence) => sequence.type !== 'selector'));
const isComplete = (sequence: EditableFlowSequence | null): boolean => {
    if (!sequence || sequence.type === 'selector') return false;
    if (sequence.type === 'action') {
        return sequence.actions.length > 0 && sequence.actions.every((action) => Boolean(action.actionName));
    }
    return Boolean(
        sequence.ruleId &&
            (!sequence.trueChild || isComplete(sequence.trueChild)) &&
            (!sequence.falseChild || isComplete(sequence.falseChild)),
    );
};
const canSave = computed(() =>
    Boolean(
        canEdit.value &&
            flow.value?.name?.trim() &&
            flow.value?.eventName &&
            sequences.value.length > 0 &&
            sequences.value.every(isComplete) &&
            !isLoading.value,
    ),
);
const detailTabs = computed(() => [
    { label: t('ct-flow.detail.generalCard'), name: 'general' as const },
    { label: t('ct-flow.detail.builderCard'), name: 'flow' as const },
]);
const availableActions = computed<FlowActionOption[]>(() => {
    const selectedEvent = businessEvents.value.find((event) => event.name === flow.value?.eventName);
    const aware = selectedEvent?.aware ?? [];

    return flowActions.value
        .filter((action) => (action.requirements ?? []).every((requirement) => aware.includes(requirement)))
        .map((action) => ({
            value: action.name,
            label:
                action.name === 'action.mail.send'
                    ? t('ct-flow.sequence.sendMail')
                    : action.name === 'action.notification.create'
                      ? t('ct-flow.sequence.createNotification')
                      : action.name === 'action.user.status.assign'
                        ? t('ct-flow.sequence.assignUserStatus')
                        : action.name === 'action.user.tag.add'
                          ? t('ct-flow.sequence.addTag')
                          : action.name === 'action.user.tag.remove'
                            ? t('ct-flow.sequence.removeTag')
                            : action.name === 'action.user.custom.field.set'
                              ? t('ct-flow.sequence.setUserCustomField')
                              : action.name === 'action.stop.flow'
                                ? t('ct-flow.sequence.stopFlow')
                                : action.name,
            icon:
                action.name === 'action.mail.send'
                    ? 'regular-envelope'
                    : action.name === 'action.notification.create'
                      ? 'regular-bell'
                      : action.name === 'action.user.status.assign'
                        ? 'regular-user'
                        : action.name === 'action.user.tag.add' || action.name === 'action.user.tag.remove'
                          ? 'regular-tag'
                          : action.name === 'action.user.custom.field.set'
                            ? 'regular-variables-xs'
                            : action.name === 'action.stop.flow'
                              ? 'regular-times-circle'
                              : 'regular-file-edit-s',
            group:
                action.name === 'action.user.tag.add' || action.name === 'action.user.tag.remove'
                    ? 'tags'
                    : action.name === 'action.user.custom.field.set'
                      ? 'data'
                      : 'general',
        }))
        .sort((left, right) => {
            const groupOrder = { general: 0, tags: 1, data: 2 };
            const actionOrder = [
                'action.user.status.assign',
                'action.notification.create',
                'action.mail.send',
                'action.stop.flow',
                'action.user.tag.add',
                'action.user.tag.remove',
                'action.user.custom.field.set',
            ];
            return (
                (groupOrder[left.group as keyof typeof groupOrder] ?? 99) -
                    (groupOrder[right.group as keyof typeof groupOrder] ?? 99) ||
                actionOrder.indexOf(left.value) - actionOrder.indexOf(right.value)
            );
        });
});

function normalizeCollection<T>(response: unknown): T[] {
    if (Array.isArray(response)) return response as T[];
    if (response !== null && typeof response === 'object') return Object.values(response) as T[];
    return [];
}

function sortedBlock(stored: StoredFlowSequence[], parentId: string | null, trueCase?: boolean): StoredFlowSequence[] {
    return stored
        .filter(
            (sequence) =>
                (sequence.parentId ?? null) === parentId &&
                (trueCase === undefined || Boolean(sequence.trueCase) === trueCase),
        )
        .sort((left, right) => (left.position ?? 0) - (right.position ?? 0));
}

function hydrateBlock(
    stored: StoredFlowSequence[],
    parentId: string | null,
    trueCase: boolean | undefined,
    isRoot: boolean,
): EditableFlowSequence | null {
    const block = sortedBlock(stored, parentId, trueCase);
    if (block.length === 0) return isRoot ? createSelectorSequence() : null;

    const condition = block.find((sequence) => sequence.ruleId !== null && sequence.ruleId !== undefined);
    if (condition) {
        return {
            key: condition.id,
            type: 'condition',
            ruleId: condition.ruleId ?? null,
            rule: condition.rule ? cloneDeep(condition.rule) : null,
            actions: [],
            trueChild: hydrateBlock(stored, condition.id, true, isRoot),
            falseChild: hydrateBlock(stored, condition.id, false, isRoot),
        };
    }

    const storedActions = block.filter((sequence) => sequence.actionName !== null && sequence.actionName !== undefined);
    if (storedActions.length > 0) {
        const actions: EditableFlowAction[] = storedActions.map((action) => ({
            key: action.id,
            actionName: action.actionName ?? null,
            config: cloneDeep(action.config ?? {}),
        }));
        return {
            key: actions[0].key,
            type: 'action',
            ruleId: null,
            rule: null,
            actions,
            trueChild: null,
            falseChild: null,
        };
    }

    return createSelectorSequence();
}

function hydrateSequences(stored: StoredFlowSequence[]): void {
    persistedRootSequenceIds.value = stored.filter((sequence) => !sequence.parentId).map((sequence) => sequence.id);
    const groups: Record<number, StoredFlowSequence[]> = {};
    stored.forEach((sequence) => {
        const displayGroup = sequence.displayGroup ?? 1;
        groups[displayGroup] = [
            ...(groups[displayGroup] ?? []),
            sequence,
        ];
    });
    sequences.value = Object.entries(groups)
        .sort(([left], [right]) => Number(left) - Number(right))
        .map(
            ([
                ,
                group,
            ]) => hydrateBlock(group, null, undefined, true) ?? createSelectorSequence(),
        );
    if (sequences.value.length === 0 && flow.value?.eventName) sequences.value = [createSelectorSequence()];
}

async function loadMetadata(): Promise<void> {
    const [
        eventsResponse,
        actionsResponse,
    ] = await Promise.all([
        businessEventService.getBusinessEvents(),
        flowActionService.getFlowActions(),
    ]);
    businessEvents.value = normalizeCollection<FlowEventOption>(eventsResponse);
    flowActions.value = normalizeCollection<FlowActionDefinition>(actionsResponse);
}

async function loadTemplate(): Promise<void> {
    if (!props.flowTemplateId) return;
    const template = await templateRepository.get(props.flowTemplateId, Contena.Context.api);
    const config = (template.config ?? {}) as FlowTemplateConfig;
    if (!flow.value) return;
    flow.value.name = template.name;
    flow.value.description = config.description ?? '';
    flow.value.eventName = config.eventName ?? '';
    hydrateSequences(config.sequences ?? []);
    persistedRootSequenceIds.value = [];
}

async function load(): Promise<void> {
    isLoading.value = true;
    try {
        await loadMetadata();
        if (!props.flowId) {
            const created = flowRepository.create();
            created.priority = 1;
            created.active = false;
            flow.value = created;
            sequences.value = [];
            persistedRootSequenceIds.value = [];
            await loadTemplate();
            return;
        }

        const criteria = new Criteria(1, 1);
        criteria.addAssociation('sequences');
        criteria.addAssociation('sequences.rule');
        flow.value = await flowRepository.get(props.flowId, Contena.Context.api, criteria);
        hydrateSequences(
            [...(flow.value.sequences ?? [])].map((sequence) => ({
                id: sequence.id,
                parentId: sequence.parentId,
                ruleId: sequence.ruleId,
                rule: sequence.rule
                    ? {
                          id: sequence.rule.id,
                          name: sequence.rule.name,
                          description: sequence.rule.description ?? null,
                      }
                    : null,
                actionName: sequence.actionName,
                config: sequence.config as Record<string, unknown>,
                position: sequence.position,
                displayGroup: sequence.displayGroup,
                trueCase: sequence.trueCase,
            })),
        );
    } finally {
        isLoading.value = false;
    }
}

async function saveBlock(
    item: EditableFlowSequence | null,
    parentId: string | null,
    trueCase: boolean,
    displayGroup: number,
): Promise<void> {
    if (!item || item.type === 'selector') return;

    if (item.type === 'action') {
        for (let index = 0; index < item.actions.length; index += 1) {
            const action = item.actions[index];
            if (!action.actionName) continue;
            const sequence = sequenceRepository.create(Contena.Context.api, action.key);
            sequence.flowId = flow.value?.id;
            sequence.parentId = parentId;
            sequence.ruleId = null;
            sequence.actionName = action.actionName;
            sequence.config = cloneDeep(action.config);
            sequence.position = index + 1;
            sequence.displayGroup = displayGroup;
            sequence.trueCase = trueCase;
            await sequenceRepository.save(sequence);
        }
        return;
    }

    const sequence = sequenceRepository.create(Contena.Context.api, item.key);
    sequence.flowId = flow.value?.id;
    sequence.parentId = parentId;
    sequence.ruleId = item.ruleId;
    sequence.actionName = null;
    sequence.config = {};
    sequence.position = 1;
    sequence.displayGroup = displayGroup;
    sequence.trueCase = trueCase;
    await sequenceRepository.save(sequence);
    await saveBlock(item.trueChild, sequence.id, true, displayGroup);
    await saveBlock(item.falseChild, sequence.id, false, displayGroup);
}

async function saveSequences(): Promise<void> {
    const pendingSequences = cloneDeep(sequences.value);
    if (persistedRootSequenceIds.value.length > 0) {
        await sequenceRepository.syncDeleted(persistedRootSequenceIds.value, Contena.Context.api);
    }
    for (let index = 0; index < pendingSequences.length; index += 1) {
        await saveBlock(pendingSequences[index], null, false, index + 1);
    }
}

async function onSave(): Promise<void> {
    if (!flow.value || !canSave.value) return;
    isLoading.value = true;
    try {
        const wasNew = flow.value.isNew();
        await flowRepository.save(flow.value);
        await saveSequences();
        isSaveSuccessful.value = true;
        createNotificationSuccess({ message: t('ct-flow.detail.saveSuccess') });
        if (wasNew) await router.replace({ name: 'ct.flow.detail', params: { id: flow.value.id } });
        await load();
    } catch (error) {
        createNotificationError({
            message: error instanceof Error ? error.message : t('ct-flow.detail.saveError'),
        });
    } finally {
        isLoading.value = false;
    }
}

function onEventChange(eventName: string): void {
    if (!flow.value || flow.value.eventName === eventName) return;
    flow.value.eventName = eventName;
    sequences.value = [createSelectorSequence()];
}

function onCancel(): void {
    void router.push({ name: 'ct.flow.index' });
}

function onTabChange(tab: string): void {
    if (tab === 'general' || tab === 'flow') activeTab.value = tab;
}

onMounted(load);

swDefinePublic({
    flow,
    sequences,
    triggerEvents,
    availableActions,
    activeTab,
    detailTabs,
    isLoading,
    isSaveSuccessful,
    canEdit,
    canSave,
    hasConfiguredSequences,
    load,
    onEventChange,
    onTabChange,
    onSave,
    onCancel,
});

defineExpose({
    flow,
    sequences,
    triggerEvents,
    availableActions,
    activeTab,
    detailTabs,
    isLoading,
    isSaveSuccessful,
    canEdit,
    canSave,
    hasConfiguredSequences,
    load,
    onEventChange,
    onTabChange,
    onSave,
    onCancel,
});
</script>

<style scoped>
.ct-flow-detail__card-view {
    width: 100%;
    max-width: 60rem;
    margin: 0 auto;
}

.ct-flow-detail__general-grid {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(140px, 1fr) minmax(140px, 1fr);
    gap: 0 var(--scale-size-24, 24px);
    align-items: end;
}

.ct-flow-detail__tabs {
    margin-bottom: var(--scale-size-20, 20px);
}

.ct-flow-detail__builder {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    min-height: 32rem;
    padding: var(--scale-size-24, 24px) 0 var(--scale-size-64, 64px);
    overflow-x: auto;
}

.ct-flow-detail__trigger-row {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: var(--scale-size-48, 48px);
    align-items: start;
}

.ct-flow-detail__trigger-card {
    min-width: 20.5rem;
    padding: var(--scale-size-24, 24px);
    border: 1px solid var(--color-border-secondary-default, #d8dde6);
    border-radius: var(--border-radius-card, 8px);
    background: var(--color-elevation-surface-raised, #fff);
    box-shadow: 0 3px 6px 0 var(--color-elevation-shadow-default, rgb(0 0 0 / 10%));
}

.ct-flow-detail__trigger-help {
    width: 26.25rem;
    padding: var(--scale-size-24, 24px);
    border: 2px dashed var(--color-border-secondary-default, #d8dde6);
    border-radius: var(--border-radius-card, 8px);
    color: var(--color-text-primary-default, #2b3137);
}

.ct-flow-detail__trigger-help h4 {
    margin: 0 0 var(--scale-size-4, 4px);
    font-size: var(--font-size-s, 16px);
    font-weight: var(--font-weight-medium, 500);
}

.ct-flow-detail__trigger-help p {
    margin: 0;
    font-size: var(--font-size-xs, 14px);
    line-height: var(--font-line-height-xs, 20px);
}

@media (max-width: 960px) {
    .ct-flow-detail__general-grid {
        grid-template-columns: 1fr 1fr;
    }

    .ct-flow-detail__trigger-row {
        grid-template-columns: 1fr;
    }
}
</style>

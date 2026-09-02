<template>
    <ct-block name="ct_flow_sequence_action">
        <div class="ct-flow-sequence-action">
            <ct-block name="ct_flow_sequence_action_card">
                <div class="ct-flow-sequence-action__card">
                    <ct-block name="ct_flow_sequence_action_header">
                        <div class="ct-flow-sequence-action__header">
                            <div class="ct-flow-sequence-action__title-description">
                                <h3 class="ct-flow-sequence-action__title">{{ $t('ct-flow.sequence.action') }}</h3>
                                <p class="ct-flow-sequence-action__description">({{ $t('ct-flow.sequence.then') }})</p>
                            </div>

                            <mt-dropdown-menu-root>
                                <mt-dropdown-menu-trigger as-child>
                                    <mt-button
                                        class="ct-flow-sequence-action__context-button"
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
                                        <mt-action-menu-item variant="critical" @select="removeContainer">
                                            {{ $t('ct-flow.sequence.deleteActionContainer') }}
                                        </mt-action-menu-item>
                                    </mt-action-menu>
                                </mt-dropdown-menu-portal>
                            </mt-dropdown-menu-root>
                        </div>
                    </ct-block>

                    <ct-block name="ct_flow_sequence_action_content">
                        <div class="ct-flow-sequence-action__content">
                            <div v-if="configuredActions.length === 0" class="ct-flow-sequence-action__actions-empty">
                                <span class="ct-flow-sequence-action__no-action">
                                    {{ $t('ct-flow.sequence.noActions') }}
                                </span>
                            </div>

                            <transition-group v-else name="list" tag="ul" class="ct-flow-sequence-action__action-list">
                                <li
                                    v-for="(action, index) in configuredActions"
                                    :key="action.key"
                                    class="ct-flow-sequence-action__action-item"
                                    role="button"
                                    tabindex="0"
                                    @click="openEdit(index)"
                                    @keydown.enter="openEdit(index)"
                                >
                                    <div class="ct-flow-sequence-action__action-header">
                                        <div class="ct-flow-sequence-action__action-icon">
                                            <mt-icon :name="getActionIcon(action.actionName)" size="12px" />
                                        </div>
                                        <div class="ct-flow-sequence-action__action-name">
                                            <h3>{{ getActionLabel(action.actionName) }}</h3>
                                        </div>

                                        <div @click.stop @keydown.stop>
                                            <mt-dropdown-menu-root>
                                                <mt-dropdown-menu-trigger as-child>
                                                    <mt-button
                                                        class="ct-flow-sequence-action__context-button"
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
                                                        <mt-action-menu-item
                                                            v-if="action.actionName !== 'action.stop.flow'"
                                                            @select="openEdit(index)"
                                                        >
                                                            {{ $t('ct-flow.sequence.editAction') }}
                                                        </mt-action-menu-item>
                                                        <mt-action-menu-item
                                                            variant="critical"
                                                            @select="removeAction(index)"
                                                        >
                                                            {{ $t('ct-flow.sequence.deleteAction') }}
                                                        </mt-action-menu-item>
                                                        <mt-action-menu-item
                                                            v-if="canMoveAction(index, -1)"
                                                            @select="moveAction(index, -1)"
                                                        >
                                                            {{ $t('ct-flow.sequence.moveUp') }}
                                                        </mt-action-menu-item>
                                                        <mt-action-menu-item
                                                            v-if="canMoveAction(index, 1)"
                                                            @select="moveAction(index, 1)"
                                                        >
                                                            {{ $t('ct-flow.sequence.moveDown') }}
                                                        </mt-action-menu-item>
                                                    </mt-action-menu>
                                                </mt-dropdown-menu-portal>
                                            </mt-dropdown-menu-root>
                                        </div>
                                    </div>
                                    <div
                                        v-if="getActionDescription(action)"
                                        class="ct-flow-sequence-action__action-description"
                                    >
                                        <template v-for="line in getActionDescription(action)" :key="line">
                                            <span>{{ line }}</span>
                                        </template>
                                    </div>
                                </li>
                            </transition-group>

                            <div v-if="showAddAction && !disabled" class="ct-flow-sequence-action__select">
                                <mt-select
                                    class="ct-flow-sequence-action__selection-action"
                                    :model-value="null"
                                    :placeholder="$t('ct-flow.sequence.actionPlaceholder')"
                                    :options="availableActions"
                                    @item-add="selectActionOption"
                                >
                                    <template #result-item="{ item, index, addItem }">
                                        <div
                                            v-if="index === 0 || availableActions[index - 1]?.group !== item.group"
                                            class="ct-flow-sequence-action__group-label"
                                        >
                                            {{ getActionGroupLabel(item.group) }}
                                        </div>
                                        <mt-button
                                            class="ct-flow-sequence-action__result-item"
                                            variant="tertiary"
                                            @click="addItem(item)"
                                        >
                                            <mt-icon :name="item.icon" size="16px" />
                                            <span>{{ item.label }}</span>
                                        </mt-button>
                                    </template>
                                </mt-select>
                            </div>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-flow-mail-send-modal
                v-if="selectedActionName === 'action.mail.send'"
                :config="modalConfig"
                @save="saveMailAction"
                @cancel="closeModal"
            />
            <ct-flow-notification-modal
                v-if="selectedActionName === 'action.notification.create'"
                :config="modalConfig"
                @save="saveNotificationAction"
                @cancel="closeModal"
            />
            <ct-flow-user-status-modal
                v-if="selectedActionName === 'action.user.status.assign'"
                :config="modalConfig"
                @save="saveSelectedAction"
                @cancel="closeModal"
            />
            <ct-flow-tag-modal
                v-if="selectedActionName === 'action.user.tag.add' || selectedActionName === 'action.user.tag.remove'"
                :action-name="selectedActionName"
                :config="modalConfig"
                @save="saveSelectedAction"
                @cancel="closeModal"
            />
            <ct-flow-user-custom-field-modal
                v-if="selectedActionName === 'action.user.custom.field.set'"
                :config="modalConfig"
                @save="saveSelectedAction"
                @cancel="closeModal"
            />
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, inject, ref, watch, type PropType } from 'vue';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import type { EditableFlowAction, EditableFlowSequence, FlowActionOption } from '../flow-sequence.types';

interface MailTemplateSummary {
    name: string;
    description: string | null;
}

const { cloneDeep } = Contena.Utils.object;
const { Criteria } = Contena.Data;
const props = defineProps({
    sequence: { type: Object as PropType<EditableFlowSequence>, required: true },
    availableActions: { type: Array as PropType<FlowActionOption[]>, required: true },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits<{ 'update:sequence': [sequence: EditableFlowSequence]; remove: [] }>();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory', undefined);
const mailTemplateRepository = repositoryFactory?.create('mail_template');
const selectedActionName = ref<string | null>(null);
const editingActionIndex = ref<number | null>(null);
const modalConfig = ref<Record<string, unknown>>({});
const mailTemplates = ref<Record<string, MailTemplateSummary>>({});
const configuredActions = computed(() => props.sequence.actions.filter((action) => action.actionName));
const showAddAction = computed(() => !props.sequence.actions.some((action) => action.actionName === 'action.stop.flow'));

const emitActions = (actions: EditableFlowAction[]): void => {
    emit('update:sequence', { ...cloneDeep(props.sequence), actions });
};
const replaceOrAppendAction = (action: EditableFlowAction): void => {
    const actions = cloneDeep(props.sequence.actions);
    const emptyIndex = actions.findIndex((item) => !item.actionName);
    if (editingActionIndex.value !== null) actions[editingActionIndex.value] = action;
    else if (emptyIndex >= 0) actions[emptyIndex] = action;
    else actions.push(action);
    emitActions(actions);
};
const selectAction = (actionName: string | null): void => {
    if (!actionName) return;
    const configuredActions = [
        'action.mail.send',
        'action.notification.create',
        'action.user.status.assign',
        'action.user.tag.add',
        'action.user.tag.remove',
        'action.user.custom.field.set',
    ];
    if (configuredActions.includes(actionName)) {
        editingActionIndex.value = null;
        selectedActionName.value = actionName;
        const defaults: Record<string, Record<string, unknown>> = {
            'action.mail.send': { recipient: { type: 'default', data: [] }, mailTemplateId: null },
            'action.notification.create': { status: 'info', message: '', adminOnly: false, requiredPrivileges: [] },
            'action.user.status.assign': { active: true },
            'action.user.tag.add': { tagIds: [] },
            'action.user.tag.remove': { tagIds: [] },
            'action.user.custom.field.set': { customFieldId: null, customFieldValue: null, option: 'upsert' },
        };
        modalConfig.value = defaults[actionName] ?? {};
        return;
    }
    replaceOrAppendAction({ key: Contena.Utils.createId(), actionName, config: {} });
};
const selectActionOption = (action: FlowActionOption): void => selectAction(action.value);
const openEdit = (index: number): void => {
    const action = configuredActions.value[index];
    if (!action || action.actionName === 'action.stop.flow') return;
    editingActionIndex.value = props.sequence.actions.findIndex((item) => item.key === action.key);
    selectedActionName.value = action.actionName;
    modalConfig.value = cloneDeep(action.config);
};
const closeModal = (): void => {
    selectedActionName.value = null;
    editingActionIndex.value = null;
    modalConfig.value = {};
};
const saveConfiguredAction = (actionName: string, config: Record<string, unknown>): void => {
    const existingKey =
        editingActionIndex.value === null
            ? Contena.Utils.createId()
            : (props.sequence.actions[editingActionIndex.value]?.key ?? Contena.Utils.createId());
    replaceOrAppendAction({ key: existingKey, actionName, config: cloneDeep(config) });
    closeModal();
};
const saveMailAction = (config: Record<string, unknown>): void => saveConfiguredAction('action.mail.send', config);
const saveNotificationAction = (config: Record<string, unknown>): void =>
    saveConfiguredAction('action.notification.create', config);
const saveSelectedAction = (config: Record<string, unknown>): void => {
    if (selectedActionName.value) saveConfiguredAction(selectedActionName.value, config);
};
const removeContainer = (): void => emit('remove');
const removeAction = (configuredIndex: number): void => {
    const action = configuredActions.value[configuredIndex];
    if (!action) return;
    const actions = props.sequence.actions.filter((item) => item.key !== action.key);
    if (actions.length === 0) {
        emit('remove');
        return;
    }
    emitActions(cloneDeep(actions));
};
const canMoveAction = (configuredIndex: number, offset: -1 | 1): boolean => {
    const action = configuredActions.value[configuredIndex];
    const target = configuredActions.value[configuredIndex + offset];

    if (!action || !target) return false;
    if (action.actionName === 'action.stop.flow' || target.actionName === 'action.stop.flow') return false;

    return true;
};
const moveAction = (configuredIndex: number, offset: -1 | 1): void => {
    const actions = cloneDeep(configuredActions.value);
    const target = configuredIndex + offset;
    if (!canMoveAction(configuredIndex, offset)) return;
    [
        actions[configuredIndex],
        actions[target],
    ] = [
        actions[target],
        actions[configuredIndex],
    ];
    emitActions(actions);
};
const getOption = (actionName: string | null): FlowActionOption | undefined =>
    props.availableActions.find((option) => option.value === actionName);
const getActionLabel = (actionName: string | null): string => getOption(actionName)?.label ?? actionName ?? '';
const getActionIcon = (actionName: string | null): string => getOption(actionName)?.icon ?? 'regular-question-circle-s';
const getActionGroupLabel = (group: string): string => Contena.Snippet.tc(`ct-flow.sequence.actionGroup.${group}`);
const getActionDescription = (action: EditableFlowAction): string[] => {
    if (action.actionName === 'action.notification.create') {
        const message = action.config.message;
        return typeof message === 'string' && message.trim()
            ? [Contena.Snippet.tc('ct-flow.sequence.notificationMessageLabel', { message })]
            : [Contena.Snippet.tc('ct-flow.sequence.notificationMessageMissing')];
    }
    if (action.actionName === 'action.user.status.assign') {
        return [
            Contena.Snippet.tc(
                action.config.active === false ? 'ct-flow.sequence.userStatusInactive' : 'ct-flow.sequence.userStatusActive',
            ),
        ];
    }
    if (action.actionName === 'action.user.tag.add' || action.actionName === 'action.user.tag.remove') {
        return [Contena.Snippet.tc('ct-flow.sequence.tagConfigured')];
    }
    if (action.actionName === 'action.user.custom.field.set') {
        return [Contena.Snippet.tc('ct-flow.sequence.customFieldConfigured')];
    }
    if (action.actionName !== 'action.mail.send') return [];
    const templateId = action.config.mailTemplateId;
    if (typeof templateId !== 'string') return [Contena.Snippet.tc('ct-flow.sequence.mailTemplateMissing')];
    const template = mailTemplates.value[templateId];
    if (!template) return [];
    return [
        Contena.Snippet.tc('ct-flow.sequence.mailTemplateLabel', { template: template.name }),
        ...(template.description
            ? [Contena.Snippet.tc('ct-flow.sequence.mailDescriptionLabel', { description: template.description })]
            : []),
    ];
};
const loadMailTemplates = async (): Promise<void> => {
    if (!mailTemplateRepository) return;
    const templateIds = props.sequence.actions
        .map((action) => action.config.mailTemplateId)
        .filter((id): id is string => typeof id === 'string' && !mailTemplates.value[id]);
    await Promise.all(
        templateIds.map(async (id) => {
            try {
                const criteria = new Criteria(1, 1);
                criteria.addAssociation('mailTemplateType');
                const template = (await mailTemplateRepository.get(
                    id,
                    Contena.Context.api,
                    criteria,
                )) as Entity<'mail_template'>;
                mailTemplates.value = {
                    ...mailTemplates.value,
                    [id]: {
                        name: template.mailTemplateType?.name ?? template.subject ?? id,
                        description: template.description ?? null,
                    },
                };
            } catch {
                mailTemplates.value = {
                    ...mailTemplates.value,
                    [id]: { name: id, description: null },
                };
            }
        }),
    );
};

watch(() => props.sequence.actions, loadMailTemplates, { deep: true, immediate: true });

ctDefinePublic({
    configuredActions,
    showAddAction,
    selectedActionName,
    modalConfig,
    getActionLabel,
    getActionIcon,
    getActionDescription,
    getActionGroupLabel,
    canMoveAction,
    selectAction,
    selectActionOption,
    openEdit,
    closeModal,
    saveMailAction,
    saveNotificationAction,
    saveSelectedAction,
    removeContainer,
    removeAction,
    moveAction,
});

defineExpose({
    configuredActions,
    showAddAction,
    selectedActionName,
    modalConfig,
    getActionLabel,
    getActionIcon,
    getActionDescription,
    getActionGroupLabel,
    canMoveAction,
    selectAction,
    selectActionOption,
    openEdit,
    closeModal,
    saveMailAction,
    saveNotificationAction,
    saveSelectedAction,
    removeContainer,
    removeAction,
    moveAction,
});
</script>

<style scoped>
.ct-flow-sequence-action__card {
    width: 20.5rem;
    margin-right: var(--scale-size-32, 32px);
    overflow: hidden;
    border: 1px solid var(--color-border-secondary-default, #d8dde6);
    border-radius: var(--border-radius-card, 8px);
    background: var(--color-elevation-surface-raised, #fff);
    box-shadow: 0 3px 6px 0 var(--color-elevation-shadow-default, rgb(0 0 0 / 10%));
}

.ct-flow-sequence-action__header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: var(--scale-size-16, 16px) var(--scale-size-12, 12px) var(--scale-size-16, 16px) var(--scale-size-20, 20px);
    border-bottom: 1px solid var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-action__title-description {
    display: flex;
    gap: var(--scale-size-4, 4px);
}

.ct-flow-sequence-action__title,
.ct-flow-sequence-action__description {
    margin: 0;
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
    font-weight: var(--font-weight-semibold, 600);
}

.ct-flow-sequence-action__context-button {
    min-width: var(--scale-size-24, 24px);
}

.ct-flow-sequence-action__actions-empty {
    display: flex;
    align-items: center;
    padding: var(--scale-size-16, 16px) var(--scale-size-20, 20px);
}

.ct-flow-sequence-action__no-action {
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
}

.ct-flow-sequence-action__action-list {
    margin: 0;
    padding: 0;
    list-style: none;
}

.ct-flow-sequence-action__action-item {
    padding: var(--scale-size-12, 12px) var(--scale-size-12, 12px) var(--scale-size-16, 16px) var(--scale-size-20, 20px);
    cursor: pointer;
    transition: background 100ms ease-in-out;
}

.ct-flow-sequence-action__action-item:not(:last-child) {
    border-bottom: 1px solid var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-action__action-item:hover {
    background: var(--color-elevation-surface-sunken, #f0f2f5);
}

.ct-flow-sequence-action__action-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ct-flow-sequence-action__action-icon {
    display: flex;
    margin-right: var(--scale-size-6, 6px);
    color: var(--color-icon-primary-default, #52667a);
}

.ct-flow-sequence-action__action-name {
    flex-basis: 100%;
    overflow: hidden;
    overflow-wrap: anywhere;
}

.ct-flow-sequence-action__action-name h3 {
    margin: 0;
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
    font-weight: var(--font-weight-medium, 500);
}

.ct-flow-sequence-action__action-description {
    display: flex;
    flex-direction: column;
    margin-top: var(--scale-size-6, 6px);
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
    line-height: var(--font-line-height-xs, 20px);
}

.ct-flow-sequence-action__select {
    display: flex;
    align-items: center;
    width: 100%;
    padding: var(--scale-size-16, 16px) var(--scale-size-20, 20px);
    border-top: 1px solid var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-action__select :deep(.mt-field),
.ct-flow-sequence-action__select :deep(.mt-select) {
    width: 100%;
    margin-bottom: 0;
}

.ct-flow-sequence-action__group-label {
    padding: var(--scale-size-12, 12px) var(--scale-size-16, 16px) var(--scale-size-4, 4px);
    color: var(--color-text-secondary-default, #52667a);
    font-size: var(--font-size-2xs, 12px);
    font-weight: var(--font-weight-semibold, 600);
}

.ct-flow-sequence-action__result-item {
    width: 100%;
    margin: 0 var(--scale-size-4, 4px);
    padding: var(--scale-size-4, 4px) var(--scale-size-16, 16px);
    justify-content: flex-start;
    gap: var(--scale-size-10, 10px);
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
}

.list-enter-active,
.list-leave-active {
    transition: all 0.5s ease-in-out;
}

.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(var(--scale-size-30, 30px));
}
</style>

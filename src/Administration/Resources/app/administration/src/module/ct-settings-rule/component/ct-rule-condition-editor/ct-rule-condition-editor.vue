<template>
    <ct-block name="sw_rule_condition_editor">
        <div class="ct-rule-condition-editor" :class="{ 'is--nested': nested }">
            <div class="ct-rule-condition-editor__container">
                <div class="ct-rule-condition-editor__toolbar">
                    <mt-select
                        :model-value="mode"
                        :label="$t('ct-settings-rule.condition.matchMode')"
                        :options="modeOptions"
                        :disabled="disabled || undefined"
                        @update:model-value="updateMode"
                    />
                </div>

                <div v-if="conditions.length === 0" class="ct-rule-condition-editor__empty">
                    {{ $t('ct-settings-rule.condition.empty') }}
                </div>

                <template v-for="(condition, index) in conditions" :key="condition.key">
                    <div v-if="index > 0" class="ct-rule-condition-editor__spacer">
                        {{ mode === 'all' ? $t('ct-settings-rule.condition.and') : $t('ct-settings-rule.condition.or') }}
                    </div>

                    <div v-if="isGroup(condition)" class="ct-rule-condition-editor__group">
                        <ct-rule-condition-editor
                            :conditions="condition.children ?? []"
                            :mode="groupMode(condition)"
                            nested
                            :disabled="disabled"
                            @update:mode="(value) => updateGroupMode(index, value)"
                            @update:conditions="(value) => updateGroup(index, value)"
                        />
                        <mt-button
                            square
                            size="small"
                            variant="secondary"
                            :title="$t('ct-settings-rule.condition.removeGroup')"
                            :disabled="disabled || undefined"
                            @click="removeCondition(index)"
                        >
                            <mt-icon name="regular-trash" size="14px" />
                        </mt-button>
                    </div>

                    <article v-else class="ct-rule-condition-editor__condition">
                        <mt-select
                            v-model="condition.type"
                            class="ct-rule-condition-editor__type"
                            :label="$t('ct-settings-rule.condition.type')"
                            :options="conditionTypes"
                            :disabled="disabled || undefined"
                            @update:model-value="resetValue(condition)"
                        />

                        <div class="ct-rule-condition-editor__fields">
                            <template v-if="condition.type === 'dateRange'">
                                <mt-text-field
                                    v-model="condition.value.fromDate"
                                    type="datetime-local"
                                    :label="$t('ct-settings-rule.condition.fromDate')"
                                    :disabled="disabled || undefined"
                                />
                                <mt-text-field
                                    v-model="condition.value.toDate"
                                    type="datetime-local"
                                    :label="$t('ct-settings-rule.condition.toDate')"
                                    :disabled="disabled || undefined"
                                />
                            </template>
                            <template v-else-if="condition.type === 'timeRange'">
                                <mt-text-field
                                    v-model="condition.value.fromTime"
                                    type="time"
                                    :label="$t('ct-settings-rule.condition.fromTime')"
                                    :disabled="disabled || undefined"
                                />
                                <mt-text-field
                                    v-model="condition.value.toTime"
                                    type="time"
                                    :label="$t('ct-settings-rule.condition.toTime')"
                                    :disabled="disabled || undefined"
                                />
                            </template>
                            <template v-else-if="condition.type === 'dayOfWeek'">
                                <mt-select
                                    v-model="condition.value.dayOfWeek"
                                    :label="$t('ct-settings-rule.condition.weekday')"
                                    :options="weekdayOptions"
                                    :disabled="disabled || undefined"
                                />
                            </template>
                            <template v-else-if="condition.type === 'language'">
                                <ct-entity-single-select
                                    :value="condition.value.languageIds?.[0] ?? null"
                                    entity="language"
                                    :label="$t('ct-settings-rule.condition.languageLabel')"
                                    :disabled="disabled || undefined"
                                    @update:value="(value) => setLanguage(condition, value)"
                                />
                            </template>
                            <template
                                v-else-if="
                                    condition.type === 'userDaysSinceFirstLogin' ||
                                    condition.type === 'userDaysSinceLastLogin'
                                "
                            >
                                <mt-select
                                    v-model="condition.value.operator"
                                    :label="$t('ct-settings-rule.condition.operator')"
                                    :options="numericOperatorOptions"
                                    :disabled="disabled || undefined"
                                />
                                <mt-number-field
                                    v-model="condition.value.daysPassed"
                                    number-type="float"
                                    :min="0"
                                    :label="$t('ct-settings-rule.condition.daysPassed')"
                                    :disabled="disabled || undefined"
                                />
                            </template>
                        </div>
                        <mt-button
                            square
                            size="small"
                            variant="secondary"
                            :title="$t('ct-settings-rule.condition.remove')"
                            :disabled="disabled || undefined"
                            @click="removeCondition(index)"
                        >
                            <mt-icon name="regular-trash" size="14px" />
                        </mt-button>
                    </article>
                </template>

                <div class="ct-rule-condition-editor__actions">
                    <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="addCondition">
                        {{ $t('ct-settings-rule.condition.add') }}
                    </mt-button>
                    <mt-button size="small" variant="secondary" :disabled="disabled || undefined" @click="addGroup">
                        {{ $t('ct-settings-rule.condition.addGroup') }}
                    </mt-button>
                    <mt-button
                        v-if="conditions.length > 0"
                        class="ct-rule-condition-editor__delete-all"
                        size="small"
                        variant="secondary"
                        :disabled="disabled || undefined"
                        @click="removeAll"
                    >
                        {{ $t('ct-settings-rule.condition.removeAll') }}
                    </mt-button>
                </div>
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';

/** @private */
export interface EditableRuleCondition {
    key: string;
    type:
        | 'dateRange'
        | 'timeRange'
        | 'dayOfWeek'
        | 'language'
        | 'userDaysSinceFirstLogin'
        | 'userDaysSinceLastLogin'
        | 'andContainer'
        | 'orContainer';
    value: Record<string, unknown>;
    children?: EditableRuleCondition[];
}

const props = defineProps({
    conditions: { type: Array as PropType<EditableRuleCondition[]>, required: true },
    mode: { type: String as PropType<'all' | 'any'>, required: true },
    nested: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits<{
    'update:mode': [value: 'all' | 'any'];
    'update:conditions': [value: EditableRuleCondition[]];
}>();
const { t } = useI18n();

const conditionTypes = computed(() => [
    { label: t('ct-settings-rule.condition.dateRange'), value: 'dateRange' },
    { label: t('ct-settings-rule.condition.timeRange'), value: 'timeRange' },
    { label: t('ct-settings-rule.condition.dayOfWeek'), value: 'dayOfWeek' },
    { label: t('ct-settings-rule.condition.language'), value: 'language' },
    {
        label: t('ct-settings-rule.condition.userDaysSinceFirstLogin'),
        value: 'userDaysSinceFirstLogin',
    },
    {
        label: t('ct-settings-rule.condition.userDaysSinceLastLogin'),
        value: 'userDaysSinceLastLogin',
    },
]);
const modeOptions = computed(() => [
    { label: t('ct-settings-rule.condition.matchAll'), value: 'all' },
    { label: t('ct-settings-rule.condition.matchAny'), value: 'any' },
]);
const weekdayOptions = computed(() =>
    [
        1,
        2,
        3,
        4,
        5,
        6,
        7,
    ].map((value) => ({
        value,
        label: t(
            `ct-settings-rule.condition.${
                [
                    'monday',
                    'tuesday',
                    'wednesday',
                    'thursday',
                    'friday',
                    'saturday',
                    'sunday',
                ][value - 1]
            }`,
        ),
    })),
);
const numericOperatorOptions = computed(() => [
    { label: t('ct-settings-rule.condition.operatorEquals'), value: '=' },
    { label: t('ct-settings-rule.condition.operatorNotEquals'), value: '!=' },
    { label: t('ct-settings-rule.condition.operatorGreaterThan'), value: '>' },
    { label: t('ct-settings-rule.condition.operatorGreaterThanEquals'), value: '>=' },
    { label: t('ct-settings-rule.condition.operatorLessThan'), value: '<' },
    { label: t('ct-settings-rule.condition.operatorLessThanEquals'), value: '<=' },
]);
const defaults = (type: EditableRuleCondition['type']): Record<string, unknown> => {
    if (type === 'dateRange') return { fromDate: '', toDate: '', useTime: true, timezone: null };
    if (type === 'timeRange') return { fromTime: '09:00', toTime: '18:00', timezone: null };
    if (type === 'dayOfWeek') return { operator: '=', dayOfWeek: 1 };
    if (type === 'userDaysSinceFirstLogin' || type === 'userDaysSinceLastLogin') {
        return { operator: '=', daysPassed: 0 };
    }
    return { operator: '=', languageIds: [] };
};
const updateMode = (value: 'all' | 'any'): void => emit('update:mode', value);

const updateConditions = (mutator: (conditions: EditableRuleCondition[]) => void): void => {
    const conditions = Contena.Utils.object.cloneDeep(props.conditions);
    mutator(conditions);
    emit('update:conditions', conditions);
};
const addCondition = (): void =>
    updateConditions((conditions) => {
        conditions.push({ key: Contena.Utils.createId(), type: 'timeRange', value: defaults('timeRange') });
    });
const addGroup = (): void =>
    updateConditions((conditions) => {
        conditions.push({
            key: Contena.Utils.createId(),
            type: props.mode === 'all' ? 'orContainer' : 'andContainer',
            value: {},
            children: [{ key: Contena.Utils.createId(), type: 'timeRange', value: defaults('timeRange') }],
        });
    });
const removeCondition = (index: number): void =>
    updateConditions((conditions) => {
        conditions.splice(index, 1);
    });
const removeAll = (): void => emit('update:conditions', []);
const resetValue = (condition: EditableRuleCondition): void => {
    condition.value = defaults(condition.type);
};
const setLanguage = (condition: EditableRuleCondition, value: string | null): void => {
    condition.value.languageIds = value ? [value] : [];
};
const isGroup = (condition: EditableRuleCondition): boolean =>
    condition.type === 'andContainer' || condition.type === 'orContainer';
const groupMode = (condition: EditableRuleCondition): 'all' | 'any' => (condition.type === 'orContainer' ? 'any' : 'all');
const updateGroup = (index: number, children: EditableRuleCondition[]): void =>
    updateConditions((conditions) => {
        conditions[index].children = children;
    });
const updateGroupMode = (index: number, mode: 'all' | 'any'): void =>
    updateConditions((conditions) => {
        conditions[index].type = mode === 'all' ? 'andContainer' : 'orContainer';
    });

swDefinePublic({
    conditionTypes,
    modeOptions,
    weekdayOptions,
    numericOperatorOptions,
    updateMode,
    addCondition,
    addGroup,
    removeCondition,
    removeAll,
    resetValue,
    setLanguage,
    isGroup,
    groupMode,
    updateGroup,
    updateGroupMode,
});

defineExpose({
    conditionTypes,
    modeOptions,
    weekdayOptions,
    numericOperatorOptions,
    updateMode,
    addCondition,
    addGroup,
    removeCondition,
    removeAll,
    resetValue,
    setLanguage,
    isGroup,
    groupMode,
    updateGroup,
    updateGroupMode,
});
</script>

<style scoped>
.ct-rule-condition-editor {
    width: 100%;
}

.ct-rule-condition-editor__container {
    padding: var(--scale-size-24, 24px);
    border: 1px solid var(--color-border-secondary-default, #d8dde6);
    border-radius: var(--border-radius-card, 8px);
    background: var(--color-elevation-surface-default, #fff);
}

.is--nested > .ct-rule-condition-editor__container {
    background: var(--color-elevation-surface-sunken, #f6f8fa);
}

.ct-rule-condition-editor__toolbar {
    max-width: var(--scale-size-256, 256px);
    margin-bottom: var(--scale-size-16, 16px);
}

.ct-rule-condition-editor__toolbar :deep(.mt-field),
.ct-rule-condition-editor__toolbar :deep(.mt-select) {
    margin-bottom: 0;
}

.ct-rule-condition-editor__condition {
    display: grid;
    grid-template-columns: var(--scale-size-256, 256px) minmax(0, 1fr) auto;
    align-items: center;
    gap: var(--scale-size-4, 4px);
    width: 100%;
}

.ct-rule-condition-editor__fields {
    display: flex;
    align-items: center;
    gap: var(--scale-size-4, 4px);
    min-width: 0;
}

.ct-rule-condition-editor__type,
.ct-rule-condition-editor__fields > * {
    min-width: 0;
    margin-bottom: 0;
    flex: 1;
}

.ct-rule-condition-editor__condition :deep(.mt-field),
.ct-rule-condition-editor__condition :deep(.mt-select),
.ct-rule-condition-editor__condition :deep(.ct-field),
.ct-rule-condition-editor__condition :deep(.ct-entity-single-select) {
    margin-bottom: 0;
}

.ct-rule-condition-editor__condition :deep(.mt-field__label),
.ct-rule-condition-editor__condition :deep(.mt-datepicker__label),
.ct-rule-condition-editor__condition :deep(.ct-field__label) {
    display: none;
}

.ct-rule-condition-editor__spacer {
    padding: var(--scale-size-8, 8px) 0;
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
    font-weight: var(--font-weight-semibold, 600);
    text-transform: uppercase;
}

.ct-rule-condition-editor__group {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: var(--scale-size-8, 8px);
}

.ct-rule-condition-editor__empty {
    padding: var(--scale-size-24, 24px);
    border: 1px dashed var(--color-border-secondary-default, #c7ccd5);
    border-radius: var(--border-radius-card, 8px);
    color: var(--color-text-secondary-default, #6b7280);
    text-align: center;
}

.ct-rule-condition-editor__actions {
    display: flex;
    gap: var(--scale-size-8, 8px);
    margin-top: var(--scale-size-16, 16px);
}

.ct-rule-condition-editor__delete-all {
    margin-left: auto;
}

@media (max-width: 960px) {
    .ct-rule-condition-editor__condition {
        grid-template-columns: 1fr auto;
    }

    .ct-rule-condition-editor__type,
    .ct-rule-condition-editor__fields {
        grid-column: 1;
    }

    .ct-rule-condition-editor__condition > .mt-button {
        grid-column: 2;
        grid-row: 1;
    }

    .ct-rule-condition-editor__fields {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

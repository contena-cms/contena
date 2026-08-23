<template>
    <ct-block name="sw_flow_sequence_selector">
        <div class="ct-flow-sequence-selector">
            <ct-block name="sw_flow_sequence_selector_title">
                <div class="ct-flow-sequence-selector__title">
                    <h4>{{ title }}</h4>
                </div>
            </ct-block>

            <ct-block name="sw_flow_sequence_selector_help_text">
                <div class="ct-flow-sequence-selector__help-text">
                    <p>{{ helpText }}</p>
                </div>
            </ct-block>

            <ct-block name="sw_flow_sequence_selector_actions">
                <div class="ct-flow-sequence-selector__actions">
                    <mt-button
                        class="ct-flow-sequence-selector__add-condition"
                        variant="secondary"
                        :disabled="disabled || undefined"
                        @click="chooseCondition"
                    >
                        <template #iconFront>
                            <mt-icon name="regular-rule-s" size="14px" />
                        </template>
                        {{ $t('ct-flow.sequence.addConditionIf') }}
                    </mt-button>

                    <mt-button
                        class="ct-flow-sequence-selector__add-action"
                        variant="secondary"
                        :disabled="disabled || undefined"
                        @click="chooseAction"
                    >
                        <template #iconFront>
                            <mt-icon name="regular-file-edit-s" size="14px" />
                        </template>
                        {{ $t('ct-flow.sequence.addActionThen') }}
                    </mt-button>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps({
    rootIndex: { type: Number, default: null },
    branch: { type: String as () => 'true' | 'false' | null, default: null },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits<{ choose: [type: 'condition' | 'action'] }>();

const title = computed(() =>
    props.rootIndex !== null && props.rootIndex > 0
        ? Contena.Snippet.tc('ct-flow.sequence.selectorAddTitle')
        : Contena.Snippet.tc('ct-flow.sequence.selectorTitle'),
);
const helpText = computed(() => {
    if (props.branch === 'true') {
        return Contena.Snippet.tc('ct-flow.sequence.selectorTrueHelp');
    }
    if (props.branch === 'false') {
        return Contena.Snippet.tc('ct-flow.sequence.selectorFalseHelp');
    }
    if (props.rootIndex !== null && props.rootIndex > 0) {
        return Contena.Snippet.tc('ct-flow.sequence.selectorAddHelp');
    }
    return Contena.Snippet.tc('ct-flow.sequence.selectorHelp');
});
const chooseCondition = (): void => emit('choose', 'condition');
const chooseAction = (): void => emit('choose', 'action');

swDefinePublic({
    title,
    helpText,
    chooseCondition,
    chooseAction,
});

defineExpose({ title, helpText, chooseCondition, chooseAction });
</script>

<style scoped>
.ct-flow-sequence-selector {
    width: 20.5rem;
    margin-right: var(--scale-size-32, 32px);
    padding: var(--scale-size-24, 24px);
    border: 2px dashed var(--color-border-secondary-default, #d8dde6);
    border-radius: var(--border-radius-card, 8px);
}

.ct-flow-sequence-selector__title h4 {
    margin: 0 0 var(--scale-size-12, 12px);
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-s, 16px);
    font-weight: var(--font-weight-medium, 500);
    line-height: var(--font-line-height-s, 24px);
}

.ct-flow-sequence-selector__help-text p {
    margin: 0 0 var(--scale-size-16, 16px);
    color: var(--color-text-primary-default, #2b3137);
    font-size: var(--font-size-xs, 14px);
    font-weight: var(--font-weight-regular, 400);
    line-height: var(--font-line-height-xs, 20px);
}

.ct-flow-sequence-selector__actions {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: var(--scale-size-12, 12px);
}
</style>

<template>
    <ct-block name="ct_flow_sequence">
        <div class="ct-flow-sequence">
            <ct-block name="ct_flow_sequence_content">
                <ct-flow-sequence-selector
                    v-if="sequence.type === 'selector'"
                    :root-index="rootIndex"
                    :branch="branch"
                    :disabled="disabled"
                    @choose="replaceSequence"
                />

                <ct-flow-sequence-condition
                    v-else-if="sequence.type === 'condition'"
                    :sequence="sequence"
                    :is-root="isRoot"
                    :disabled="disabled"
                    @update:sequence="updateSequence"
                    @remove="removeSequence"
                    @choose-branch="replaceBranch"
                />

                <ct-flow-sequence-action
                    v-else
                    :sequence="sequence"
                    :available-actions="availableActions"
                    :disabled="disabled"
                    @update:sequence="updateSequence"
                    @remove="removeSequence"
                />
            </ct-block>

            <ct-block name="ct_flow_sequence_true_block">
                <div v-if="sequence.trueChild" class="ct-flow-sequence__true-block" :class="trueBlockClasses">
                    <ct-flow-sequence
                        :sequence="sequence.trueChild"
                        :available-actions="availableActions"
                        :disabled="disabled"
                        branch="true"
                        @update:sequence="(value) => updateBranch('true', value)"
                        @remove="() => removeBranch('true')"
                    />
                </div>
            </ct-block>

            <ct-block name="ct_flow_sequence_false_block">
                <div v-if="sequence.falseChild" class="ct-flow-sequence__false-block">
                    <ct-flow-sequence
                        :sequence="sequence.falseChild"
                        :available-actions="availableActions"
                        :disabled="disabled"
                        branch="false"
                        @update:sequence="(value) => updateBranch('false', value)"
                        @remove="() => removeBranch('false')"
                    />
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue';

import {
    createActionSequence,
    createConditionSequence,
    createSelectorSequence,
    type EditableFlowSequence,
    type FlowActionOption,
} from '../flow-sequence.types';

const { cloneDeep } = Contena.Utils.object;
const props = defineProps({
    sequence: { type: Object as PropType<EditableFlowSequence>, required: true },
    availableActions: { type: Array as PropType<FlowActionOption[]>, required: true },
    rootIndex: { type: Number, default: null },
    branch: { type: String as () => 'true' | 'false' | null, default: null },
    isRoot: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits<{
    'update:sequence': [sequence: EditableFlowSequence];
    remove: [];
}>();

const trueBlockClasses = computed(() => ({
    'has--selector':
        props.isRoot &&
        props.sequence.type === 'condition' &&
        props.sequence.trueChild?.type === 'selector' &&
        props.sequence.falseChild?.type === 'selector',
}));
const createSequence = (type: 'condition' | 'action'): EditableFlowSequence =>
    type === 'condition' ? createConditionSequence() : createActionSequence();
const replaceSequence = (type: 'condition' | 'action'): void => emit('update:sequence', createSequence(type));
const updateSequence = (sequence: EditableFlowSequence): void => emit('update:sequence', sequence);
const removeSequence = (): void => emit('remove');
const replaceBranch = (branch: 'true' | 'false', type: 'condition' | 'action'): void => {
    updateBranch(branch, createSequence(type));
};
const updateBranch = (branch: 'true' | 'false', sequence: EditableFlowSequence): void => {
    const updated = cloneDeep(props.sequence);
    if (branch === 'true') updated.trueChild = sequence;
    else updated.falseChild = sequence;
    emit('update:sequence', updated);
};
const removeBranch = (branch: 'true' | 'false'): void => {
    const updated = cloneDeep(props.sequence);
    const replacement = props.isRoot ? createSelectorSequence() : null;
    if (branch === 'true') updated.trueChild = replacement;
    else updated.falseChild = replacement;
    emit('update:sequence', updated);
};

ctDefinePublic({
    trueBlockClasses,
    replaceSequence,
    updateSequence,
    removeSequence,
    replaceBranch,
    updateBranch,
    removeBranch,
});

defineExpose({
    trueBlockClasses,
    replaceSequence,
    updateSequence,
    removeSequence,
    replaceBranch,
    updateBranch,
    removeBranch,
});
</script>

<style scoped>
.ct-flow-sequence {
    display: grid;
    grid-template-columns: auto;
    grid-template-rows: auto 1fr;
    width: min-content;
}

.ct-flow-sequence__true-block {
    grid-row: 1;
    grid-column: 2;
}

.ct-flow-sequence__true-block.has--selector {
    grid-row: 1 / span 2;
}

.ct-flow-sequence__false-block {
    grid-row: 2 / span 2;
    grid-column: 1;
}
</style>

<template>
    <ct-block name="sw_flow_sequence_editor">
        <div class="ct-flow-sequence-editor">
            <ct-block name="sw_flow_sequence_editor_diagram">
                <div class="ct-flow-sequence-editor__diagram">
                    <div class="ct-flow-sequence-editor__oval" aria-hidden="true"></div>

                    <transition-group name="list" tag="div">
                        <div
                            v-for="(sequence, index) in sequences"
                            :key="sequence.key"
                            class="ct-flow-sequence-editor__position list-item"
                        >
                            <mt-button
                                class="ct-flow-sequence-editor__position-plus"
                                variant="secondary"
                                size="x-small"
                                square
                                :disabled="disabled || undefined"
                                :aria-label="$t('ct-flow.sequence.addSequence')"
                                @click="addRootSequence"
                            >
                                <mt-icon name="regular-plus-xs" size="12px" />
                            </mt-button>

                            <div class="ct-flow-sequence-editor__position-connection" aria-hidden="true">
                                <mt-icon name="regular-chevron-right-xs" size="12px" />
                            </div>

                            <div class="ct-flow-sequence-editor__sequences">
                                <ct-flow-sequence
                                    :sequence="sequence"
                                    :available-actions="availableActions"
                                    :root-index="index"
                                    is-root
                                    :disabled="disabled"
                                    @update:sequence="(value) => setRootSequence(index, value)"
                                    @remove="removeRootSequence(index)"
                                />
                            </div>
                        </div>
                    </transition-group>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { type PropType } from 'vue';

import { createSelectorSequence, type EditableFlowSequence, type FlowActionOption } from '../flow-sequence.types';

const { cloneDeep } = Contena.Utils.object;
const props = defineProps({
    sequences: { type: Array as PropType<EditableFlowSequence[]>, required: true },
    availableActions: { type: Array as PropType<FlowActionOption[]>, required: true },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits<{ 'update:sequences': [sequences: EditableFlowSequence[]] }>();

const update = (mutator: (draft: EditableFlowSequence[]) => void): void => {
    const draft = cloneDeep(props.sequences);
    mutator(draft);
    emit('update:sequences', draft);
};
const addRootSequence = (): void => update((draft) => draft.push(createSelectorSequence()));
const setRootSequence = (index: number, sequence: EditableFlowSequence): void =>
    update((draft) => {
        draft[index] = sequence;
    });
const removeRootSequence = (index: number): void =>
    update((draft) => {
        if (draft.length === 1) draft[0] = createSelectorSequence();
        else draft.splice(index, 1);
    });

swDefinePublic({
    addRootSequence,
    setRootSequence,
    removeRootSequence,
});

defineExpose({ addRootSequence, setRootSequence, removeRootSequence });
</script>

<style scoped>
.ct-flow-sequence-editor {
    position: relative;
    min-width: 20.5rem;
}

.ct-flow-sequence-editor__diagram {
    position: relative;
    margin-left: var(--scale-size-30, 30px);
    padding: 0 0 var(--scale-size-40, 40px);
}

.ct-flow-sequence-editor__oval {
    position: absolute;
    z-index: 2;
    top: -8px;
    left: -5px;
    width: var(--scale-size-12, 12px);
    height: var(--scale-size-12, 12px);
    border: 1px solid var(--color-border-secondary-default, #d8dde6);
    border-radius: var(--border-radius-round, 50%);
    background: var(--color-elevation-surface-raised, #fff);
}

.ct-flow-sequence-editor__position {
    position: relative;
    display: flex;
}

.ct-flow-sequence-editor__position::before {
    z-index: 1;
    display: block;
    border-left: 2px dashed var(--color-border-secondary-default, #d8dde6);
    content: '';
}

.ct-flow-sequence-editor__position:last-child::before {
    height: 165px;
}

.ct-flow-sequence-editor__sequences {
    margin-left: var(--scale-size-40, 40px);
    padding-top: var(--scale-size-64, 64px);
}

.ct-flow-sequence-editor__position:not(:last-child) .ct-flow-sequence-editor__sequences {
    width: 100%;
    padding-bottom: var(--scale-size-64, 64px);
    border-bottom: 1px solid var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-editor__position-connection {
    position: absolute;
    top: 64px;
    left: 0;
    width: var(--scale-size-32, 32px);
    height: 58px;
    border-bottom: 2px dashed var(--color-border-secondary-default, #d8dde6);
    border-left: 2px dashed var(--color-border-secondary-default, #d8dde6);
    border-bottom-left-radius: 40px;
}

.ct-flow-sequence-editor__position-connection .mt-icon {
    position: absolute;
    right: -10px;
    bottom: -7px;
    color: var(--color-border-secondary-default, #d8dde6);
}

.ct-flow-sequence-editor__position-plus {
    display: none;
}

.ct-flow-sequence-editor__position:last-child .ct-flow-sequence-editor__position-plus {
    position: absolute;
    z-index: 2;
    top: 155px;
    left: -11px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--border-radius-round, 50%);
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

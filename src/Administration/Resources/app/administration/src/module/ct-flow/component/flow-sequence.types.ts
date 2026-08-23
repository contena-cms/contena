/** @private */
export interface FlowRuleSummary {
    id: string;
    name: string;
    description?: string | null;
}

/** @private */
export interface EditableFlowAction {
    key: string;
    actionName: string | null;
    config: Record<string, unknown>;
}

/** @private */
export interface EditableFlowSequence {
    key: string;
    type: 'selector' | 'condition' | 'action';
    ruleId: string | null;
    rule: FlowRuleSummary | null;
    actions: EditableFlowAction[];
    trueChild: EditableFlowSequence | null;
    falseChild: EditableFlowSequence | null;
}

/** @private */
export interface FlowActionOption {
    label: string;
    value: string;
    icon: string;
    group: string;
}

/** @private */
export interface FlowEventOption {
    name: string;
    aware?: string[];
}

/** @private */
export function createSelectorSequence(): EditableFlowSequence {
    return {
        key: Contena.Utils.createId(),
        type: 'selector',
        ruleId: null,
        rule: null,
        actions: [],
        trueChild: null,
        falseChild: null,
    };
}

/** @private */
export function createActionSequence(): EditableFlowSequence {
    return {
        ...createSelectorSequence(),
        type: 'action',
        actions: [
            {
                key: Contena.Utils.createId(),
                actionName: null,
                config: {},
            },
        ],
    };
}

/** @private */
export function createConditionSequence(): EditableFlowSequence {
    return {
        ...createSelectorSequence(),
        type: 'condition',
    };
}

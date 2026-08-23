import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';
import { createConditionSequence, createSelectorSequence, type EditableFlowSequence } from '../flow-sequence.types';

const wrappers: Array<{ unmount: () => void }> = [];

async function createWrapper(sequence: EditableFlowSequence, isRoot = true) {
    const component = (await import('./ct-flow-sequence.vue')).default;
    const wrapper = mount(component, {
        props: {
            sequence,
            isRoot,
            availableActions: [],
        },
        global: {
            stubs: {
                'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                'ct-flow-sequence-selector': true,
                'ct-flow-sequence-condition': true,
                'ct-flow-sequence-action': true,
                'ct-flow-sequence': false,
            },
        },
    });
    wrappers.push(wrapper);
    return wrapper;
}

describe('module/ct-flow/component/ct-flow-sequence', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () => ({ addPrivilegeMappingEntry: jest.fn() }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));

    it('renders the condition branches in the upstream L-shaped grid', async () => {
        const condition = createConditionSequence();
        condition.ruleId = 'rule-id';
        condition.trueChild = createSelectorSequence();
        condition.falseChild = createSelectorSequence();
        const wrapper = await createWrapper(condition);

        expect(wrapper.find('.ct-flow-sequence__true-block.has--selector').exists()).toBe(true);
        expect(wrapper.find('.ct-flow-sequence__false-block').exists()).toBe(true);
    });

    it('restores a root selector when a branch is removed', async () => {
        const condition = createConditionSequence();
        condition.ruleId = 'rule-id';
        condition.trueChild = createSelectorSequence();
        const wrapper = await createWrapper(condition);
        const sequence = wrapper.vm as unknown as { removeBranch: (branch: 'true' | 'false') => void };

        sequence.removeBranch('true');

        const emitted = wrapper.emitted('update:sequence')?.[0]?.[0] as EditableFlowSequence;
        expect(emitted.trueChild?.type).toBe('selector');
    });
});

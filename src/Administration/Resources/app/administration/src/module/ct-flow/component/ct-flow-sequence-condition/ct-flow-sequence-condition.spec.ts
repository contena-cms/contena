import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';
import { createConditionSequence, type EditableFlowSequence } from '../flow-sequence.types';

describe('module/ct-flow/component/ct-flow-sequence-condition', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () => ({ addPrivilegeMappingEntry: jest.fn() }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    it('creates the two root selectors only after a rule is selected', async () => {
        const component = (await import('./ct-flow-sequence-condition.vue')).default;
        const wrapper = mount(component, {
            props: { sequence: createConditionSequence(), isRoot: true },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({ get: jest.fn(() => Promise.resolve({ id: 'rule-id', name: 'Rule' })) }),
                    },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-entity-select': true,
                },
            },
        });
        const condition = wrapper.vm as unknown as { setRule: (ruleId: string) => Promise<void> };

        await condition.setRule('rule-id');

        const emitted = wrapper.emitted('update:sequence')?.[0]?.[0] as EditableFlowSequence;
        expect(emitted.trueChild?.type).toBe('selector');
        expect(emitted.falseChild?.type).toBe('selector');
        wrapper.unmount();
    });

    it('opens the upstream-style rule editor for create and edit interactions', async () => {
        const component = (await import('./ct-flow-sequence-condition.vue')).default;
        const sequence = createConditionSequence();
        sequence.ruleId = 'rule-id';
        sequence.rule = { id: 'rule-id', name: 'Rule' };
        const wrapper = mount(component, {
            props: { sequence, isRoot: true },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-entity-select': true,
                    'ct-flow-rule-modal': true,
                },
            },
        });
        const condition = wrapper.vm as unknown as {
            openRuleModal: (ruleId: string | null) => void;
            selectedRuleId: string | null;
            showRuleModal: boolean;
        };

        condition.openRuleModal('rule-id');

        expect(condition.selectedRuleId).toBe('rule-id');
        expect(condition.showRuleModal).toBe(true);
        wrapper.unmount();
    });

    it('keeps advanced selection and rule creation inside the rule dropdown', async () => {
        const component = (await import('./ct-flow-sequence-condition.vue')).default;
        const wrapper = mount(component, {
            props: { sequence: createConditionSequence(), isRoot: true },
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => ({ search: jest.fn(() => Promise.resolve(Object.assign([], { total: 0 }))) }),
                    },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': defineComponent({
                        emits: ['click'],
                        template: '<button @click="$emit(\'click\')"><slot /></button>',
                    }),
                    'mt-icon': true,
                    'mt-entity-select': defineComponent({
                        template: '<div><slot name="before-item-list" /></div>',
                    }),
                    'mt-modal-root': true,
                    'mt-modal': true,
                    'mt-data-table': true,
                    'ct-flow-rule-modal': true,
                },
            },
        });
        const condition = wrapper.vm as unknown as {
            showAdvancedRuleSelection: boolean;
            showRuleModal: boolean;
            closeAdvancedRuleSelection: () => void;
        };

        await wrapper.get('.ct-flow-sequence-condition__advanced-rule').trigger('click');
        expect(condition.showAdvancedRuleSelection).toBe(true);

        condition.closeAdvancedRuleSelection();
        await wrapper.get('.ct-flow-sequence-condition__create-rule').trigger('click');
        expect(condition.showRuleModal).toBe(true);
        wrapper.unmount();
    });

    it('applies one rule from the advanced table instead of closing on row click', async () => {
        const component = (await import('./ct-flow-sequence-condition.vue')).default;
        const search = jest.fn(() => Promise.resolve(Object.assign([], { total: 0 })));
        const get = jest.fn(() => Promise.resolve({ id: 'advanced-rule', name: 'Advanced rule' }));
        const wrapper = mount(component, {
            props: { sequence: createConditionSequence(), isRoot: true },
            global: {
                provide: { repositoryFactory: { create: () => ({ search, get }) } },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-entity-select': true,
                    'mt-data-table': true,
                    'mt-modal-root': true,
                    'mt-modal': true,
                    'ct-flow-rule-modal': true,
                },
            },
        });
        const condition = wrapper.vm as unknown as {
            openAdvancedRuleSelection: () => void;
            selectAdvancedRule: (rule: { id: string }) => void;
            applyAdvancedRuleSelection: () => Promise<void>;
            showAdvancedRuleSelection: boolean;
            selectedAdvancedRuleIds: string[];
        };

        condition.openAdvancedRuleSelection();
        condition.selectAdvancedRule({ id: 'advanced-rule' });
        expect(condition.showAdvancedRuleSelection).toBe(true);
        expect(condition.selectedAdvancedRuleIds).toEqual(['advanced-rule']);

        await condition.applyAdvancedRuleSelection();

        expect(get).toHaveBeenCalledWith('advanced-rule', Contena.Context.api);
        expect(condition.showAdvancedRuleSelection).toBe(false);
        expect(wrapper.emitted('update:sequence')?.[0]?.[0]).toEqual(expect.objectContaining({ ruleId: 'advanced-rule' }));
        wrapper.unmount();
    });
});

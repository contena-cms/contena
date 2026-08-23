import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';

describe('module/ct-flow/component/ct-flow-rule-modal', () => {
    it('creates a reusable rule and returns it to the condition', async () => {
        const rule = {
            id: 'rule-id',
            name: '',
            priority: 1,
            description: 'Working hours',
            isNew: () => true,
        };
        const ruleRepository = {
            create: jest.fn(() => rule),
            save: jest.fn(() => Promise.resolve()),
        };
        const conditionRepository = {
            create: jest.fn(() => ({})),
            save: jest.fn(() => Promise.resolve()),
            delete: jest.fn(() => Promise.resolve()),
        };
        const component = (await import('./ct-flow-rule-modal.vue')).default;
        const wrapper = mount(component, {
            props: { ruleId: null },
            global: {
                provide: {
                    repositoryFactory: {
                        create: (entity: string) => (entity === 'rule' ? ruleRepository : conditionRepository),
                    },
                    acl: { can: jest.fn(() => true) },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal-root': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal': defineComponent({ template: '<div><slot /><slot name="footer" /></div>' }),
                    'mt-tabs': true,
                    'ct-container': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-text-field': true,
                    'mt-number-field': true,
                    'mt-textarea': true,
                    'ct-rule-condition-editor': true,
                    'mt-button': true,
                },
            },
        });
        await flushPromises();
        (wrapper.vm as unknown as { rule: { name: string } }).rule.name = 'Business hours';
        await wrapper.vm.$nextTick();

        await (wrapper.vm as unknown as { onSave: () => Promise<void> }).onSave();

        expect(ruleRepository.save).toHaveBeenCalledWith(rule);
        expect(conditionRepository.save).toHaveBeenCalled();
        expect(wrapper.emitted('process-finish')?.[0]?.[0]).toEqual({
            id: 'rule-id',
            name: 'Business hours',
            description: 'Working hours',
        });
    });

    it('loads condition pages after the association limit', async () => {
        const rule = {
            id: 'rule-id',
            name: 'Existing rule',
            priority: 1,
            description: '',
            isNew: () => false,
        };
        const firstPage = Array.from({ length: 500 }, (_, index) => ({
            id: `condition-${index}`,
            parentId: null,
            type: 'dayOfWeek',
            value: { operator: '=', dayOfWeek: 1 },
            position: index,
        }));
        const storedConditions = Object.assign(firstPage, {
            total: 501,
            criteria: { page: 1 },
            context: Contena.Context.api,
        });
        const nextPage = Object.assign(
            [
                {
                    id: 'condition-500',
                    parentId: null,
                    type: 'dayOfWeek',
                    value: { operator: '=', dayOfWeek: 1 },
                    position: 500,
                },
            ],
            { total: 501, criteria: { page: 2 } },
        );
        const ruleRepository = {
            get: jest.fn(() => Promise.resolve({ ...rule, conditions: storedConditions })),
        };
        const conditionRepository = {
            search: jest.fn(() => Promise.resolve(nextPage)),
        };
        const component = (await import('./ct-flow-rule-modal.vue')).default;
        const wrapper = mount(component, {
            props: { ruleId: rule.id },
            global: {
                provide: {
                    repositoryFactory: {
                        create: (entity: string) => (entity === 'rule' ? ruleRepository : conditionRepository),
                    },
                    acl: { can: jest.fn(() => true) },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal-root': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal': defineComponent({ template: '<div><slot /><slot name="footer" /></div>' }),
                    'mt-tabs': true,
                    'ct-container': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-text-field': true,
                    'mt-number-field': true,
                    'mt-textarea': true,
                    'ct-rule-condition-editor': true,
                    'mt-button': true,
                },
            },
        });
        await flushPromises();

        expect(conditionRepository.search).toHaveBeenCalledWith(
            expect.objectContaining({ page: 2, limit: 500 }),
            Contena.Context.api,
        );
        expect((wrapper.vm as unknown as { conditions: unknown[] }).conditions).toHaveLength(501);
        wrapper.unmount();
    });
});

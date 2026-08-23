import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';

describe('module/ct-flow/component/ct-flow-user-custom-field-modal', () => {
    it('parses structured custom field values before saving', async () => {
        const component = (await import('./ct-flow-user-custom-field-modal.vue')).default;
        const wrapper = mount(component, {
            props: {
                config: { customFieldId: 'field-id', customFieldValue: ['one'], option: 'add' },
            },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal-root': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal': defineComponent({ template: '<div><slot /><slot name="footer" /></div>' }),
                    'mt-entity-select': true,
                    'mt-select': true,
                    'mt-textarea': true,
                    'mt-button': true,
                },
            },
        });
        const modal = wrapper.vm as unknown as { customFieldValue: string; onSave: () => void };
        modal.customFieldValue = '["one", "two"]';

        modal.onSave();

        expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({
            customFieldId: 'field-id',
            customFieldValue: [
                'one',
                'two',
            ],
            option: 'add',
        });
    });
});

import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';

describe('module/ct-flow/component/ct-flow-user-status-modal', () => {
    it('emits the selected user state', async () => {
        const component = (await import('./ct-flow-user-status-modal.vue')).default;
        const wrapper = mount(component, {
            props: { config: { active: false } },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal-root': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal': defineComponent({ template: '<div><slot /><slot name="footer" /></div>' }),
                    'mt-select': true,
                    'mt-button': true,
                },
            },
        });

        (wrapper.vm as unknown as { onSave: () => void }).onSave();

        expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({ active: false });
    });
});

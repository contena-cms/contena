import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';

describe('module/ct-flow/component/ct-flow-notification-modal', () => {
    it('normalizes privileges and emits an executable configuration', async () => {
        const component = (await import('./ct-flow-notification-modal.vue')).default;
        const wrapper = mount(component, {
            props: { config: { status: 'warning', message: ' Notice ', adminOnly: true, requiredPrivileges: [] } },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal-root': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal': defineComponent({ template: '<div><slot /><slot name="footer" /></div>' }),
                    'mt-select': true,
                    'mt-textarea': true,
                    'mt-switch': true,
                    'mt-button': true,
                },
            },
        });
        const modal = wrapper.vm as unknown as { requiredPrivileges: string; onSave: () => void };
        modal.requiredPrivileges = 'user.viewer, flow.viewer';

        modal.onSave();

        expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({
            status: 'warning',
            message: 'Notice',
            adminOnly: true,
            requiredPrivileges: [
                'user.viewer',
                'flow.viewer',
            ],
        });
    });
});

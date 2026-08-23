import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';

describe('module/ct-flow/component/ct-flow-tag-modal', () => {
    it('stores the selected tag for add and remove actions', async () => {
        const component = (await import('./ct-flow-tag-modal.vue')).default;
        const wrapper = mount(component, {
            props: { actionName: 'action.user.tag.remove', config: { tagIds: ['tag-id'] } },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal-root': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-modal': defineComponent({ template: '<div><slot /><slot name="footer" /></div>' }),
                    'mt-entity-select': true,
                    'mt-button': true,
                },
            },
        });

        (wrapper.vm as unknown as { onSave: () => void }).onSave();

        expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({ tagIds: ['tag-id'] });
    });
});

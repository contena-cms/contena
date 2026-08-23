import { defineComponent } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import itemModal from './ct-data-dictionary-item-modal.vue';

const modalStub = defineComponent({
    props: {
        variant: {
            type: String,
            default: '',
        },
    },
    template: '<div :data-variant="variant"><slot /><slot name="modal-footer" /></div>',
});

describe('module/ct-data-dictionary/component/ct-data-dictionary-item-modal', () => {
    let wrapper: VueWrapper | null = null;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('keeps node editing independent and emits modal actions to the parent', async () => {
        const item = {
            id: 'item-id',
            code: 'male',
            label: '男',
            description: null,
            position: 1,
            active: true,
        };

        wrapper = mount(itemModal, {
            props: {
                item,
                parentLabel: '根节点',
                canEdit: true,
                canCreate: true,
                canDelete: true,
            },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-modal': modalStub,
                    'ct-container': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-text-field': true,
                    'mt-textarea': true,
                    'mt-number-field': true,
                    'mt-switch': true,
                    'mt-button': true,
                },
            },
        });
        await flushPromises();

        expect(wrapper.find('[data-variant="small"]').exists()).toBe(true);

        const component = wrapper.vm as unknown as {
            onCancel: () => void;
            onSave: () => void;
            onAddChild: () => void;
            onDelete: () => void;
        };

        component.onCancel();
        component.onSave();
        component.onAddChild();
        component.onDelete();

        expect(wrapper.emitted('modal-close')).toHaveLength(2);
        expect(wrapper.emitted('save-item')).toEqual([[item]]);
        expect(wrapper.emitted('add-child')).toEqual([[item]]);
        expect(wrapper.emitted('delete-item')).toEqual([[item]]);
    });
});

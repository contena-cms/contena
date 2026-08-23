import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';
import { createActionSequence } from '../flow-sequence.types';

describe('module/ct-flow/component/ct-flow-sequence-action', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () => ({ addPrivilegeMappingEntry: jest.fn() }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    it('does not move actions across a stop-flow action', async () => {
        const sequence = createActionSequence();
        sequence.actions = [
            { key: 'first', actionName: 'action.mail.send', config: {} },
            { key: 'second', actionName: 'action.stop.flow', config: {} },
        ];
        const component = (await import('./ct-flow-sequence-action.vue')).default;
        const wrapper = mount(component, {
            props: {
                sequence,
                availableActions: [
                    { label: 'Send', value: 'action.mail.send', icon: 'regular-envelope', group: 'general' },
                    { label: 'Stop', value: 'action.stop.flow', icon: 'regular-times-circle', group: 'general' },
                ],
            },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-select': true,
                    'ct-flow-mail-send-modal': true,
                },
            },
        });
        const action = wrapper.vm as unknown as {
            canMoveAction: (index: number, offset: -1 | 1) => boolean;
            moveAction: (index: number, offset: -1 | 1) => void;
        };

        expect(action.canMoveAction(0, 1)).toBe(false);
        expect(action.canMoveAction(1, -1)).toBe(false);
        action.moveAction(1, -1);

        expect(wrapper.emitted('update:sequence')).toBeUndefined();
        wrapper.unmount();
    });

    it('exposes upstream-compatible move boundaries for ordinary actions', async () => {
        const sequence = createActionSequence();
        sequence.actions = [
            { key: 'first', actionName: 'action.mail.send', config: {} },
            { key: 'second', actionName: 'action.mail.send', config: {} },
            { key: 'third', actionName: 'action.mail.send', config: {} },
        ];
        const component = (await import('./ct-flow-sequence-action.vue')).default;
        const wrapper = mount(component, {
            props: {
                sequence,
                availableActions: [
                    { label: 'Send', value: 'action.mail.send', icon: 'regular-envelope', group: 'general' },
                ],
            },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-select': true,
                },
            },
        });
        const action = wrapper.vm as unknown as {
            canMoveAction: (index: number, offset: -1 | 1) => boolean;
        };

        expect(action.canMoveAction(0, -1)).toBe(false);
        expect(action.canMoveAction(0, 1)).toBe(true);
        expect(action.canMoveAction(1, -1)).toBe(true);
        expect(action.canMoveAction(1, 1)).toBe(true);
        expect(action.canMoveAction(2, -1)).toBe(true);
        expect(action.canMoveAction(2, 1)).toBe(false);
        expect(wrapper.findComponent({ name: 'TransitionGroup' }).props('name')).toBe('list');
        wrapper.unmount();
    });

    it('opens and saves user-aware tag actions without flattening their group', async () => {
        const sequence = createActionSequence();
        const component = (await import('./ct-flow-sequence-action.vue')).default;
        const wrapper = mount(component, {
            props: {
                sequence,
                availableActions: [
                    { label: 'Assign status', value: 'action.user.status.assign', icon: 'regular-user', group: 'general' },
                    { label: 'Add tag', value: 'action.user.tag.add', icon: 'regular-tag', group: 'tags' },
                ],
            },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-select': true,
                    'ct-flow-tag-modal': true,
                },
            },
        });
        const action = wrapper.vm as unknown as {
            selectAction: (actionName: string) => void;
            selectedActionName: string | null;
            saveSelectedAction: (config: Record<string, unknown>) => void;
            getActionGroupLabel: (group: string) => string;
        };

        action.selectAction('action.user.tag.add');
        expect(action.selectedActionName).toBe('action.user.tag.add');
        expect(action.getActionGroupLabel('tags')).toBe('ct-flow.sequence.actionGroup.tags');

        action.saveSelectedAction({ tagIds: ['tag-id'] });

        expect(wrapper.emitted('update:sequence')?.[0]?.[0]).toEqual(
            expect.objectContaining({
                actions: [expect.objectContaining({ actionName: 'action.user.tag.add', config: { tagIds: ['tag-id'] } })],
            }),
        );
        wrapper.unmount();
    });

    it('handles configurable and immediate actions selected through the visible option list', async () => {
        const sequence = createActionSequence();
        const component = (await import('./ct-flow-sequence-action.vue')).default;
        const selectStub = defineComponent({
            props: { options: { type: Array, required: true } },
            emits: ['item-add'],
            template: '<button class="action-option" @click="$emit(\'item-add\', options[0])">Choose action</button>',
        });
        const wrapper = mount(component, {
            props: {
                sequence,
                availableActions: [
                    { label: 'Assign status', value: 'action.user.status.assign', icon: 'regular-user', group: 'general' },
                ],
            },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-select': selectStub,
                    'ct-flow-user-status-modal': defineComponent({ template: '<div class="status-modal" />' }),
                },
            },
        });

        await wrapper.get('.action-option').trigger('click');
        expect(wrapper.find('.status-modal').exists()).toBe(true);
        expect((wrapper.vm as unknown as { modalConfig: Record<string, unknown> }).modalConfig).toEqual({ active: true });

        (wrapper.vm as unknown as { closeModal: () => void }).closeModal();
        await wrapper.setProps({
            availableActions: [
                { label: 'Stop flow', value: 'action.stop.flow', icon: 'regular-times-circle', group: 'general' },
            ],
        });
        await wrapper.get('.action-option').trigger('click');

        expect(wrapper.emitted('update:sequence')?.[0]?.[0]).toEqual(
            expect.objectContaining({
                actions: [expect.objectContaining({ actionName: 'action.stop.flow', config: {} })],
            }),
        );
        wrapper.unmount();
    });

    it('opens a configured action from the card and saves over the same action', async () => {
        const sequence = createActionSequence();
        sequence.actions = [
            { key: 'status-action', actionName: 'action.user.status.assign', config: { active: false } },
        ];
        const component = (await import('./ct-flow-sequence-action.vue')).default;
        const statusModal = defineComponent({
            props: { config: { type: Object, required: true } },
            emits: ['save'],
            template: '<button class="save-status" @click="$emit(\'save\', { active: true })">Save</button>',
        });
        const wrapper = mount(component, {
            props: {
                sequence,
                availableActions: [
                    { label: 'Assign status', value: 'action.user.status.assign', icon: 'regular-user', group: 'general' },
                ],
            },
            global: {
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-button': true,
                    'mt-icon': true,
                    'mt-select': true,
                    'ct-flow-user-status-modal': statusModal,
                },
            },
        });

        await wrapper.get('.ct-flow-sequence-action__action-item').trigger('click');

        expect(wrapper.findComponent(statusModal).props('config')).toEqual({ active: false });
        await wrapper.get('.save-status').trigger('click');

        expect(wrapper.emitted('update:sequence')?.[0]?.[0]).toEqual(
            expect.objectContaining({
                actions: [
                    expect.objectContaining({
                        key: 'status-action',
                        actionName: 'action.user.status.assign',
                        config: { active: true },
                    }),
                ],
            }),
        );
        wrapper.unmount();
    });
});

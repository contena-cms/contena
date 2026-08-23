import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import type PrivilegesService from 'src/app/service/privileges.service';
import { createActionSequence, createSelectorSequence, type EditableFlowSequence } from '../flow-sequence.types';

const wrappers: Array<ReturnType<typeof mount>> = [];

async function createWrapper(sequences: EditableFlowSequence[] = [createSelectorSequence()]) {
    const wrapper = mount(await wrapTestComponent('ct-flow-sequence-editor', { sync: true }), {
        props: {
            sequences,
            availableActions: [
                { label: 'Send email', value: 'action.mail.send', icon: 'regular-envelope', group: 'general' },
                { label: 'Stop flow', value: 'action.stop.flow', icon: 'regular-times-circle', group: 'general' },
            ],
        },
        global: {
            stubs: {
                'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                'ct-flow-sequence': true,
                'mt-button': defineComponent({ template: '<button><slot /></button>' }),
                'mt-icon': true,
            },
        },
    });
    wrappers.push(wrapper);
    return wrapper;
}

describe('module/ct-flow/component/ct-flow-sequence-editor', () => {
    beforeAll(async () => {
        // eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
        Contena.Service().register(
            'privileges',
            () =>
                ({
                    addPrivilegeMappingEntry: jest.fn(),
                    getPrivileges: jest.fn(() => () => []),
                }) as unknown as PrivilegesService,
        );
        await import('../../index');
    });

    afterEach(() => {
        wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    });

    it('adds an independent selector root like the upstream display group control', async () => {
        const firstRoot = createActionSequence();
        const wrapper = await createWrapper([firstRoot]);
        const editor = wrapper.vm as unknown as { addRootSequence: () => void };

        editor.addRootSequence();

        expect(wrapper.emitted('update:sequences')?.[0]?.[0]).toEqual([
            firstRoot,
            expect.objectContaining({ type: 'selector' }),
        ]);
        expect(wrapper.findComponent({ name: 'TransitionGroup' }).props('name')).toBe('list');
    });

    it('restores the selector when the last root container is removed', async () => {
        const wrapper = await createWrapper([createActionSequence()]);
        const editor = wrapper.vm as unknown as { removeRootSequence: (index: number) => void };

        editor.removeRootSequence(0);

        expect(wrapper.emitted('update:sequences')?.[0]?.[0]).toEqual([
            expect.objectContaining({ type: 'selector' }),
        ]);
    });

    it('keeps consecutive actions inside one root container', async () => {
        const wrapper = await createWrapper();
        const editor = wrapper.vm as unknown as {
            setRootSequence: (index: number, sequence: EditableFlowSequence) => void;
        };
        const actionContainer = createActionSequence();
        actionContainer.actions = [
            { key: 'first', actionName: 'action.mail.send', config: {} },
            { key: 'second', actionName: 'action.stop.flow', config: {} },
        ];

        editor.setRootSequence(0, actionContainer);

        const emitted = wrapper.emitted('update:sequences')?.[0]?.[0] as EditableFlowSequence[];
        expect(emitted).toHaveLength(1);
        expect(emitted[0].actions).toHaveLength(2);
    });
});

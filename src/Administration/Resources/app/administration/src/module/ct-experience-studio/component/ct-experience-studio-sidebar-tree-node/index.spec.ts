/* eslint-disable @typescript-eslint/no-unsafe-call */
import { mount, shallowMount } from '@vue/test-utils';
import sidebarTreeNodeComponent from './index';

describe('module/ct-experience-studio/component/ct-experience-studio-sidebar-tree-node', () => {
    const getByName = jest.fn();
    const createWrapper = (props: Record<string, unknown> = {}) => {
        jest.spyOn(Contena.Store, 'get').mockReturnValue({ getByName } as never);

        return shallowMount(sidebarTreeNodeComponent, {
            props: {
                element: { id: 'element-id', component: 'Ct:Content:Text' },
                ...props,
            },
            global: {
                provide: {
                    acl: { can: () => true },
                },
            },
        });
    };

    afterEach(() => {
        jest.restoreAllMocks();
        getByName.mockReset();
    });

    it('uses configured type icon when available', () => {
        getByName.mockReturnValue({ icon: 'regular-align-left' });
        const wrapper = createWrapper();

        expect(wrapper.vm.typeIcon).toBe('regular-align-left');
    });

    it('labels the node through the anchor-only language chain', () => {
        getByName.mockReturnValue({ icon: null });
        const wrapper = createWrapper({
            element: {
                id: 'element-id',
                component: 'Ct:Content:Text',
                properties: {
                    title: {
                        [Contena.Defaults.systemLanguageId]: 'Headline',
                    },
                },
            },
        });

        expect(wrapper.vm.label).toBe('Headline');
    });

    it('falls back to generic icon when no type icon exists', () => {
        getByName.mockReturnValue({ icon: null });
        const wrapper = createWrapper({
            element: { id: 'element-id', component: 'Ct:Content:Unknown' },
        });

        expect(wrapper.vm.typeIcon).toBe('bars-square-s');
    });

    it('includes defined but currently empty slots in tree entries', () => {
        getByName.mockReturnValue({ slots: [{ name: 'content' }] });
        const wrapper = createWrapper({
            element: { id: 'element-id', component: 'Ct:Grid:Container', slots: {} },
        });

        expect(wrapper.vm.slotEntries).toEqual([{ name: 'content', elements: [] }]);
    });

    it('emits move payload when dropping an element into a slot', () => {
        getByName.mockReturnValue(null);
        const wrapper = createWrapper();

        wrapper.vm.onDropElement(
            { elementId: 'element-id' },
            { newParentElementId: 'parent-id', newSlotName: 'main', newIndex: 2 },
        );

        expect(wrapper.emitted('move-element')).toEqual([
            [
                {
                    elementId: 'element-id',
                    newParentElementId: 'parent-id',
                    newSlotName: 'main',
                    newIndex: 2,
                },
            ],
        ]);
    });

    it('marks dropping into dragged subtree as invalid', () => {
        getByName.mockReturnValue(null);
        const wrapper = createWrapper({ allowDragAndDrop: true });

        expect(
            wrapper.vm.validateMoveDrop(
                {
                    elementId: 'parent',
                    subtreeIds: [
                        'parent',
                        'child',
                    ],
                },
                { newParentElementId: 'child', newSlotName: 'main', newIndex: 0 },
            ),
        ).toBe(false);
    });

    it('prevents drag events from reaching the draggable ancestor through control buttons', async () => {
        const dragListener = jest.fn();
        getByName.mockReturnValue({ slots: [{ name: 'content' }] });
        jest.spyOn(Contena.Store, 'get').mockReturnValue({ getByName } as never);
        const wrapper = mount(sidebarTreeNodeComponent, {
            attachTo: document.body,
            props: {
                element: {
                    id: 'element-id',
                    component: 'Ct:Grid:Container',
                    slots: { content: [] },
                },
                allowDragAndDrop: true,
            },
            global: {
                provide: {
                    acl: { can: () => true },
                },
                directives: {
                    draggable: {
                        mounted(el: HTMLElement) {
                            el.addEventListener('mousedown', dragListener);
                        },
                        unmounted(el: HTMLElement) {
                            el.removeEventListener('mousedown', dragListener);
                        },
                    },
                    droppable: {},
                    tooltip: {},
                },
                stubs: {
                    'ct-block': { template: '<div><slot /></div>' },
                    'mt-icon': true,
                    'ct-experience-studio-sidebar-tree-node': true,
                },
            },
        });

        try {
            const duplicateButton = wrapper.get('.ct-experience-studio-sidebar-tree-node__actions button');

            await duplicateButton.trigger('mousedown');

            expect(dragListener).not.toHaveBeenCalled();

            for (const button of wrapper.findAll('button')) {
                await button.trigger('mousedown');
            }

            expect(dragListener).not.toHaveBeenCalled();
        } finally {
            wrapper.unmount();
        }
    });
});

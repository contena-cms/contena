/* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
import { createMutationResponse, createWrapper, resetWrappers } from './index.spec/test.helper';

describe('module/ct-experience-studio/page/ct-experience-studio-detail presets', () => {
    afterEach(() => {
        resetWrappers();
    });

    it('allows any preset at the root but gates slot presets by their root component', async () => {
        const { wrapper } = await createWrapper();
        const allowed = new Set(['CT:Content:Text']);
        const rootPayload = { parentElementId: null, slotName: null, anchorTop: 0, anchorLeft: 0 };
        const slotPayload = { parentElementId: 'parent-1', slotName: 'content', anchorTop: 0, anchorLeft: 0 };
        const containerPreset = {
            id: 'p',
            name: 'P',
            description: null,
            icon: null,
            payload: [{ id: 'x', component: 'CT:Grid:Container' }],
        };
        const textPreset = { ...containerPreset, payload: [{ id: 'x', component: 'CT:Content:Text' }] };

        expect(wrapper.vm.isPresetAllowedForPayload(containerPreset, rootPayload, allowed)).toBe(true);
        expect(wrapper.vm.isPresetAllowedForPayload(containerPreset, slotPayload, allowed)).toBe(false);
        expect(wrapper.vm.isPresetAllowedForPayload(textPreset, slotPayload, allowed)).toBe(true);
    });

    it('appends allowed presets to the picker elements and filters disallowed ones in slots', async () => {
        const { wrapper } = await createWrapper({
            services: {
                contentSystemLayoutPresetService: {
                    getPresets: jest.fn().mockResolvedValue([
                        {
                            id: 'allowed',
                            name: 'Allowed',
                            description: 'd',
                            icon: 'p',
                            payload: [{ id: 'a', component: 'CT:Content:Text' }],
                        },
                        {
                            id: 'blocked',
                            name: 'Blocked',
                            description: null,
                            icon: null,
                            payload: [{ id: 'b', component: 'CT:Grid:Container' }],
                        },
                    ]),
                },
            },
        });
        wrapper.vm.pendingAddElementPayload = {
            parentElementId: 'parent-1',
            slotName: 'content',
            anchorTop: 0,
            anchorLeft: 0,
        };
        wrapper.vm.elementTypeStore.typesByName = {
            'CT:Content:Text': {
                name: 'CT:Content:Text',
                label: 'Text',
                icon: 'i',
                category: 'content',
                slots: [],
            },
            'CT:Layout:Container': {
                name: 'CT:Layout:Container',
                label: 'Container',
                icon: null,
                category: 'layout',
                slots: [{ name: 'content', maxElements: null, allowList: ['CT:Content:Text'] }],
            },
        };
        wrapper.vm.layout = {
            rootSource: 'blog',
            layout: [
                {
                    id: 'parent-1',
                    component: 'CT:Layout:Container',
                    slots: { content: [] },
                },
            ],
        };
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.availablePickerElements).toEqual([
            { name: 'CT:Content:Text', label: 'Text', icon: 'i', category: 'content', kind: 'element' },
            {
                name: 'allowed',
                label: 'Allowed',
                icon: 'p',
                category: 'presets',
                kind: 'preset',
                id: 'allowed',
                description: 'd',
            },
        ]);
    });

    it('inserts a preset at the root through a single insert-preset mutation', async () => {
        const insertPreset = jest.fn().mockResolvedValue(createMutationResponse([], ['element-1']));
        const { wrapper } = await createWrapper({
            services: {
                contentSystemLayoutDraftMutationService: { insertPreset },
            },
        });
        wrapper.vm.pendingAddElementPayload = {
            parentElementId: null,
            slotName: null,
            anchorTop: 0,
            anchorLeft: 0,
        };
        wrapper.vm.layout = { rootSource: 'blog', layout: [] };

        await wrapper.vm.onSelectPreset('core.text-block');

        expect(insertPreset).toHaveBeenCalledWith({
            layout: [],
            rootSource: 'blog',
            presetId: 'core.text-block',
        });
        expect(wrapper.vm.isElementPickerOpen).toBe(false);
    });

    it('passes the parent and slot when inserting a preset into a slot', async () => {
        const insertPreset = jest.fn().mockResolvedValue(createMutationResponse([], ['element-1']));
        const { wrapper } = await createWrapper({
            services: {
                contentSystemLayoutDraftMutationService: { insertPreset },
            },
        });
        wrapper.vm.pendingAddElementPayload = {
            parentElementId: 'parent-1',
            slotName: 'content',
            anchorTop: 0,
            anchorLeft: 0,
        };
        wrapper.vm.layout = { rootSource: 'blog', layout: [] };

        await wrapper.vm.onSelectPreset('core.text-block');

        expect(insertPreset).toHaveBeenCalledWith({
            layout: [],
            rootSource: 'blog',
            presetId: 'core.text-block',
            parentElementId: 'parent-1',
            slot: 'content',
        });
    });
});

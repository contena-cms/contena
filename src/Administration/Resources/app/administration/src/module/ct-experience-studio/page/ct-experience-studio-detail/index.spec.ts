/* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
import { createCollection, createMutationResponse, createWrapper, resetWrappers } from './index.spec/test.helper';

describe('module/ct-experience-studio/page/ct-experience-studio-detail', () => {
    const ANCHOR_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
    const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
    afterEach(() => {
        resetWrappers();
    });

    it('starts inline session for text elements', async () => {
        const { wrapper } = await createWrapper();
        wrapper.vm.layout = {
            id: 'layout-1',
            rootSource: 'blog',
            layout: [
                {
                    id: 'element-1',
                    component: 'content:text',
                    properties: { text: '<p>Initial</p>' },
                },
            ],
        };
        wrapper.vm.elementTypeStore.typesByName = {
            'content:text': {
                name: 'content:text',
                properties: {},
            },
        };

        wrapper.vm.onInlineEditStart({
            elementId: 'element-1',
        });

        expect(wrapper.vm.selectedElementId).toBe('element-1');
        expect(wrapper.vm.inlineEditSession).toEqual({
            elementId: 'element-1',
            originalValue: '<p>Initial</p>',
            draftValue: '<p>Initial</p>',
            isEditing: true,
        });
    });

    it('commits inline session only when value changed', async () => {
        const { wrapper } = await createWrapper();
        wrapper.vm.layout = {
            id: 'layout-1',
            rootSource: 'blog',
            layout: [
                {
                    id: 'element-1',
                    component: 'content:text',
                    properties: { text: '<p>Before</p>' },
                },
            ],
        };
        wrapper.vm.elementTypeStore.typesByName = {
            'content:text': { name: 'content:text', properties: {} },
        };
        const pushToHistory = jest.spyOn(wrapper.vm.editorStore, 'pushToHistory');

        wrapper.vm.onInlineEditStart({ elementId: 'element-1' });
        await wrapper.vm.onInlineEditCommit({
            elementId: 'element-1',
            value: '<p>Before</p>',
        });
        expect(pushToHistory).not.toHaveBeenCalled();

        wrapper.vm.onInlineEditStart({ elementId: 'element-1' });
        wrapper.vm.onInlineEditChange({ elementId: 'element-1', value: '<p>After</p>' });
        await wrapper.vm.onInlineEditCommit({
            elementId: 'element-1',
            value: '<p>After</p>',
        });
        expect(pushToHistory).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.layout.layout[0].properties.text).toBe('<p>After</p>');
    });

    it('commits translatable inline text through update-element-properties while preserving other languages', async () => {
        const updatedElement = {
            id: 'element-1',
            component: 'Ct:Content:Text',
            properties: {
                text: {
                    [ANCHOR_LANGUAGE_ID]: 'Hello again',
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                },
            },
        };
        const updateElementProperties = jest.fn().mockResolvedValue(createMutationResponse([updatedElement], ['element-1']));
        const { wrapper } = await createWrapper({
            services: {
                contentSystemLayoutDraftMutationService: { updateElementProperties },
            },
        });
        const originalElement = {
            id: 'element-1',
            component: 'Ct:Content:Text',
            properties: {
                text: {
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                },
            },
        };
        wrapper.vm.layout = { id: 'layout-1', rootSource: 'blog', layout: [originalElement] };
        wrapper.vm.elementTypeStore.typesByName = {
            'Ct:Content:Text': {
                name: 'Ct:Content:Text',
                properties: { text: { translatable: true, adminUI: { component: 'text-editor' } } },
            },
        };

        wrapper.vm.onInlineEditStart({ elementId: 'element-1' });
        await wrapper.vm.onInlineEditCommit({ elementId: 'element-1', value: 'Hello again' });

        expect(updateElementProperties).toHaveBeenCalledWith({
            layout: [originalElement],
            rootSource: 'blog',
            elementId: 'element-1',
            values: {
                text: {
                    [ANCHOR_LANGUAGE_ID]: 'Hello again',
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                },
            },
        });
        expect(wrapper.vm.layout.layout).toEqual([updatedElement]);
        expect(wrapper.vm.selectedElementId).toBe('element-1');
    });

    it('reads an empty string when no anchor-chain language carries a translatable text entry', async () => {
        const { wrapper } = await createWrapper();
        wrapper.vm.elementTypeStore.typesByName = {
            'Ct:Content:Text': {
                name: 'Ct:Content:Text',
                properties: { text: { translatable: true } },
            },
        };

        expect(
            wrapper.vm.getElementTextValue({
                id: 'element-1',
                component: 'Ct:Content:Text',
                properties: { text: { [GERMAN_LANGUAGE_ID]: 'Hallo' } },
            }),
        ).toBe('');
    });

    it('commits a translatable settings field through update-element-properties', async () => {
        const element = {
            id: 'element-1',
            component: 'Ct:Media:Image',
            properties: {
                caption: {
                    [ANCHOR_LANGUAGE_ID]: 'Caption',
                    [GERMAN_LANGUAGE_ID]: 'Bildunterschrift',
                },
            },
        };
        const updateElementProperties = jest.fn().mockResolvedValue(createMutationResponse([element], ['element-1']));
        const { wrapper } = await createWrapper({
            services: {
                contentSystemLayoutDraftMutationService: { updateElementProperties },
            },
        });
        wrapper.vm.layout = { id: 'layout-1', rootSource: 'blog', layout: [element] };
        wrapper.vm.elementTypeStore.typesByName = {
            'Ct:Media:Image': {
                name: 'Ct:Media:Image',
                properties: { caption: { translatable: true } },
            },
        };

        await wrapper.vm.onElementSettingsChange({
            elementId: 'element-1',
            propertyKey: 'caption',
            value: 'Caption updated',
        });

        expect(updateElementProperties).toHaveBeenCalledWith({
            layout: [element],
            rootSource: 'blog',
            elementId: 'element-1',
            values: {
                caption: {
                    [ANCHOR_LANGUAGE_ID]: 'Caption updated',
                    [GERMAN_LANGUAGE_ID]: 'Bildunterschrift',
                },
            },
        });
    });

    it('clears inline session on cancel for matching element', async () => {
        const { wrapper } = await createWrapper();
        wrapper.vm.layout = {
            id: 'layout-1',
            rootSource: 'blog',
            layout: [
                {
                    id: 'element-1',
                    component: 'content:text',
                    properties: { text: '<p>Before</p>' },
                },
            ],
        };
        wrapper.vm.elementTypeStore.typesByName = {
            'content:text': { name: 'content:text', properties: {} },
        };

        wrapper.vm.onInlineEditStart({ elementId: 'element-1' });
        wrapper.vm.onInlineEditCancel({ elementId: 'element-1' });

        expect(wrapper.vm.inlineEditSession).toBeNull();
    });

    it('uses layout rootSource for draft mutation payloads', async () => {
        const { wrapper } = await createWrapper();
        wrapper.vm.layout = { rootSource: 'blog', layout: [] };

        expect(wrapper.vm.resolveMutationRootSource()).toBe('blog');
    });

    it('returns null rootSource when no rootSource is set', async () => {
        const { wrapper } = await createWrapper();
        wrapper.vm.layout = { rootSource: null, layout: [] };

        expect(wrapper.vm.resolveMutationRootSource()).toBeNull();
    });

    it('creates draft mutation payload with sanitized layout and rootSource', async () => {
        const { wrapper } = await createWrapper();
        wrapper.vm.layout = { rootSource: null, layout: [] };

        const payload = wrapper.vm.createDraftMutationPayload(
            [
                {
                    id: 'element-1',
                    component: 'Ct:Content:Text',
                    properties: {
                        text: 'Hello',
                    },
                },
            ],
            {
                type: 'Ct:Content:Text',
            },
        );

        expect(payload.rootSource).toBeNull();
        expect(payload.layout).toHaveLength(1);
        expect(payload.type).toBe('Ct:Content:Text');
    });

    it('derives preview entity type from layout rootSource', async () => {
        const { wrapper } = await createWrapper();
        const previewContext = wrapper.vm.resolvePreviewContext({
            rootSource: 'blog',
        });

        expect(previewContext).toEqual({
            entityType: 'blog',
            entityId: null,
            channelId: null,
        });
    });

    it('loads first preview entity when assignment does not provide one', async () => {
        const categoryRepository = {
            search: jest.fn().mockResolvedValue(createCollection([{ id: 'entity-1' }])),
        };
        const { wrapper } = await createWrapper({
            repositories: { category: categoryRepository },
        });
        wrapper.vm.layout = { rootSource: 'category', layout: [] };
        wrapper.vm.previewEntityId = null;

        await wrapper.vm.loadDefaultPreviewEntity();

        expect(wrapper.vm.previewEntityId).toBe('entity-1');
    });

    it('initializes a new layout with the requested entity context', async () => {
        const { wrapper } = await createWrapper({
            route: {
                name: 'ct.experience.studio.create',
                params: { id: 'layout-new' },
                query: {
                    rootSource: 'blog',
                    entityId: 'blog-1',
                },
            },
        });

        expect(wrapper.vm.layout).toMatchObject({
            id: 'layout-new',
            rootSource: 'blog',
        });
        expect(wrapper.vm.previewEntityId).toBe('blog-1');
    });

    it('initializes a section layout with the requested Channel preview context', async () => {
        const headerRepository = { search: jest.fn() };
        const { wrapper } = await createWrapper({
            route: {
                name: 'ct.experience.studio.create',
                params: { id: 'layout-new' },
                query: {
                    rootSource: 'header',
                    channelId: 'channel-1',
                },
            },
            repositories: { header: headerRepository },
        });

        expect(wrapper.vm.layout).toMatchObject({
            id: 'layout-new',
            rootSource: 'header',
        });
        expect(wrapper.vm.previewChannelId).toBe('channel-1');
        expect(wrapper.vm.showPreviewEntitySelect).toBe(false);
        expect(headerRepository.search).not.toHaveBeenCalled();
    });

    it('assigns the current layout to the selected entity and channel', async () => {
        const assignment = {};
        const assignmentRepository = {
            search: jest.fn().mockResolvedValue(createCollection([])),
            create: jest.fn().mockReturnValue(assignment),
            save: jest.fn().mockResolvedValue(undefined),
        };
        const initialLayout = {
            id: 'layout-1',
            name: 'Layout',
            rootSource: 'blog',
            layout: [],
            blogContentLayouts: [],
        };
        const reloadedLayout = { ...initialLayout, blogContentLayouts: [assignment] };
        const layoutRepository = {
            get: jest.fn().mockResolvedValueOnce(initialLayout).mockResolvedValueOnce(reloadedLayout),
        };
        const { wrapper } = await createWrapper({
            initialLayout,
            repositories: {
                content_layout: layoutRepository,
                blog_content_layout: assignmentRepository,
            },
        });
        wrapper.vm.previewEntityId = 'blog-1';
        wrapper.vm.previewChannelId = 'channel-1';
        await wrapper.vm.$nextTick();

        await wrapper.vm.onAssignLayout();

        expect(assignment).toEqual({
            blogId: 'blog-1',
            channelId: 'channel-1',
            contentLayoutId: 'layout-1',
        });
        expect(assignmentRepository.save).toHaveBeenCalledWith(assignment, Contena.Context.api);
        expect(wrapper.vm.layout).toEqual(reloadedLayout);
        expect(wrapper.vm.isAssignmentLoading).toBe(false);
    });

    it('removes the current layout assignment', async () => {
        const assignmentRepository = {
            delete: jest.fn().mockResolvedValue(undefined),
        };
        const initialLayout = {
            id: 'layout-1',
            name: 'Layout',
            rootSource: 'category',
            layout: [],
            categoryContentLayouts: [
                {
                    id: 'assignment-1',
                    categoryId: 'category-1',
                    channelId: 'channel-1',
                },
            ],
        };
        const reloadedLayout = { ...initialLayout, categoryContentLayouts: [] };
        const layoutRepository = {
            get: jest.fn().mockResolvedValueOnce(initialLayout).mockResolvedValueOnce(reloadedLayout),
        };
        const { wrapper } = await createWrapper({
            initialLayout,
            repositories: {
                content_layout: layoutRepository,
                category_content_layout: assignmentRepository,
            },
        });
        wrapper.vm.previewEntityId = 'category-1';
        wrapper.vm.previewChannelId = 'channel-1';
        await wrapper.vm.$nextTick();

        await wrapper.vm.onUnassignLayout();

        expect(assignmentRepository.delete).toHaveBeenCalledWith('assignment-1', Contena.Context.api);
        expect(wrapper.vm.layout).toEqual(reloadedLayout);
        expect(wrapper.vm.isAssignmentLoading).toBe(false);
    });

    it('moves element via structural draft mutation', async () => {
        const moveElement = jest.fn().mockResolvedValue(createMutationResponse([], ['element-1']));
        const { wrapper } = await createWrapper({
            services: {
                contentSystemLayoutDraftMutationService: { moveElement },
            },
        });
        wrapper.vm.layout = {
            id: 'layout-1',
            rootSource: 'blog',
            layout: [
                {
                    id: 'parent-1',
                    component: 'Ct:Layout:Container',
                    slots: {
                        main: [
                            { id: 'element-1', component: 'Ct:Content:Text' },
                            { id: 'element-2', component: 'Ct:Content:Text' },
                        ],
                    },
                },
            ],
        };

        await wrapper.vm.onMoveElement({
            elementId: 'element-1',
            newParentElementId: 'parent-1',
            newSlotName: 'main',
            newIndex: 2,
        });

        expect(moveElement).toHaveBeenCalledWith({
            layout: [
                {
                    id: 'parent-1',
                    component: 'Ct:Layout:Container',
                    slots: {
                        main: [
                            { id: 'element-1', component: 'Ct:Content:Text' },
                            { id: 'element-2', component: 'Ct:Content:Text' },
                        ],
                    },
                },
            ],
            rootSource: 'blog',
            elementId: 'element-1',
            newParentId: 'parent-1',
            newSlot: 'main',
            index: 1,
        });
        expect(wrapper.vm.selectedElementId).toBe('element-1');
    });

    it('records history and applies latest successful draft mutation response', async () => {
        const response = createMutationResponse(
            [
                {
                    id: 'element-2',
                    component: 'Ct:Content:Text',
                },
            ],
            ['element-2'],
        );
        const insertElement = jest.fn().mockResolvedValue(response);
        const { wrapper } = await createWrapper({
            services: {
                contentSystemLayoutDraftMutationService: { insertElement },
            },
        });
        const previousLayout = [
            {
                id: 'element-1',
                component: 'Ct:Content:Text',
            },
        ];
        wrapper.vm.layout = {
            id: 'layout-1',
            rootSource: 'blog',
            layout: previousLayout,
        };
        wrapper.vm.selectedElementId = 'element-1';
        const pushToHistory = jest.fn();
        wrapper.vm.editorStore.pushToHistory = pushToHistory;

        await wrapper.vm.executeStructuralDraftMutation(
            'insert',
            previousLayout,
            { type: 'Ct:Content:Text' },
            (mutationResponse: { affectedElementIds: string[] }) => mutationResponse.affectedElementIds[0] ?? null,
        );

        expect(insertElement).toHaveBeenCalledWith({
            layout: previousLayout,
            rootSource: 'blog',
            type: 'Ct:Content:Text',
        });
        expect(pushToHistory).toHaveBeenCalledWith(previousLayout, 'element-1');
        expect(wrapper.vm.selectedElementId).toBe('element-2');
        expect(wrapper.vm.layout.layout).toHaveLength(1);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('ignores stale mutation responses by request id', async () => {
        let resolveFirstRequest!: (value: unknown) => void;
        const insertElement = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveFirstRequest = resolve;
                    }),
            )
            .mockResolvedValueOnce(createMutationResponse([{ id: 'newer', component: 'Ct:Content:Text' }], ['newer']));
        const { wrapper } = await createWrapper({
            services: {
                contentSystemLayoutDraftMutationService: { insertElement },
            },
        });
        wrapper.vm.layout = { id: 'layout-1', rootSource: 'blog', layout: [] };
        wrapper.vm.selectedElementId = 'element-1';

        const firstCall = wrapper.vm.executeStructuralDraftMutation(
            'insert',
            [{ id: 'first', component: 'Ct:Content:Text' }],
            { type: 'Ct:Content:Text' },
            () => 'first',
        );
        const secondCall = wrapper.vm.executeStructuralDraftMutation(
            'insert',
            [{ id: 'second', component: 'Ct:Content:Text' }],
            { type: 'Ct:Content:Text' },
            (response: { affectedElementIds: string[] }) => response.affectedElementIds[0] ?? null,
        );

        await secondCall;
        resolveFirstRequest(createMutationResponse([{ id: 'stale', component: 'Ct:Content:Text' }], ['stale']));
        await firstCall;

        expect(wrapper.vm.layout.layout[0].id).toBe('newer');
    });

    it('stops loading when saving the layout fails', async () => {
        const layoutRepository = {
            get: jest.fn().mockResolvedValue({
                id: 'layout-1',
                name: 'Layout',
                rootSource: 'blog',
                layout: [],
            }),
            save: jest.fn().mockRejectedValue(new Error('Save failed')),
        };
        const { wrapper } = await createWrapper({
            repositories: { content_layout: layoutRepository },
        });

        await expect(wrapper.vm.onSave()).rejects.toThrow('Save failed');

        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('calls move mutation endpoint for move operations', async () => {
        const moveElement = jest.fn().mockResolvedValue(createMutationResponse([]));
        const { wrapper } = await createWrapper({
            services: {
                contentSystemLayoutDraftMutationService: { moveElement },
            },
        });
        wrapper.vm.layout = { id: 'layout-1', rootSource: 'category', layout: [] };

        await wrapper.vm.requestDraftMutation('move', [], {
            elementId: 'element-1',
            newParentId: null,
            newSlot: null,
        });

        expect(moveElement).toHaveBeenCalledWith({
            layout: [],
            rootSource: 'category',
            elementId: 'element-1',
            newParentId: null,
            newSlot: null,
        });
    });

    it('rejects invalid move targets from subtree cycles', async () => {
        const { wrapper } = await createWrapper();
        wrapper.vm.layout = {
            id: 'layout-1',
            rootSource: 'blog',
            layout: [
                {
                    id: 'parent',
                    component: 'Ct:Layout:Container',
                    slots: {
                        main: [
                            {
                                id: 'child',
                                component: 'Ct:Content:Text',
                            },
                        ],
                    },
                },
            ],
        };

        expect(
            wrapper.vm.validateMoveTarget({
                elementId: 'parent',
                newParentElementId: 'child',
                newSlotName: 'main',
                newIndex: 0,
            }),
        ).toBe(false);
    });

    it('adjusts move index when reordering in same slot', async () => {
        const { wrapper } = await createWrapper();
        const layout = [
            {
                id: 'parent',
                component: 'Ct:Layout:Container',
                slots: {
                    main: [
                        { id: 'a', component: 'Ct:Content:Text' },
                        { id: 'b', component: 'Ct:Content:Text' },
                        { id: 'c', component: 'Ct:Content:Text' },
                    ],
                },
            },
        ];

        const normalizedIndex = wrapper.vm.normalizeMoveIndex(layout, {
            elementId: 'a',
            newParentElementId: 'parent',
            newSlotName: 'main',
            newIndex: 2,
        });

        expect(normalizedIndex).toBe(1);
    });
});

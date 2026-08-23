import { defineComponent } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { routeLocationKey, routerKey } from 'vue-router';

const pageStub = defineComponent({
    template: `
        <div>
            <slot name="smart-bar-header" />
            <slot name="smart-bar-actions" />
            <slot name="content" />
        </div>
    `,
});
const cardStub = defineComponent({
    template: '<section><slot name="headerRight" /><slot /><slot name="grid" /></section>',
});
const modalStub = defineComponent({
    template: '<div><slot /><slot name="modal-footer" /></div>',
});

type DetailComponent = {
    dictionary: Entity<'data_dictionary'>;
    items: Entity<'data_dictionary_item'>[];
    selectedItemId: string | null;
    selectedItem: Entity<'data_dictionary_item'> | null;
    treeSourceItems: Array<{ id: string; childCount: number }>;
    addItem: (parent?: Entity<'data_dictionary_item'> | null) => void;
    saveItem: (item: Entity<'data_dictionary_item'>) => void;
    removeItem: (item: Entity<'data_dictionary_item'>) => Promise<void>;
    onTreeDragEnd: (payload: unknown) => void;
    onSave: () => Promise<void>;
};

function createEntity(id: string, overrides: Partial<EntitySchema.data_dictionary_item> = {}) {
    return {
        id,
        dictionaryId: 'dictionary-id',
        code: '',
        label: '',
        position: 1,
        active: true,
        systemLocked: false,
        isNew: jest.fn(() => true),
        ...overrides,
    } as unknown as Entity<'data_dictionary_item'>;
}

describe('module/ct-data-dictionary/page/ct-data-dictionary-detail', () => {
    let wrapper: VueWrapper | null = null;
    let itemSequence = 0;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    async function createWrapper(options: { items?: Entity<'data_dictionary_item'>[]; existing?: boolean } = {}) {
        itemSequence = 0;
        const dictionary = {
            id: 'dictionary-id',
            technicalName: options.existing ? 'example.tree' : '',
            label: options.existing ? 'Example tree' : '',
            active: true,
            isNew: jest.fn(() => !options.existing),
        } as unknown as Entity<'data_dictionary'>;
        const dictionaryRepository = {
            create: jest.fn(() => dictionary),
            get: jest.fn(() => Promise.resolve(dictionary)),
            save: jest.fn(() => Promise.resolve()),
        };
        const itemCollection = new Contena.Data.EntityCollection(
            '/data-dictionary-item',
            'data_dictionary_item',
            Contena.Context.api,
            new Contena.Data.Criteria(1, 500),
            options.items ?? [],
            options.items?.length ?? 0,
        );
        const itemRepository = {
            create: jest.fn(() => createEntity(`new-item-${++itemSequence}`)),
            search: jest.fn(() => Promise.resolve(itemCollection)),
            save: jest.fn(() => Promise.resolve()),
            delete: jest.fn(() => Promise.resolve()),
        };
        const repositoryFactory = {
            create: jest.fn((entityName: string) =>
                entityName === 'data_dictionary' ? dictionaryRepository : itemRepository,
            ),
        };
        const router = { push: jest.fn(() => Promise.resolve()) };

        wrapper = mount(await wrapTestComponent('ct-data-dictionary-detail', { sync: true }), {
            global: {
                provide: {
                    [routeLocationKey as symbol]: {
                        params: options.existing ? { id: 'dictionary-id' } : {},
                    },
                    [routerKey as symbol]: router,
                    repositoryFactory,
                    acl: { can: () => true },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': pageStub,
                    'ct-card-view': defineComponent({ template: '<div><slot /></div>' }),
                    'mt-card': cardStub,
                    'ct-modal': modalStub,
                    'ct-container': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-tree': true,
                    'ct-tree-item': true,
                    'ct-data-dictionary-tree': true,
                    'ct-data-dictionary-item-modal': true,
                    'ct-context-button': true,
                    'ct-context-menu-item': true,
                    'ct-button-process': true,
                    'mt-button': true,
                    'mt-text-field': true,
                    'mt-number-field': true,
                    'mt-textarea': true,
                    'mt-switch': true,
                    'mt-badge': true,
                    'mt-empty-state': true,
                },
            },
        });
        await flushPromises();

        return { wrapper, dictionaryRepository, itemRepository, router };
    }

    it('creates root and child entities with the standard parentId field', async () => {
        const result = await createWrapper();
        const component = result.wrapper.vm as unknown as DetailComponent;

        component.addItem();
        expect(component.items).toHaveLength(0);
        const root = component.selectedItem as Entity<'data_dictionary_item'>;
        root.code = 'root';
        root.label = 'Root';
        component.saveItem(root);
        component.addItem(root);
        const child = component.selectedItem as Entity<'data_dictionary_item'>;
        child.code = 'child';
        child.label = 'Child';
        component.saveItem(child);

        expect(root.parentId).toBeUndefined();
        expect(child.parentId).toBe(root.id);
        expect(component.treeSourceItems.find((item) => item.id === root.id)?.childCount).toBe(1);
        expect(component.selectedItemId).toBe(child.id);
    });

    it('keeps the value workspace for the second step after dictionary creation', async () => {
        const result = await createWrapper();

        expect(result.wrapper.findComponent({ name: 'ct-tree' }).exists()).toBe(false);
        expect(result.wrapper.text()).not.toContain('字典值结构');
    });

    it('deletes a persisted branch child-first and removes it from the editor', async () => {
        const root = createEntity('root', { code: 'root', label: 'Root' });
        const child = createEntity('child', { parentId: 'root', code: 'child', label: 'Child' });
        root.isNew = jest.fn(() => false);
        child.isNew = jest.fn(() => false);
        const result = await createWrapper({
            items: [
                root,
                child,
            ],
            existing: true,
        });
        const component = result.wrapper.vm as unknown as DetailComponent;

        await component.removeItem(root);

        expect(result.itemRepository.delete.mock.calls).toEqual([
            ['child'],
            ['root'],
        ]);
        expect(component.items).toHaveLength(0);
    });

    it('shows editable dictionary settings directly for an existing dictionary', async () => {
        const result = await createWrapper({ existing: true });

        expect(result.wrapper.find('[name="sw_data_dictionary_detail_settings_card"]').exists()).toBe(true);
        expect(result.wrapper.find('.ct-data-dictionary-detail__tree-card').exists()).toBe(true);
    });

    it('saves parents before children so new hierarchy references are valid', async () => {
        const result = await createWrapper();
        const component = result.wrapper.vm as unknown as DetailComponent;
        component.dictionary.technicalName = 'example.tree';
        component.dictionary.label = 'Example tree';
        component.addItem();
        const root = component.selectedItem as Entity<'data_dictionary_item'>;
        root.code = 'root';
        root.label = 'Root';
        component.saveItem(root);
        component.addItem(root);
        const child = component.selectedItem as Entity<'data_dictionary_item'>;
        child.code = 'child';
        child.label = 'Child';
        component.saveItem(child);

        await component.onSave();

        const savedItems = result.itemRepository.save.mock.calls as unknown as Array<[Entity<'data_dictionary_item'>]>;
        expect(savedItems.map(([item]) => item.id)).toEqual([
            root.id,
            child.id,
        ]);
        expect(result.router.push).toHaveBeenCalledWith({ name: 'ct.data.dictionary.index' });
    });

    it('persists a dragged node parent and sibling order in the local tree state', async () => {
        const root = createEntity('root', { code: 'root', label: 'Root', position: 1 });
        const child = createEntity('child', { code: 'child', label: 'Child', parentId: 'root', position: 1 });
        const result = await createWrapper({
            items: [
                root,
                child,
            ],
            existing: true,
        });
        const component = result.wrapper.vm as unknown as DetailComponent;

        component.onTreeDragEnd({
            draggedItem: { data: { id: 'child', afterId: 'root' } },
            oldParentId: 'root',
            newParentId: null,
        });

        expect(child.parentId).toBeNull();
        expect(child.position).toBe(2);
    });
});

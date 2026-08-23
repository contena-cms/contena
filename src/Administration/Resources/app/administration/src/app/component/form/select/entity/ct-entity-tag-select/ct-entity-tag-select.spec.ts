import { mount } from '@vue/test-utils';

import EntityCollection from 'src/core/data/entity-collection.data';

type TagSelectVm = {
    $nextTick: () => Promise<void>;
    addItem: (tag: { id: string; name: string }) => void;
    createNewTag: () => Promise<void>;
    resultCollection: EntityCollection<'tag'>;
    search: (term: string) => Promise<void>;
    searchTerm: string;
};

type EmittedTagCollection = {
    getIds: () => string[];
};

const createTagCollection = (tags = []) =>
    new EntityCollection('/tag', 'tag', Contena.Context.api, new Contena.Data.Criteria(1, 25), tags, tags.length);

async function createWrapper() {
    const repository = {
        create: jest.fn((context: object, id: string | number = 'created-tag-id') => ({ id, name: '', context })),
        save: jest.fn(() => Promise.resolve()),
        search: jest.fn(() => Promise.resolve(createTagCollection())),
    };

    const wrapper = mount(await wrapTestComponent('ct-entity-tag-select', { sync: true }), {
        global: {
            stubs: {
                'ct-select-base': await wrapTestComponent('ct-select-base', { sync: true }),
                'ct-block-field': await wrapTestComponent('ct-block-field', { sync: true }),
                'ct-base-field': await wrapTestComponent('ct-base-field', { sync: true }),
                'ct-select-selection-list': await wrapTestComponent('ct-select-selection-list', { sync: true }),
                'ct-field-error': await wrapTestComponent('ct-field-error', { sync: true }),
                'ct-loader': await wrapTestComponent('ct-loader', { sync: true }),
                'ct-select-result-list': await wrapTestComponent('ct-select-result-list', { sync: true }),
                'ct-select-result': await wrapTestComponent('ct-select-result', { sync: true }),
                'ct-highlight-text': await wrapTestComponent('ct-highlight-text', { sync: true }),
                'ct-label': await wrapTestComponent('ct-label', { sync: true }),
                'ct-inheritance-switch': true,
                'ct-ai-copilot-badge': true,
                'ct-help-text': true,
                'ct-color-badge': true,
                'mt-loader': true,
                'mt-floating-ui': {
                    template: '<div><slot /></div>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => repository,
                },
            },
        },
        props: {
            entityCollection: createTagCollection(),
        },
    });

    await flushPromises();

    return { wrapper, repository };
}

describe('components/ct-entity-tag-select', () => {
    it('inherits the entity multi-select SFC', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.ct-entity-multi-select').exists()).toBe(true);

        wrapper.unmount();
    });

    it('adds an existing tag to the DAL association collection', async () => {
        const { wrapper } = await createWrapper();
        const vm = wrapper.vm as unknown as TagSelectVm;
        const tag = { id: 'existing-tag-id', name: 'Existing tag' };

        vm.addItem(tag);
        await vm.$nextTick();

        const emittedCollection = wrapper.emitted('update:entityCollection')?.[0]?.[0] as EmittedTagCollection;
        expect(Array.from(emittedCollection.getIds())).toEqual(['existing-tag-id']);

        wrapper.unmount();
    });

    it('offers the upstream create option when no tag matches the search term', async () => {
        const { wrapper } = await createWrapper();
        const vm = wrapper.vm as unknown as TagSelectVm;

        vm.resultCollection = createTagCollection();
        vm.searchTerm = 'New tag';
        await vm.search('New tag');

        expect(vm.resultCollection.at(0)).toEqual(
            expect.objectContaining({
                id: -1,
            }),
        );

        wrapper.unmount();
    });

    it('creates a tag through the repository and adds it to the DAL association collection', async () => {
        const { wrapper, repository } = await createWrapper();
        const vm = wrapper.vm as unknown as TagSelectVm;

        vm.searchTerm = 'New tag';
        await vm.createNewTag();

        expect(repository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                id: 'created-tag-id',
                name: 'New tag',
            }),
            Contena.Context.api,
        );
        const emittedCollection = wrapper.emitted('update:entityCollection')?.[0]?.[0] as EmittedTagCollection;
        expect(Array.from(emittedCollection.getIds())).toEqual(['created-tag-id']);

        wrapper.unmount();
    });
});

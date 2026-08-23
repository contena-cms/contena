import { defineComponent, toRaw } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { routerKey } from 'vue-router';

const pageStub = defineComponent({
    template: `
        <div>
            <slot name="search-bar" />
            <slot name="smart-bar-header" />
            <slot name="smart-bar-actions" />
            <slot name="language-switch" />
            <slot name="content" />
        </div>
    `,
});
const tableStub = defineComponent({
    name: 'MtDataTable',
    inheritAttrs: false,
    props: [
        'dataSource',
        'columns',
        'disableDelete',
        'additionalContextButtons',
    ],
    template: `<div class="test-table"><table><thead><tr><th>Label</th></tr></thead><tbody><tr v-for="item in dataSource" :key="item.id"><td>{{ item.label }}</td></tr></tbody></table><slot v-if="!dataSource?.length" name="empty-state" /></div>`,
});

describe('module/ct-data-dictionary/page/ct-data-dictionary-list', () => {
    let wrapper: VueWrapper | null = null;

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    async function createWrapper(items: Entity<'data_dictionary'>[]) {
        const criteria = new Contena.Data.Criteria(1, 25);
        const result = new Contena.Data.EntityCollection(
            '/data-dictionary',
            'data_dictionary',
            Contena.Context.api,
            criteria,
            items,
            items.length,
        );
        const repository = {
            search: jest.fn((requestedCriteria: InstanceType<typeof Contena.Data.Criteria>) => {
                void requestedCriteria;

                return Promise.resolve(result);
            }),
            delete: jest.fn(() => Promise.resolve()),
        };
        const repositoryFactory = {
            create: jest.fn(() => repository),
        };

        wrapper = mount(await wrapTestComponent('ct-data-dictionary-list', { sync: true }), {
            global: {
                provide: {
                    [routerKey as symbol]: { push: jest.fn() },
                    repositoryFactory,
                    acl: { can: () => true },
                },
                stubs: {
                    'ct-block': defineComponent({ template: '<div><slot /></div>' }),
                    'ct-page': pageStub,
                    'mt-data-table': tableStub,
                    'ct-data-dictionary-detail': true,
                    'ct-search-bar': true,
                    'ct-language-switch': true,
                    'mt-button': true,
                    'mt-icon': true,
                    'ct-modal': true,
                    'mt-empty-state': defineComponent({
                        props: [
                            'headline',
                            'description',
                        ],
                        template: '<div class="test-empty-state">{{ headline }} {{ description }}</div>',
                    }),
                },
            },
        });
        await flushPromises();

        return { wrapper, repository, result };
    }

    it('keeps the repository EntityCollection and renders its records', async () => {
        const dictionary = {
            id: 'dictionary-id',
            technicalName: 'core.gender',
            label: 'Gender',
            active: true,
        } as unknown as Entity<'data_dictionary'>;
        const result = await createWrapper([dictionary]);
        const component = result.wrapper.vm as unknown as {
            dictionaries: EntityCollection<'data_dictionary'>;
        };

        expect(toRaw(component.dictionaries)).toBe(result.result);
        expect(toRaw(result.wrapper.findComponent(tableStub).props('dataSource'))).toBe(result.result);
        expect(result.wrapper.find('tbody').text()).toContain('Gender');
        expect(result.wrapper.find('.test-empty-state').exists()).toBe(false);
    });

    it('passes snippet keys to the listing so headers follow the active locale', async () => {
        const result = await createWrapper([]);
        const component = result.wrapper.vm as unknown as {
            columns: Array<{ property: string; label: string }>;
        };

        expect(component.columns).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    property: 'label',
                    label: 'ct-data-dictionary.list.columns.label',
                }),
                expect.objectContaining({
                    property: 'technicalName',
                    label: 'ct-data-dictionary.list.columns.technicalName',
                }),
                expect.objectContaining({
                    property: 'active',
                    label: 'ct-data-dictionary.list.columns.active',
                }),
            ]),
        );
    });

    it('reloads dictionary labels when the administration language changes', async () => {
        const result = await createWrapper([]);
        const component = result.wrapper.vm as unknown as { onChangeLanguage: () => void };
        const initialSearchCalls = result.repository.search.mock.calls.length;

        component.onChangeLanguage();
        await flushPromises();

        expect(result.repository.search.mock.calls).toHaveLength(initialSearchCalls + 1);
    });

    it('deletes a dictionary through the explicit actions flow', async () => {
        const dictionary = {
            id: 'dictionary-id',
            technicalName: 'core.gender',
            label: 'Gender',
            active: true,
        } as unknown as Entity<'data_dictionary'>;
        const result = await createWrapper([dictionary]);
        const component = result.wrapper.vm as unknown as {
            showDelete: (item: Entity<'data_dictionary'>) => void;
            confirmDelete: () => Promise<void>;
            itemToDelete: Entity<'data_dictionary'> | null;
        };

        component.showDelete(dictionary);
        expect(component.itemToDelete).toEqual(dictionary);
        await component.confirmDelete();

        expect(result.repository.delete).toHaveBeenCalledWith('dictionary-id');
        expect(component.itemToDelete).toBeNull();
    });

    it('keeps the table header visible and appends an empty-state message when no records exist', async () => {
        const result = await createWrapper([]);

        expect(result.wrapper.find('thead').exists()).toBe(true);
        expect(result.wrapper.find('.test-empty-state').exists()).toBe(true);
    });

    it('opens the independent dictionary modal for create and edit actions', async () => {
        const dictionary = {
            id: 'dictionary-id',
            technicalName: 'core.gender',
            label: 'Gender',
            active: true,
        } as unknown as Entity<'data_dictionary'>;
        const result = await createWrapper([dictionary]);
        const component = result.wrapper.vm as unknown as {
            onCreate: () => void;
            onEdit: (item: Entity<'data_dictionary'>) => void;
            isDetailModalOpen: boolean;
            editingDictionaryId: string | null;
        };

        component.onCreate();
        expect(component.isDetailModalOpen).toBe(true);
        expect(component.editingDictionaryId).toBeNull();

        component.onEdit(dictionary);
        expect(component.isDetailModalOpen).toBe(true);
        expect(component.editingDictionaryId).toBe('dictionary-id');
    });

    it('loads the requested page and page size from the listing event', async () => {
        const result = await createWrapper([]);

        const component = result.wrapper.vm as unknown as {
            onPageChange: (options: { page: number; limit: number }) => void;
        };
        component.onPageChange({ page: 3, limit: 50 });
        await flushPromises();

        const requestedCriteria = result.repository.search.mock.calls[1][0];
        expect(requestedCriteria.page).toBe(3);
        expect(requestedCriteria.limit).toBe(50);
    });
});

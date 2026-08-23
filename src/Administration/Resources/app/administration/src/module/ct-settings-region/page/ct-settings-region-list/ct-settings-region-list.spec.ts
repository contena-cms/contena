/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access */
import { mount } from '@vue/test-utils';

function collection<T>(items: T[]) {
    return Object.assign(items, {
        first: () => items[0] ?? null,
        total: items.length,
    });
}

async function createWrapper(privileges: string[] = []) {
    const countryRepository = {
        search: jest.fn(() => Promise.resolve(collection([{ id: 'china-id', iso: 'CN' }]))),
    };
    const createdRegion = {
        id: 'new-region-id',
        name: '',
        type: '',
        countryId: null as string | null,
        parentId: null as string | null,
        position: 0,
        active: false,
        isNew: () => true,
    };
    const persistedRegion = {
        id: 'province-id',
        name: 'Guangdong',
        type: 'province',
        isNew: () => false,
    };
    const regionRepository = {
        search: jest.fn(() =>
            Promise.resolve(
                collection([
                    {
                        id: 'province-id',
                        countryId: 'china-id',
                        parentId: null,
                        childCount: 2,
                        position: 1,
                        name: 'Guangdong',
                        translated: { name: 'Guangdong' },
                    },
                ]),
            ),
        ),
        create: jest.fn(() => createdRegion),
        get: jest.fn(() => Promise.resolve(persistedRegion)),
        save: jest.fn(),
        syncDeleted: jest.fn(),
    };
    const wrapper = mount(await wrapTestComponent('ct-settings-region-list', { sync: true }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: (entityName: string) => (entityName === 'country' ? countryRepository : regionRepository),
                },
                acl: {
                    can: (privilege: string) => privileges.includes(privilege),
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: jest.fn(() => Promise.resolve([{ id: 'region-fields' }])),
                },
            },
            mocks: {
                $t: (key: string) => key,
            },
            stubs: {
                'ct-page': {
                    template:
                        '<div><slot name="search-bar" /><slot name="language-switch" /><slot name="smart-bar-actions" /><slot name="content" /><slot /></div>',
                },
                'ct-card-view': { template: '<div><slot /></div>' },
                'mt-card': { template: '<div><slot /><slot name="grid" /></div>' },
                'mt-entity-select': true,
                'ct-search-bar': true,
                'ct-language-switch': true,
                'ct-region-tree': true,
                'ct-region-form': true,
                'mt-empty-state': true,
            },
        },
    });

    await flushPromises();
    return { wrapper: wrapper as any, countryRepository, regionRepository, createdRegion };
}

describe('module/ct-settings-region/page/ct-settings-region-list', () => {
    it('selects China and loads only its root Regions initially', async () => {
        const { wrapper, countryRepository, regionRepository } = await createWrapper(['region.viewer']);

        expect(countryRepository.search).toHaveBeenCalled();
        expect(wrapper.vm.selectedCountryId).toBe('china-id');
        expect(regionRepository.search).toHaveBeenCalledTimes(1);
        const criteria = (regionRepository.search as jest.Mock).mock.calls[0]?.[0];
        expect(criteria.filters).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ field: 'countryId', value: 'china-id' }),
                expect.objectContaining({ field: 'parentId', value: null }),
            ]),
        );
        expect(wrapper.vm.treeItems[0].childCount).toBe(2);
    });

    it('creates a root Region for the selected country', async () => {
        const { wrapper, regionRepository, createdRegion } = await createWrapper(['region.creator']);

        wrapper.vm.onAddRegion();
        await wrapper.vm.$nextTick();

        expect(regionRepository.create).toHaveBeenCalledWith(Contena.Context.api);
        expect(createdRegion).toMatchObject({
            countryId: 'china-id',
            parentId: null,
            type: 'region',
            active: true,
        });
        expect(wrapper.vm.currentRegion).toMatchObject(createdRegion);
        expect(wrapper.findComponent({ name: 'ct-region-form' }).exists()).toBe(true);
    });

    it('loads a selected Region into the inline form', async () => {
        const { wrapper, regionRepository } = await createWrapper([
            'region.viewer',
            'region.editor',
        ]);

        await wrapper.vm.onSelectTreeRegion({ id: 'province-id', data: { id: 'province-id' } });

        expect(regionRepository.get).toHaveBeenCalledWith('province-id', Contena.Context.api);
        expect(wrapper.vm.currentRegion.name).toBe('Guangdong');

        wrapper.vm.onUpdateRegion('name', 'Guangdong Province');
        expect(wrapper.vm.currentRegion.name).toBe('Guangdong Province');
    });

    it('links tree create and delete actions to the inline editor and repository', async () => {
        const { wrapper, regionRepository, createdRegion } = await createWrapper([
            'region.creator',
            'region.deleter',
        ]);
        const treeItem = { id: 'province-id', data: { id: 'province-id' } };

        wrapper.vm.onAddChildRegion(treeItem);

        expect(createdRegion.parentId).toBe('province-id');
        expect(wrapper.vm.currentRegion).toMatchObject(createdRegion);

        await wrapper.vm.onDeleteTreeRegion(treeItem);

        expect(regionRepository.syncDeleted).toHaveBeenCalledWith(['province-id'], Contena.Context.api);
        expect(wrapper.vm.currentRegion).toBeNull();
    });

    it('normalizes the tree selection before batch deletion', async () => {
        const { wrapper, regionRepository } = await createWrapper(['region.deleter']);

        await wrapper.vm.onBatchDeleteTreeRegions([
            'province-id',
            { id: 'city-id' },
            { data: { id: 'district-id' } },
        ]);

        expect(regionRepository.syncDeleted).toHaveBeenCalledWith(
            [
                'province-id',
                'city-id',
                'district-id',
            ],
            Contena.Context.api,
        );
    });

    it('keeps Region write actions behind Region privileges', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.canCreate).toBe(false);
        expect(wrapper.vm.canEdit).toBe(false);
        expect(wrapper.vm.canDelete).toBe(false);
        expect(wrapper.find('.ct-settings-region-list__add-root-action').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.ct-settings-region-list__save-action').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.ct-settings-region-list__add-child-action').exists()).toBe(false);
        expect(wrapper.find('.ct-settings-region-list__delete-action').exists()).toBe(false);
    });
});

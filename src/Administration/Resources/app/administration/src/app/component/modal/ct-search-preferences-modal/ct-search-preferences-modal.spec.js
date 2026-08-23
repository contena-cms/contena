import { mount } from '@vue/test-utils';
import { routerKey } from 'vue-router';

async function createWrapper() {
    return mount(await wrapTestComponent('ct-search-preferences-modal', { sync: true }), {
        global: {
            stubs: {
                'ct-loader': true,
                'ct-data-grid': true,
                'router-link': true,
            },
            provide: {
                [routerKey]: {
                    push: jest.fn(),
                },
                searchPreferencesService: {
                    getDefaultSearchPreferences: () => [],
                    getUserSearchPreferences: () => Promise.resolve(null),
                    processSearchPreferences: () => [],
                    processSearchPreferencesFields: () => ({}),
                    createUserSearchPreferences: () => {
                        return {
                            key: 'search.preferences',
                            userId: 'userId',
                        };
                    },
                },
                searchRankingService: {
                    clearCacheUserSearchConfiguration: () => {},
                },
                userConfigService: {
                    upsert: () => {
                        return Promise.resolve();
                    },
                    search: () => {
                        return Promise.resolve();
                    },
                },
            },
        },
    });
}

describe('src/app/component/modal/ct-search-preferences-modal', () => {
    let wrapper;

    beforeEach(async () => {
        Contena.Application.view.deleteReactive = () => {};
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('should get data source once component created', async () => {
        wrapper.vm.searchPreferencesService.getUserSearchPreferences = jest.fn(() => Promise.resolve(null));

        await wrapper.vm.createdComponent();

        expect(wrapper.vm.searchPreferencesService.getUserSearchPreferences).toHaveBeenCalledTimes(1);
    });

    it('should be able to turn off modal', async () => {
        await wrapper.find('.ct-search-preferences-modal__button-cancel').trigger('click');

        expect(wrapper.emitted()['modal-close']).toBeTruthy();
    });

    it('should call to user config service when saving changes', async () => {
        wrapper.vm.userConfigService.upsert = jest.fn(() => Promise.resolve());

        await wrapper.find('.ct-search-preferences-modal__button-save').trigger('click');

        expect(wrapper.vm.userConfigService.upsert).toHaveBeenCalledTimes(1);

        wrapper.vm.userConfigService.upsert.mockRestore();
    });

    it('should be able to change search preference', async () => {
        wrapper.vm.searchPreferences = [
            {
                entityName: 'product',
                _searchable: false,
                fields: [
                    {
                        fieldName: 'name',
                        _searchable: false,
                    },
                    {
                        fieldName: 'productNumber',
                        _searchable: false,
                    },
                ],
            },
        ];
        await wrapper.vm.$nextTick();

        wrapper.vm.searchPreferences[0]._searchable = true;
        wrapper.vm.onChangeSearchPreference(wrapper.vm.searchPreferences[0]);

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: 'product',
                    _searchable: true,
                    fields: expect.arrayContaining([
                        expect.objectContaining({
                            fieldName: 'name',
                            _searchable: true,
                        }),
                        expect.objectContaining({
                            fieldName: 'productNumber',
                            _searchable: true,
                        }),
                    ]),
                }),
            ]),
        );
    });

    it('should not be able to change search preference', async () => {
        wrapper.vm.searchPreferences = [
            {
                entityName: 'product',
                _searchable: false,
                fields: [
                    {
                        fieldName: 'name',
                        _searchable: true,
                    },
                    {
                        fieldName: 'productNumber',
                        _searchable: false,
                    },
                ],
            },
        ];
        await wrapper.vm.$nextTick();

        wrapper.vm.searchPreferences[0]._searchable = true;
        wrapper.vm.onChangeSearchPreference(wrapper.vm.searchPreferences[0]);

        expect(wrapper.vm.searchPreferences).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: 'product',
                    _searchable: true,
                    fields: expect.arrayContaining([
                        expect.objectContaining({
                            fieldName: 'name',
                            _searchable: true,
                        }),
                        expect.objectContaining({
                            fieldName: 'productNumber',
                            _searchable: false,
                        }),
                    ]),
                }),
            ]),
        );
    });
});

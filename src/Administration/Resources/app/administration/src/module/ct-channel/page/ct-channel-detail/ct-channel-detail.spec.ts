import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';
import { routeLocationKey, routerKey } from 'vue-router';
import component from './index';

type DetailVm = {
    channel: Record<string, unknown> | null;
    tabs: Array<{ name: string; onClick: () => void }>;
    onSave: () => Promise<boolean>;
    abortOnLanguageChange: () => boolean;
};

describe('ct-channel-detail', () => {
    function createWrapper(createMode = false) {
        const channel = {
            id: 'channel-id',
            name: 'Frontend',
            typeId: 'frontend-type',
            languageId: 'language-id',
            currencyId: 'currency-id',
            countryId: 'country-id',
            memberGroupId: 'member-group-id',
            navigationCategoryId: 'category-id',
            accessKey: 'access-key',
            translated: { name: 'Frontend' },
        };
        const get = jest.fn<Promise<typeof channel>, [string, unknown, InstanceType<typeof Contena.Data.Criteria>?]>(() =>
            Promise.resolve(channel),
        );
        const create = jest.fn(() => ({
            ...channel,
            id: 'new-channel-id',
            languages: new Contena.Data.EntityCollection('/language', 'language', Contena.Context.api),
            currencies: new Contena.Data.EntityCollection('/currency', 'currency', Contena.Context.api),
        }));
        const save = jest.fn(() => Promise.resolve());
        const hasChanges = jest.fn(() => true);
        const repositoryFactory = {
            create: jest.fn((entity: string) =>
                entity === 'channel'
                    ? { get, create, save, hasChanges }
                    : { get: jest.fn((id: string) => Promise.resolve({ id })) },
            ),
        };
        const router = { push: jest.fn(), replace: jest.fn(() => Promise.resolve()) };
        const route = reactive({
            name: createMode ? 'ct.channel.create.base' : 'ct.channel.detail.base',
            params: createMode ? { typeId: 'frontend-type' } : { id: 'channel-id' },
        });
        const wrapper = shallowMount(component, {
            props: { createMode },
            global: {
                provide: {
                    repositoryFactory,
                    acl: { can: jest.fn(() => true) },
                    channelService: { generateKey: jest.fn(() => Promise.resolve({ accessKey: 'generated-key' })) },
                    [routeLocationKey]: route,
                    [routerKey]: router,
                },
                stubs: { 'ct-block': true, 'ct-page': true, 'mt-button': true, 'mt-loader': true, 'router-view': true },
            },
        });

        return {
            wrapper: wrapper as unknown as VueWrapper<DetailVm>,
            get,
            create,
            save,
            hasChanges,
            router,
            route,
            repositoryFactory,
        };
    }

    beforeEach(() => {
        Contena.Context.app.systemCurrencyId = 'currency-id' as EntityKey<'currency'>;
    });

    it('loads the upstream generic association set for an existing Channel', async () => {
        const { wrapper, get } = createWrapper();
        await flushPromises();

        const criteria = get.mock.calls[0]?.[2];
        expect(criteria?.associations.map(({ association }) => association)).toEqual(
            expect.arrayContaining([
                'type',
                'languages',
                'countries',
                'currencies',
                'domains',
                'themes',
            ]),
        );
        expect(wrapper.vm.channel?.name).toBe('Frontend');
        const parsedCriteria = criteria?.parse();
        const associations = parsedCriteria?.associations ?? {};
        expect(associations.languages?.sort?.[0]).toEqual({
            field: 'name',
            order: 'ASC',
            naturalSorting: false,
        });
        expect(associations.languages?.filter).toEqual([
            { type: 'equals', field: 'active', value: true },
        ]);
        expect(associations.countries?.sort?.[0]).toEqual({
            field: 'name',
            order: 'ASC',
            naturalSorting: false,
        });
    });

    it('clears the previous Channel while loading another Channel from the route', async () => {
        const { wrapper, get, route } = createWrapper();
        await flushPromises();

        let resolveChannel: (channel: {
            id: string;
            name: string;
            typeId: string;
            languageId: string;
            currencyId: string;
            countryId: string;
            memberGroupId: string;
            navigationCategoryId: string;
            accessKey: string;
            translated: { name: string };
        }) => void = () => {};
        get.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveChannel = resolve;
                }),
        );

        route.params = { id: 'api-channel-id' };
        await nextTick();

        expect(wrapper.vm.channel).toBeNull();

        resolveChannel({
            id: 'api-channel-id',
            name: 'API',
            typeId: 'api-type',
            languageId: 'language-id',
            currencyId: 'currency-id',
            countryId: 'country-id',
            memberGroupId: 'member-group-id',
            navigationCategoryId: 'category-id',
            accessKey: 'access-key',
            translated: { name: 'API' },
        });
        await flushPromises();

        expect(wrapper.vm.channel).toMatchObject({ id: 'api-channel-id', name: 'API' });
    });

    it('persists and reloads an existing Channel', async () => {
        const { wrapper, save, get } = createWrapper();
        const eventSpy = jest.spyOn(Contena.Utils.EventBus, 'emit');
        await flushPromises();

        await wrapper.vm.onSave();

        expect(save).toHaveBeenCalledWith(expect.objectContaining({ id: 'channel-id' }), Contena.Context.api);
        expect(eventSpy).toHaveBeenCalledWith('ct-channel-detail-channel-change');
        expect(get).toHaveBeenCalledTimes(2);

        eventSpy.mockRestore();
    });

    it('creates the Channel repository with Sync enabled', async () => {
        const { repositoryFactory } = createWrapper();
        await flushPromises();

        expect(repositoryFactory.create).toHaveBeenCalledWith('channel', undefined, { useSync: true });
    });

    it('creates a Channel with its selected type, default currency, and generated access key', async () => {
        const { wrapper, create } = createWrapper(true);
        await flushPromises();

        expect(create).toHaveBeenCalled();
        expect(wrapper.vm.channel).toMatchObject({
            typeId: 'frontend-type',
            currencyId: 'currency-id',
            accessKey: 'generated-key',
            active: false,
        });
        expect((wrapper.vm.channel?.currencies as EntityCollection<'currency'>).has('currency-id')).toBe(true);
    });

    it('uses Meteor route tabs and navigates when a tab is clicked', async () => {
        const { wrapper, router } = createWrapper();
        await flushPromises();

        expect(wrapper.vm.tabs.map((tab) => tab.name)).toEqual([
            'ct.channel.detail.base',
            'ct.channel.detail.theme',
        ]);

        wrapper.vm.tabs[1]?.onClick();

        expect(router.push).toHaveBeenCalledWith({ name: 'ct.channel.detail.theme', params: { id: 'channel-id' } });
    });

    it('reports repository changes to the language switch', async () => {
        const { wrapper, hasChanges } = createWrapper();
        await flushPromises();

        expect(wrapper.vm.abortOnLanguageChange()).toBe(true);
        expect(hasChanges).toHaveBeenCalledWith(expect.objectContaining({ id: 'channel-id' }));
    });
});

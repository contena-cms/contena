/* eslint-disable ct-deprecation-rules/private-feature-declarations */
import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { routerKey } from 'vue-router';
import component from './index';
import type ChannelFavoritesService from '../../service/channel-favorites.service';

type ChannelListVm = {
    columns: Array<{ property: string }>;
    additionalContextButtons: Array<{ key: string }>;
    channelToDelete: Entity<'channel'> | null;
    isFavorite: (channelId: string) => boolean;
    deleteChannel: () => Promise<void>;
};

describe('ct-channel-list', () => {
    const channel = {
        id: 'channel-id',
        name: 'Frontend',
        translated: { name: 'Frontend' },
        active: true,
        maintenance: false,
        type: { name: 'Frontend', translated: { name: 'Frontend' } },
        domains: [],
    } as unknown as Entity<'channel'>;
    const favorites = {
        isFavorite: jest.fn((channelId: string) => channelId === channel.id),
        update: jest.fn(() => Promise.resolve()),
        refresh: jest.fn(),
    };

    beforeAll(() => {
        Contena.Service().register('channelFavorites', () => favorites as unknown as ChannelFavoritesService);
    });

    function createWrapper() {
        const result = Object.assign([channel], { total: 1 });
        const search = jest.fn(() => Promise.resolve(result));
        const remove = jest.fn(() => Promise.resolve());
        const wrapper = shallowMount(component, {
            global: {
                provide: {
                    repositoryFactory: { create: () => ({ search, delete: remove }) },
                    acl: { can: () => true },
                    [routerKey]: { push: jest.fn() },
                },
                stubs: {
                    'ct-block': true,
                    'ct-page': true,
                    'mt-button': true,
                    'mt-data-table': true,
                    'mt-modal-root': true,
                },
            },
        }) as unknown as VueWrapper<ChannelListVm>;

        return { wrapper, remove };
    }

    beforeEach(() => {
        favorites.isFavorite.mockClear();
        favorites.refresh.mockClear();
    });

    it('adds the upstream favourites column and reads the Channel favourite state', async () => {
        const { wrapper } = createWrapper();
        await flushPromises();

        expect(wrapper.vm.columns).toContainEqual(expect.objectContaining({ property: 'favorite' }));
        expect(wrapper.vm.columns).not.toContainEqual(expect.objectContaining({ property: 'actions' }));
        expect(wrapper.vm.additionalContextButtons).toEqual([
            { key: 'openFrontend', label: 'ct-channel.general.tooltipOpenFrontend' },
            { key: 'edit', label: 'global.default.edit' },
        ]);
        expect(wrapper.vm.isFavorite(channel.id)).toBe(true);
        expect(favorites.isFavorite).toHaveBeenCalledWith(channel.id);
    });

    it('refreshes favourites and the sidebar after deleting a Channel', async () => {
        const eventSpy = jest.spyOn(Contena.Utils.EventBus, 'emit');
        const { wrapper, remove } = createWrapper();
        await flushPromises();
        wrapper.vm.channelToDelete = channel;

        await wrapper.vm.deleteChannel();

        expect(remove).toHaveBeenCalledWith(channel.id, Contena.Context.api);
        expect(favorites.refresh).toHaveBeenCalled();
        expect(eventSpy).toHaveBeenCalledWith('ct-channel-detail-channel-change');

        eventSpy.mockRestore();
    });
});

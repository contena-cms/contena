import UserConfigClass from 'src/core/service/support/user-config.class';
import ChannelFavoritesService from './channel-favorites.service';

describe('module/ct-channel/service/channel-favorites.service', () => {
    let saveUserConfig: jest.SpyInstance;
    let service: ChannelFavoritesService;

    beforeEach(() => {
        saveUserConfig = jest
            .spyOn(UserConfigClass.prototype as unknown as { saveUserConfig: () => Promise<void> }, 'saveUserConfig')
            .mockResolvedValue();
        service = new ChannelFavoritesService();
    });

    afterEach(() => {
        saveUserConfig.mockRestore();
    });

    it('adds a Channel to the favorites and persists the user configuration', async () => {
        await service.update(true, 'channel-1');

        expect(service.getFavoriteIds()).toEqual(['channel-1']);
        expect(service.isFavorite('channel-1')).toBe(true);
        expect(saveUserConfig).toHaveBeenCalled();
    });

    it('removes an existing Channel from the favorites', async () => {
        await service.update(true, 'channel-1');
        await service.update(false, 'channel-1');

        expect(service.getFavoriteIds()).toEqual([]);
        expect(service.isFavorite('channel-1')).toBe(false);
    });

    it('does not add duplicate favorites', async () => {
        await service.update(true, 'channel-1');
        await service.update(true, 'channel-1');

        expect(service.getFavoriteIds()).toEqual(['channel-1']);
    });
});

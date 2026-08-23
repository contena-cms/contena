import { reactive } from 'vue';
import type { SubContainer } from 'src/global.types';
import UserConfigClass from 'src/core/service/support/user-config.class';

class ChannelFavoritesService extends UserConfigClass {
    public static readonly USER_CONFIG_KEY = 'channel-favorites';

    private state: { favorites: string[] } = reactive({ favorites: [] });

    public async initService(): Promise<void> {
        this.userConfig = await this.getUserConfig();

        if (Array.isArray(this.userConfig?.value) && this.userConfig.value.length > 0) {
            this.state.favorites = this.userConfig.value as string[];
        }
    }

    public getFavoriteIds(): string[] {
        return this.state.favorites;
    }

    public isFavorite(channelId: string): boolean {
        return this.state.favorites.includes(channelId);
    }

    public update(state: boolean, channelId: string): Promise<void> {
        if (state && !this.isFavorite(channelId)) {
            this.state.favorites.push(channelId);
        } else if (!state && this.isFavorite(channelId)) {
            const index = this.state.favorites.indexOf(channelId);

            this.state.favorites.splice(index, 1);
        }

        return this.saveUserConfig();
    }

    protected getConfigurationKey(): string {
        return ChannelFavoritesService.USER_CONFIG_KEY;
    }

    protected async readUserConfig(): Promise<void> {
        this.userConfig = await this.getUserConfig();
        if (Array.isArray(this.userConfig?.value)) {
            this.state.favorites = this.userConfig.value as string[];
        }
    }

    protected setUserConfig(): void {
        this.userConfig.value = this.state.favorites;
    }
}

let channelFavoritesService: ChannelFavoritesService;

Contena.Application.addServiceProvider('channelFavorites', () => {
    if (!channelFavoritesService) {
        channelFavoritesService = new ChannelFavoritesService();
    }

    return channelFavoritesService;
});

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        channelFavorites: ChannelFavoritesService;
    }
}

/** @private */
export default ChannelFavoritesService;

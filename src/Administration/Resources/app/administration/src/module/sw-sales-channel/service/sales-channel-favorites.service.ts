/**
 * @sw-package discovery
 */

import { reactive } from 'vue';
import AdminUserConfigClass from '../../../core/service/support/admin_user-config.class';

const { Application } = Contena;

class ChannelFavoritesService extends AdminUserConfigClass {
    static USER_CONFIG_KEY = 'sales-channel-favorites';

    private state: { favorites: string[] } = reactive({
        favorites: [],
    });

    private async initService(): Promise<void> {
        this.admin_userConfig = await this.getUserConfig();

        // @ts-expect-error - this object contains value
        if (this.admin_userConfig?.value?.length) {
            this.state.favorites = this.admin_userConfig.value as string[];
        }
    }

    public getFavoriteIds(): string[] {
        return this.state.favorites;
    }

    public isFavorite(channelId: EntityKey<'channel'>): boolean {
        return this.state.favorites.includes(channelId);
    }

    public update(state: boolean, channelId: EntityKey<'channel'>): Promise<void> {
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
        this.admin_userConfig = await this.getUserConfig();
        if (Array.isArray(this.admin_userConfig?.value)) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            this.state.favorites = this.admin_userConfig.value;
        }
    }

    protected setUserConfig(): void {
        this.admin_userConfig.value = this.state.favorites;
    }
}

let channelFavoritesService: ChannelFavoritesService;

// @ts-expect-error
Application.addServiceProvider('channelFavorites', () => {
    if (!channelFavoritesService) {
        channelFavoritesService = new ChannelFavoritesService();
    }

    return channelFavoritesService;
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export { ChannelFavoritesService as default };

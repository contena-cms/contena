/* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access, ct-deprecation-rules/private-feature-declarations */
import { flushPromises, shallowMount } from '@vue/test-utils';
import type { App } from 'vue';
import { routeLocationKey, routerKey } from 'vue-router';
import component from './index';
import type ChannelFavoritesService from '../../../service/channel-favorites.service';

const WEB_CHANNEL_TYPE_ID = '8a243080f92e4c719546314b577cf82b';
const API_CHANNEL_TYPE_ID = 'f183ee5650cf4bdb8a774337575067a6';
const systemLanguageId = Contena.Defaults.systemLanguageId;

const apiChannel = {
    id: 'api-channel',
    active: true,
    domains: [],
    type: { id: API_CHANNEL_TYPE_ID, iconName: 'regular-rocket' },
    translated: { name: 'API' },
};
const webChannel = {
    id: 'web-channel',
    active: true,
    domains: [{ languageId: systemLanguageId, url: 'https://example.com' }],
    type: { id: WEB_CHANNEL_TYPE_ID, iconName: 'regular-globe' },
    translated: { name: 'Web' },
};

describe('module/ct-channel/component/structure/ct-channel-menu', () => {
    const favorites: {
        ids: string[];
        initService: jest.Mock<Promise<void>, []>;
        getFavoriteIds: jest.Mock<string[], []>;
        isFavorite: jest.Mock<boolean, [string]>;
        update: jest.Mock<Promise<void>, []>;
    } = {
        ids: [],
        initService: jest.fn(() => Promise.resolve()),
        getFavoriteIds: jest.fn(() => favorites.ids),
        isFavorite: jest.fn((id: string) => favorites.ids.includes(id)),
        update: jest.fn(() => Promise.resolve()),
    };

    beforeAll(() => {
        Contena.Service().register('channelFavorites', () => favorites as unknown as ChannelFavoritesService);
    });

    beforeEach(() => {
        favorites.ids = [];
        favorites.initService.mockClear();
        favorites.getFavoriteIds.mockClear();
        Contena.Store.get('adminMenu').expandSidebar();
        Contena.Store.get('session').languageId = systemLanguageId;
    });

    it('marks the native Vue component for the Administration component factory', () => {
        expect((component as { _renderedBySfcTemplate?: boolean })._renderedBySfcTemplate).toBe(true);
    });

    function createWrapper(channelFixtures: Array<Record<string, unknown>> = [], canCreate = true) {
        const response = Object.assign([...channelFixtures], { total: channelFixtures.length });
        const search = jest.fn(() => Promise.resolve(response));
        const mediaQuery = {
            matches: false,
            addEventListener: jest.fn(),
            removeEventListener: jest.fn(),
        };
        const devicePlugin = {
            install(app: App) {
                Object.defineProperty(app.config.globalProperties, '$device', {
                    get: () => ({ getMediaQuery: () => mediaQuery }),
                });
            },
        };
        const wrapper = shallowMount(component, {
            global: {
                provide: {
                    repositoryFactory: { create: () => ({ search }) },
                    acl: { can: (privilege: string) => privilege !== 'channel.creator' || canCreate },
                    [routeLocationKey]: { path: '/sw/channel/detail/web-channel', name: 'ct.channel.detail' },
                    [routerKey]: { resolve: () => ({ meta: {} }) },
                },
                plugins: [devicePlugin],
                stubs: {
                    'ct-block': { template: '<div><slot /></div>' },
                    'ct-channel-modal': true,
                    'ct-admin-menu-item': {
                        props: ['entry'],
                        template: '<div :class="$attrs.class"><slot name="additional-text" /></div>',
                    },
                    'router-link': {
                        template: '<span><slot :navigate="() => undefined" /></span>',
                    },
                    'mt-dropdown-menu-root': { template: '<div><slot /></div>' },
                    'mt-dropdown-menu-trigger': { template: '<div><slot /></div>' },
                    'mt-dropdown-menu-portal': { template: '<div><slot /></div>' },
                    'mt-action-menu': { template: '<div><slot /></div>' },
                    'mt-action-menu-item': { template: '<button><slot /></button>' },
                    'mt-button': { template: '<button @click="$emit(\'click\')"><slot name="iconFront" /></button>' },
                    'mt-icon': true,
                },
            },
        });

        return { wrapper, search, mediaQuery };
    }

    it('loads Channels with the upstream menu criteria', async () => {
        const { wrapper, search } = createWrapper([webChannel]);
        await flushPromises();

        expect(search).toHaveBeenCalledTimes(1);
        const criteria = wrapper.vm.channelCriteria.parse();
        expect(criteria.associations).toEqual(
            expect.objectContaining({
                type: expect.any(Object),
                domains: expect.any(Object),
            }),
        );
    });

    it('builds one menu entry for every loaded Channel', async () => {
        const { wrapper } = createWrapper([
            apiChannel,
            webChannel,
        ]);
        await flushPromises();

        expect(wrapper.vm.buildMenuTree).toHaveLength(2);
        expect(wrapper.vm.buildMenuTree.map((entry: { icon: string }) => entry.icon)).toEqual([
            'regular-rocket',
            'regular-globe',
        ]);
        expect(wrapper.findAll('.ct-admin-menu__channel-item')).toHaveLength(2);
    });

    it('opens the Web domain from the upstream reduced menu payload but not an API Channel', async () => {
        window.open = jest.fn();
        const { wrapper } = createWrapper([
            apiChannel,
            webChannel,
        ]);
        await flushPromises();

        expect(wrapper.vm.buildMenuTree[0].domainLink).toBeNull();
        expect(wrapper.vm.buildMenuTree[1].domainLink).toBe('https://example.com');

        wrapper.vm.openFrontendLink('https://example.com');
        expect(window.open).toHaveBeenCalledWith('https://example.com', '_blank');
    });

    it('shows View all when more than seven Channels are available', async () => {
        const channels = Array.from({ length: 8 }, (_, index) => ({
            ...apiChannel,
            id: `channel-${index}`,
            translated: { name: `Channel ${index}` },
        }));
        const { wrapper } = createWrapper(channels);
        await flushPromises();

        wrapper.vm.channels = Object.assign(channels.slice(0, 7), { total: channels.length });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.moreChannelsAvailable).toBe(true);
        expect(wrapper.find('.ct-admin-menu__channel-more-items').exists()).toBe(true);
    });

    it('uses the favorite IDs and raises the criteria limit to fifty', async () => {
        favorites.ids = ['web-channel'];
        const { wrapper } = createWrapper([webChannel]);
        await flushPromises();

        expect(wrapper.vm.channelCriteria.limit).toBe(50);
        expect(wrapper.vm.channelCriteria.parse().filter).toContainEqual({
            type: 'equalsAny',
            field: 'id',
            value: 'web-channel',
        });
    });

    it('shows the add Channel entry only after an empty result loaded', async () => {
        const { wrapper } = createWrapper();

        expect(wrapper.vm.showAddChannelMenuItem).toBe(false);
        await flushPromises();
        expect(wrapper.vm.showAddChannelMenuItem).toBe(true);
    });

    it('does not show the add Channel entry without creator privileges', async () => {
        const { wrapper } = createWrapper([], false);
        await flushPromises();

        expect(wrapper.vm.showAddChannelMenuItem).toBe(false);
    });

    it('treats the mobile off-canvas menu as expanded and cleans up its listener', async () => {
        const { wrapper, mediaQuery } = createWrapper();
        await flushPromises();

        mediaQuery.matches = true;
        wrapper.vm.syncMobileViewport();
        expect(wrapper.vm.isSidebarExpanded).toBe(true);

        wrapper.unmount();
        expect(mediaQuery.removeEventListener).toHaveBeenCalledWith('change', expect.any(Function));
    });
});

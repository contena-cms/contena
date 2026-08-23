import { flushPromises, shallowMount } from '@vue/test-utils';
import { createMemoryHistory, createRouter, routeLocationKey, routerKey } from 'vue-router';
import swChannelDetailTheme from './index';

type Theme = { id: string; name?: string };
type Channel = { id: string; extensions: { themes: Theme[] } };
type ChannelSnapshot = { extensions: { themes: Theme[] } };
type ThemeLoader = (id: string) => Promise<Theme>;
type ChannelLoader = () => Promise<ChannelSnapshot>;
type ConfigLoader = () => Promise<Record<string, unknown>>;

interface ThemeComponentVm {
    theme: Theme | null;
    pendingTheme: Theme | null;
    pendingTooltip: string;
    pendingCheckTimeoutId: ReturnType<typeof setTimeout> | null;
    showThemeSelectionModal: boolean;
    getTheme: (themeId: string | null) => Promise<Theme | null>;
    checkPendingAssignment: () => Promise<void>;
    openThemeModal: () => void;
    openInThemeManager: () => Promise<unknown>;
    onChangeTheme: (themeId: string) => Promise<void>;
}

describe('ct-channel-detail-theme', () => {
    afterEach(() => {
        jest.useRealTimers();
    });

    async function createWrapper({
        aclCan = true,
        channel = null,
        themeRepositoryGet = null,
        pendingValues = {},
        channelGet = null,
        getValuesImpl = null,
    }: {
        aclCan?: boolean;
        channel?: Channel | null;
        themeRepositoryGet?: ThemeLoader | null;
        pendingValues?: Record<string, unknown>;
        channelGet?: ChannelLoader | null;
        getValuesImpl?: ConfigLoader | null;
    } = {}) {
        const channelEntity = channel ?? {
            id: 'channel-id',
            extensions: { themes: [{ id: 'theme-id' }] },
        };
        const themeRepository = {
            get: themeRepositoryGet ?? jest.fn((id) => Promise.resolve({ id })),
        };
        const channelRepository = {
            get: channelGet ?? jest.fn(() => Promise.resolve({ extensions: { themes: channelEntity.extensions.themes } })),
        };
        const systemConfigApiService = {
            getValues: getValuesImpl ?? jest.fn(() => Promise.resolve(pendingValues)),
        };
        const router = createRouter({
            history: createMemoryHistory(),
            routes: [
                { name: 'ct.theme.manager.index', path: '/themes', component: { template: '<div />' } },
                { name: 'ct.theme.manager.detail', path: '/themes/:id', component: { template: '<div />' } },
            ],
        });
        await router.push({ name: 'ct.theme.manager.index' });

        const wrapper = shallowMount(swChannelDetailTheme, {
            props: { channel: channelEntity },
            global: {
                stubs: {
                    'ct-block': true,
                    'mt-card': true,
                    'mt-button': true,
                    'mt-loader': true,
                    'ct-theme-list-item': true,
                    'ct-theme-modal': true,
                },
                directives: { tooltip: {} },
                provide: {
                    [routerKey]: router,
                    [routeLocationKey]: router.currentRoute.value,
                    repositoryFactory: {
                        create: (entity: string) => (entity === 'channel' ? channelRepository : themeRepository),
                    },
                    systemConfigApiService,
                    acl: { can: jest.fn(() => aclCan) },
                },
            },
        });

        return {
            wrapper,
            vm: wrapper.vm as unknown as ThemeComponentVm,
            router,
            channelEntity,
            themeRepository,
            channelRepository,
            systemConfigApiService,
        };
    }

    it('loads theme from channel on creation', async () => {
        const theme = { id: 'theme-id', name: 'Theme' };
        const { vm } = await createWrapper({ themeRepositoryGet: jest.fn(() => Promise.resolve(theme)) });

        await flushPromises();

        expect(vm.theme).toEqual(theme);
    });

    it('clears the theme when the channel has no assignment', async () => {
        const themeRepositoryGet = jest.fn(() => Promise.resolve({ id: 'theme-id' }));
        const { vm } = await createWrapper({
            themeRepositoryGet,
            channel: { id: 'channel-id', extensions: { themes: [] } },
        });
        themeRepositoryGet.mockClear();

        await vm.getTheme(null);

        expect(themeRepositoryGet).not.toHaveBeenCalled();
        expect(vm.theme).toBeNull();
    });

    it('opens theme selection modal when ACL allows', async () => {
        const { vm } = await createWrapper();

        vm.openThemeModal();

        expect(vm.showThemeSelectionModal).toBe(true);
    });

    it('does not open theme selection modal when ACL blocks', async () => {
        const { vm } = await createWrapper({ aclCan: false });

        vm.openThemeModal();

        expect(vm.showThemeSelectionModal).toBe(false);
    });

    it('navigates to theme manager detail when theme exists', async () => {
        const { vm, router } = await createWrapper();
        vm.theme = { id: 'theme-id' };
        const push = jest.spyOn(router, 'push');

        await vm.openInThemeManager();

        expect(push).toHaveBeenCalledWith({
            name: 'ct.theme.manager.detail',
            params: { id: 'theme-id' },
        });
    });

    it('navigates to theme manager list when theme is missing', async () => {
        const { vm, router } = await createWrapper({ channel: { id: 'channel-id', extensions: { themes: [] } } });
        vm.theme = null;
        const push = jest.spyOn(router, 'push');

        await vm.openInThemeManager();

        expect(push).toHaveBeenCalledWith({ name: 'ct.theme.manager.index' });
    });

    it('changes theme and updates the channel extension', async () => {
        const theme = { id: 'new-theme-id', name: 'Theme' };
        const channel = { id: 'channel-id', extensions: { themes: [{ id: 'old-theme-id' }] } };
        const { vm } = await createWrapper({ channel, themeRepositoryGet: jest.fn(() => Promise.resolve(theme)) });

        await vm.onChangeTheme('new-theme-id');

        expect(vm.showThemeSelectionModal).toBe(false);
        expect(channel.extensions.themes).toEqual([theme]);
    });

    it('shows the pending theme while a deferred switch compiles in the background', async () => {
        const { vm } = await createWrapper({
            channel: { id: 'channel-id', extensions: { themes: [{ id: 'live-id' }] } },
            pendingValues: { 'frontend.pendingThemeAssignment': 'pending-id' },
            channelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        await flushPromises();

        expect(vm.pendingTheme).toEqual({ id: 'pending-id', name: 'pending-id' });
        expect(vm.theme?.id).toBe('live-id');
        expect(vm.pendingTooltip).toBeTruthy();
    });

    it('shows the pending theme even when no theme is live yet', async () => {
        const { vm } = await createWrapper({
            channel: { id: 'channel-id', extensions: { themes: [] } },
            pendingValues: { 'frontend.pendingThemeAssignment': 'pending-id' },
            channelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: 'Pending Theme' })),
        });

        await flushPromises();

        expect(vm.theme).toBeNull();
        expect(vm.pendingTheme).toEqual({ id: 'pending-id', name: 'Pending Theme' });
    });

    it('shows no pending banner when there is no in-flight switch', async () => {
        const { vm } = await createWrapper({
            channel: { id: 'channel-id', extensions: { themes: [{ id: 'live-id' }] } },
            pendingValues: { 'frontend.pendingThemeAssignment': 'live-id' },
            channelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
        });

        await flushPromises();

        expect(vm.pendingTheme).toBeNull();
    });

    it('clears the banner and updates the displayed theme once the switch is applied without touching the draft', async () => {
        jest.useFakeTimers();
        const channelGet = jest
            .fn()
            .mockResolvedValueOnce({ extensions: { themes: [{ id: 'live-id' }] } })
            .mockResolvedValue({ extensions: { themes: [{ id: 'pending-id' }] } });
        const channel = { id: 'channel-id', extensions: { themes: [{ id: 'live-id' }] } };
        const { vm } = await createWrapper({
            channel,
            pendingValues: { 'frontend.pendingThemeAssignment': 'pending-id' },
            channelGet,
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        await flushPromises();
        expect(vm.pendingTheme?.id).toBe('pending-id');

        jest.advanceTimersByTime(10_000);
        await flushPromises();

        expect(vm.pendingTheme).toBeNull();
        expect(vm.theme?.id).toBe('pending-id');
        expect(channel.extensions.themes).toEqual([{ id: 'live-id' }]);
    });

    it('re-checks the pending assignment when the channel is reloaded', async () => {
        const getValues = jest
            .fn()
            .mockResolvedValueOnce({})
            .mockResolvedValue({ 'frontend.pendingThemeAssignment': 'pending-id' });
        const { wrapper, vm } = await createWrapper({
            channel: { id: 'channel-id', extensions: { themes: [{ id: 'live-id' }] } },
            getValuesImpl: getValues,
            channelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        await flushPromises();
        expect(vm.pendingTheme).toBeNull();

        await wrapper.setProps({ channel: { id: 'channel-id', extensions: { themes: [{ id: 'live-id' }] } } });
        await flushPromises();

        expect(vm.pendingTheme).toEqual({ id: 'pending-id', name: 'pending-id' });
    });

    it('does not resume polling when unmounted while a check is in flight', async () => {
        let resolvePending: (value: Record<string, unknown>) => void = () => undefined;
        const getValues = jest.fn(
            () =>
                new Promise<Record<string, unknown>>((resolve) => {
                    resolvePending = resolve;
                }),
        );
        const { wrapper, vm } = await createWrapper({
            channel: { id: 'channel-id', extensions: { themes: [{ id: 'live-id' }] } },
            getValuesImpl: getValues,
            channelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        wrapper.unmount();
        resolvePending({ 'frontend.pendingThemeAssignment': 'pending-id' });
        await flushPromises();

        expect(vm.pendingTheme).toBeNull();
        expect(vm.pendingCheckTimeoutId).toBeNull();
    });

    it('discards a stale in-flight check after the channel changes', async () => {
        let resolveFirst: (value: Record<string, unknown>) => void = () => undefined;
        const getValues = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise<Record<string, unknown>>((resolve) => {
                        resolveFirst = resolve;
                    }),
            )
            .mockResolvedValue({ 'frontend.pendingThemeAssignment': 'new-pending' });
        const { wrapper, vm } = await createWrapper({
            channel: { id: 'channel-id', extensions: { themes: [{ id: 'live-id' }] } },
            getValuesImpl: getValues,
            channelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        await wrapper.setProps({ channel: { id: 'next-channel-id', extensions: { themes: [{ id: 'live-id' }] } } });
        await flushPromises();

        expect(vm.pendingTheme?.id).toBe('new-pending');

        resolveFirst({ 'frontend.pendingThemeAssignment': 'stale-pending' });
        await flushPromises();

        expect(vm.pendingTheme?.id).toBe('new-pending');
        wrapper.unmount();
    });

    it('stops polling when the component is destroyed', async () => {
        jest.useFakeTimers();
        const { wrapper, vm } = await createWrapper({
            channel: { id: 'channel-id', extensions: { themes: [{ id: 'live-id' }] } },
            pendingValues: { 'frontend.pendingThemeAssignment': 'pending-id' },
            channelGet: jest.fn(() => Promise.resolve({ extensions: { themes: [{ id: 'live-id' }] } })),
            themeRepositoryGet: jest.fn((id) => Promise.resolve({ id, name: id })),
        });

        await flushPromises();
        expect(vm.pendingCheckTimeoutId).not.toBeNull();

        wrapper.unmount();

        expect(vm.pendingCheckTimeoutId).toBeNull();
    });
});

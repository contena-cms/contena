import { computed, defineComponent, h, ref, type ComputedRef, type Ref } from 'vue';
import { shallowMount } from '@vue/test-utils';
import { _overridesMap } from 'src/app/adapter/composition-extension-system';

const WEB_CHANNEL_TYPE_ID = '8a243080f92e4c719546314b577cf82b';
type ChannelTab = { name: string; label: string; onClick: () => void };
type Channel = {
    id: string;
    typeId: string;
    extensions: { themes: Array<{ id: string }> };
    getOrigin: () => { extensions: { themes: Array<{ id: string }> } };
};
type ChannelDetailState = {
    channel: Ref<Channel>;
    tabs: ComputedRef<ChannelTab[]>;
    onSave: () => Promise<boolean>;
};
type ChannelDetailOverrideResult = {
    tabs: ComputedRef<ChannelTab[]>;
    onSave: () => Promise<boolean>;
};
type ChannelDetailOverride = (
    previousState: ChannelDetailState,
    props: Record<string, never>,
    context: never,
) => ChannelDetailOverrideResult;

describe('ct-channel-detail Frontend extension', () => {
    const routerPush = jest.fn();
    const assignTheme = jest.fn(() => Promise.resolve());
    const originalView = Contena.Application.view;

    beforeAll(async () => {
        Contena.Application.view = {
            router: { push: routerPush },
        } as unknown as NonNullable<typeof Contena.Application.view>;
        await import('./index');
    });

    beforeEach(() => {
        routerPush.mockClear();
        assignTheme.mockReset().mockResolvedValue(undefined);
    });

    afterAll(() => {
        Contena.Application.view = originalView;
    });

    function applyOverride({
        typeId = WEB_CHANNEL_TYPE_ID,
        originThemeId = 'theme-id',
        newThemeId = originThemeId,
        baseOnSave = jest.fn(() => Promise.resolve(true)),
    }: {
        typeId?: string;
        originThemeId?: string;
        newThemeId?: string;
        baseOnSave?: jest.Mock;
    } = {}) {
        const override = _overridesMap['ct-channel-detail'].at(-1) as unknown as ChannelDetailOverride;
        const origin = { extensions: { themes: [{ id: originThemeId }] } };
        const channel: Channel = {
            id: 'channel-id',
            typeId,
            extensions: { themes: [{ id: newThemeId }] },
            getOrigin: () => origin,
        };
        const previousState = {
            channel: ref(channel),
            tabs: computed(() => [
                { name: 'ct.channel.detail.base', label: 'Base', onClick: jest.fn() },
                { name: 'ct.channel.detail.theme', label: 'Theme', onClick: jest.fn() },
            ]),
            onSave: baseOnSave,
        };
        let result: ChannelDetailOverrideResult | undefined;
        const harness = defineComponent({
            setup() {
                result = override(previousState, {}, {} as never);

                return () => h('div');
            },
        });

        const wrapper = shallowMount(harness, {
            global: {
                provide: {
                    themeService: { assignTheme },
                },
            },
        });

        if (!result) {
            throw new Error('The Channel detail override was not applied.');
        }

        return { wrapper, result, channel, origin, baseOnSave };
    }

    it('adds the content layouts tab after the theme tab for Web Channels', () => {
        const { result, wrapper } = applyOverride();

        expect(result.tabs.value.map((tab) => tab.name)).toEqual([
            'ct.channel.detail.base',
            'ct.channel.detail.theme',
            'ct.channel.detail.contentLayouts',
        ]);
        wrapper.unmount();
    });

    it('does not add the content layouts tab for API Channels', () => {
        const { result, wrapper } = applyOverride({ typeId: 'f183ee5650cf4bdb8a774337575067a6' });

        expect(result.tabs.value.map((tab) => tab.name)).toEqual([
            'ct.channel.detail.base',
            'ct.channel.detail.theme',
        ]);
        wrapper.unmount();
    });

    it('navigates to the ContentLayout route when the Meteor tab is clicked', () => {
        const { result, wrapper } = applyOverride();

        result.tabs.value.find((tab) => tab.name === 'ct.channel.detail.contentLayouts')?.onClick();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'ct.channel.detail.contentLayouts',
            params: { id: 'channel-id' },
        });
        wrapper.unmount();
    });

    it('assigns the theme through the deferred API and restores the draft association', async () => {
        const { result, channel, origin, baseOnSave, wrapper } = applyOverride({
            originThemeId: 'old-theme-id',
            newThemeId: 'new-theme-id',
        });

        await result.onSave();

        expect(assignTheme).toHaveBeenCalledWith('new-theme-id', 'channel-id');
        expect(channel.extensions.themes).toEqual(origin.extensions.themes);
        expect(baseOnSave).toHaveBeenCalled();
        wrapper.unmount();
    });

    it('does not assign a theme when the association did not change', async () => {
        const { result, wrapper } = applyOverride();

        await result.onSave();

        expect(assignTheme).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('restores the draft association when theme assignment fails', async () => {
        assignTheme.mockRejectedValueOnce(new Error('fail'));
        const { result, channel, origin, baseOnSave, wrapper } = applyOverride({
            originThemeId: 'old-theme-id',
            newThemeId: 'new-theme-id',
        });

        await result.onSave();

        expect(channel.extensions.themes).toEqual(origin.extensions.themes);
        expect(baseOnSave).toHaveBeenCalled();
        wrapper.unmount();
    });
});

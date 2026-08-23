/* global Entity */
import { computed, inject, type ComputedRef, type Ref } from 'vue';
import type { Router } from 'vue-router';
import { useNotification } from 'src/app/composables/use-notification';

const WEB_CHANNEL_TYPE_ID = '8a243080f92e4c719546314b577cf82b';
const translate = Contena.Snippet?.tc as unknown as ((key: string) => string) | undefined;
type ChannelTab = {
    name: string;
    label: string;
    disabled?: boolean;
    onClick: () => void;
};
type ChannelDetailState = {
    channel: Ref<Entity<'channel'> | null>;
    tabs: ComputedRef<ChannelTab[]>;
    onSave: () => Promise<boolean>;
};

type ThemeService = {
    assignTheme(themeId: string | null | undefined, channelId: string): Promise<void>;
};
type ThemeReference = { id: string };
type ThemeExtensions = { themes?: ThemeReference[] };

Contena.Component.overrideComponentSetup()('ct-channel-detail', (previousState) => {
    const channelDetailState = previousState as unknown as ChannelDetailState;
    const themeService = inject<ThemeService>('themeService');
    const { createNotificationError } = useNotification();
    const applicationView = Contena.Application.view;
    if (!applicationView) {
        throw new Error('The Administration view is unavailable.');
    }
    const router = applicationView.router as Router;
    const tabs = computed(() => {
        const baseTabs = channelDetailState.tabs.value.filter((tab) => tab.name !== 'ct.channel.detail.contentLayouts');

        if (channelDetailState.channel.value?.typeId !== WEB_CHANNEL_TYPE_ID) {
            return baseTabs;
        }

        const contentLayoutTab = {
            label: translate?.('ct-frontend-content-layout.general.tabTitle') ?? '',
            name: 'ct.channel.detail.contentLayouts',
            onClick: () =>
                void router.push({
                    name: 'ct.channel.detail.contentLayouts',
                    params: { id: channelDetailState.channel.value?.id },
                }),
        };
        const themeTabIndex = baseTabs.findIndex((tab) => tab.name === 'ct.channel.detail.theme');

        baseTabs.splice(themeTabIndex + 1, 0, contentLayoutTab);

        return baseTabs;
    });

    const assignChannelTheme = async (): Promise<void> => {
        const channel = channelDetailState.channel.value;
        if (!channel) {
            return;
        }

        const origin = channel.getOrigin() as unknown as { extensions?: ThemeExtensions };
        const extensions = channel.extensions as unknown as ThemeExtensions;
        const originThemeId = origin.extensions?.themes?.[0]?.id;
        const newThemeId = extensions.themes?.[0]?.id;

        if (originThemeId === newThemeId) {
            return;
        }

        try {
            if (!themeService) {
                throw new Error('The themeService is unavailable.');
            }

            await themeService.assignTheme(newThemeId, channel.id);
        } catch {
            createNotificationError({
                message:
                    translate?.('ct-theme-manager.general.messageSaveError') ?? 'ct-theme-manager.general.messageSaveError',
            });
        } finally {
            extensions.themes = [...(origin.extensions?.themes ?? [])];
        }
    };
    const onSave = async (): Promise<boolean> => {
        await assignChannelTheme();

        return channelDetailState.onSave();
    };

    return { tabs, onSave };
});

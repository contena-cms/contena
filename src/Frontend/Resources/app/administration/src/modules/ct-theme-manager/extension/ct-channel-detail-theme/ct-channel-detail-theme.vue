<template>
    <ct-block name="ct_channel_detail_theme">
        <mt-card :title="t('channel-theme.title')" :is-loading="isLoading">
            <div class="ct-channel-detail-theme">
                <ct-block name="ct_channel_detail_theme_preview">
                    <div class="ct-channel-detail-theme__preview">
                        <ct-block name="ct_channel_detail_theme_preview_item">
                            <button
                                class="ct-channel-detail-theme__preview-button"
                                type="button"
                                :disabled="!acl.can('channel.editor')"
                                @click="openThemeModal"
                            >
                                <ct-theme-list-item
                                    :theme="theme"
                                    :disabled="!acl.can('channel.editor')"
                                    :active="Boolean(theme)"
                                />
                            </button>
                        </ct-block>

                        <ct-block name="ct_channel_detail_theme_modal">
                            <ct-theme-modal
                                v-if="showThemeSelectionModal"
                                :selected-theme-id="theme?.id"
                                @modal-theme-select="onChangeTheme"
                                @modal-close="closeThemeModal"
                            />
                        </ct-block>
                    </div>
                </ct-block>

                <ct-block name="ct_channel_detail_theme_info">
                    <div class="ct-channel-detail-theme__info">
                        <ct-block name="ct_channel_detail_theme_info_content">
                            <div class="ct-channel-detail-theme__info-content">
                                <ct-block name="ct_channel_detail_theme_info_name">
                                    <div class="ct-channel-detail-theme__info-name" :class="{ 'is--empty': !theme }">
                                        <span class="ct-channel-detail-theme__info-name-text">
                                            {{ theme ? theme.name : t('channel-theme.defaultTitle') }}
                                        </span>
                                        <ct-block name="ct_channel_detail_theme_info_name_pending">
                                            <span
                                                v-if="pendingTheme"
                                                v-tooltip="{
                                                    message: pendingTooltip,
                                                    width: 300,
                                                }"
                                                class="ct-channel-detail-theme__info-pending"
                                            >
                                                <mt-loader size="16px" />
                                            </span>
                                        </ct-block>
                                    </div>
                                </ct-block>
                                <ct-block name="ct_channel_detail_theme_info_author">
                                    <div v-if="theme" class="ct-channel-detail-theme__info-author">
                                        {{ theme.author }}
                                    </div>
                                </ct-block>
                                <ct-block name="ct_channel_detail_theme_info_description">
                                    <div v-if="themeDescription" class="ct-channel-detail-theme__info-description">
                                        <p class="ct-channel-detail-theme__info-description-title">
                                            {{ t('ct-theme-manager.detail.description') }}:
                                        </p>
                                        <p>{{ themeDescription }}</p>
                                    </div>
                                </ct-block>
                            </div>
                        </ct-block>

                        <ct-block name="ct_channel_detail_theme_info_actions">
                            <div class="ct-channel-detail-theme__info-actions">
                                <mt-button
                                    size="small"
                                    :disabled="!acl.can('channel.editor')"
                                    variant="secondary"
                                    @click="openThemeModal"
                                >
                                    {{ theme ? t('channel-theme.changeTheme') : t('channel-theme.changeThemeEmpty') }}
                                </mt-button>
                                <mt-button
                                    size="small"
                                    :disabled="!acl.can('channel.editor')"
                                    variant="secondary"
                                    @click="openInThemeManager"
                                >
                                    {{ theme ? t('channel-theme.editContent') : t('channel-theme.createTheme') }}
                                </mt-button>
                            </div>
                        </ct-block>
                    </div>
                </ct-block>
            </div>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import { computed, inject, onBeforeUnmount, ref, watch, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import './ct-channel-detail-theme.scss';

type ThemeReference = Pick<Entity<'theme'>, 'id'> & Partial<Pick<Entity<'theme'>, 'name' | 'description'>>;
type ChannelWithThemes = Pick<Entity<'channel'>, 'id'> & {
    extensions: {
        themes: ThemeReference[];
    };
};

interface SystemConfigApiService {
    getValues(domain: string, channelId?: string | null): Promise<Record<string, unknown>>;
}

interface ChannelThemeExtensions {
    themes?: Entity<'theme'>[] | EntityCollection<'theme'>;
}

const PENDING_THEME_CONFIG_DOMAIN = 'frontend';
const PENDING_THEME_CONFIG_KEY = 'frontend.pendingThemeAssignment';
const PENDING_CHECK_INTERVAL = 10_000;

const props = defineProps({
    channel: {
        type: Object as PropType<ChannelWithThemes>,
        required: true,
    },
});

const { t } = useI18n();
const router = useRouter();

const injectedRepositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const injectedAcl = inject<AclService>('acl');
const injectedSystemConfigApiService = inject<SystemConfigApiService>('systemConfigApiService');
if (!injectedRepositoryFactory || !injectedAcl || !injectedSystemConfigApiService) {
    throw new Error('The repositoryFactory, acl and systemConfigApiService services are required.');
}

const repositoryFactory = injectedRepositoryFactory;
const acl = injectedAcl;
const systemConfigApiService = injectedSystemConfigApiService;

const theme = ref<Entity<'theme'> | null>((props.channel.extensions?.themes?.[0] as Entity<'theme'> | undefined) ?? null);
const pendingTheme = ref<Entity<'theme'> | null>(null);
const pendingCheckTimeoutId = ref<ReturnType<typeof setTimeout> | null>(null);
const activeCheckId = ref(0);
const showThemeSelectionModal = ref(false);
const isLoading = ref(false);
const themeRepository = computed(() => repositoryFactory.create('theme'));
const channelRepository = computed(() => repositoryFactory.create('channel'));
const themeDescription = computed(() => {
    const description = theme.value?.description ?? '';

    return description.length > 140 ? `${description.slice(0, 137)}...` : description;
});
const pendingTooltip = computed(() => {
    if (!pendingTheme.value) {
        return '';
    }

    return theme.value
        ? t('channel-theme.pendingAssignment.description', {
              liveThemeName: theme.value.name,
              pendingThemeName: pendingTheme.value.name,
          })
        : t('channel-theme.pendingAssignment.descriptionNoLiveTheme', {
              pendingThemeName: pendingTheme.value.name,
          });
});

const getTheme = async (themeId: string | null | undefined): Promise<Entity<'theme'> | null> => {
    if (!themeId) {
        theme.value = null;

        return null;
    }

    isLoading.value = true;
    const criteria = new Contena.Data.Criteria();
    criteria.addAssociation('previewMedia');

    try {
        theme.value = await themeRepository.value.get(themeId, Contena.Context.api, criteria);

        return theme.value;
    } finally {
        isLoading.value = false;
    }
};
const clearPendingCheck = (): void => {
    if (!pendingCheckTimeoutId.value) {
        return;
    }

    clearTimeout(pendingCheckTimeoutId.value);
    pendingCheckTimeoutId.value = null;
};
const schedulePendingCheck = (): void => {
    clearPendingCheck();
    pendingCheckTimeoutId.value = setTimeout(() => void checkPendingAssignment(), PENDING_CHECK_INTERVAL);
};
const loadPendingThemeId = async (): Promise<string | null> => {
    // Query the parent domain: getValues() matches "<domain>.%", so the full key would
    // return nothing. Read the exact key out of the returned "frontend.*" map.
    const values = await systemConfigApiService.getValues(PENDING_THEME_CONFIG_DOMAIN, props.channel.id);
    const pendingThemeId = values[PENDING_THEME_CONFIG_KEY];

    return typeof pendingThemeId === 'string' ? pendingThemeId : null;
};
const loadLiveThemeId = async (): Promise<string | null> => {
    const criteria = new Contena.Data.Criteria();
    criteria.addAssociation('themes');

    const channel = await channelRepository.value.get(props.channel.id, Contena.Context.api, criteria);

    const extensions = channel?.extensions as ChannelThemeExtensions | undefined;

    return extensions?.themes?.[0]?.id ?? null;
};
const checkPendingAssignment = async (): Promise<void> => {
    if (!props.channel?.id) {
        return;
    }

    // Tag this run so a result resolving after unmount or a channel change is discarded.
    activeCheckId.value += 1;
    const checkId = activeCheckId.value;

    try {
        const [
            pendingThemeId,
            liveThemeId,
        ] = await Promise.all([
            loadPendingThemeId(),
            loadLiveThemeId(),
        ]);

        if (checkId !== activeCheckId.value) {
            return;
        }

        if (pendingThemeId && pendingThemeId !== liveThemeId) {
            if (pendingTheme.value?.id !== pendingThemeId) {
                pendingTheme.value = await themeRepository.value.get(pendingThemeId, Contena.Context.api);

                if (checkId !== activeCheckId.value) {
                    return;
                }
            }

            schedulePendingCheck();

            return;
        }

        clearPendingCheck();
        pendingTheme.value = null;

        if (liveThemeId && liveThemeId !== theme.value?.id) {
            await getTheme(liveThemeId);
        }
    } catch {
        if (checkId !== activeCheckId.value) {
            return;
        }

        // best-effort indicator: on any read error stop polling and hide the spinner
        clearPendingCheck();
        pendingTheme.value = null;
    }
};
const openThemeModal = (): void => {
    if (acl.can('channel.editor')) {
        showThemeSelectionModal.value = true;
    }
};
const closeThemeModal = (): void => {
    showThemeSelectionModal.value = false;
};
const openInThemeManager = (): Promise<unknown> => {
    if (!theme.value) {
        return router.push({ name: 'ct.theme.manager.index' });
    }

    return router.push({
        name: 'ct.theme.manager.detail',
        params: { id: theme.value.id },
    });
};
const onChangeTheme = async (themeId: string | null): Promise<void> => {
    closeThemeModal();
    const selectedTheme = await getTheme(themeId);
    if (!selectedTheme) {
        return;
    }

    const themes = props.channel.extensions.themes;
    themes.splice(0, themes.length, selectedTheme);
};

watch(
    () => props.channel.extensions?.themes?.[0]?.id,
    (themeId) => {
        void getTheme(themeId);
    },
    { immediate: true },
);
watch(
    () => props.channel,
    () => {
        void checkPendingAssignment();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    // Invalidate an in-flight check so a late read cannot re-schedule polling.
    activeCheckId.value += 1;
    clearPendingCheck();
});

ctDefinePublic({
    theme,
    themeDescription,
    pendingTheme,
    pendingTooltip,
    pendingCheckTimeoutId,
    activeCheckId,
    showThemeSelectionModal,
    isLoading,
    themeRepository,
    channelRepository,
    acl,
    getTheme,
    checkPendingAssignment,
    loadPendingThemeId,
    loadLiveThemeId,
    schedulePendingCheck,
    clearPendingCheck,
    openThemeModal,
    closeThemeModal,
    openInThemeManager,
    onChangeTheme,
});

defineExpose({
    theme,
    themeDescription,
    pendingTheme,
    pendingTooltip,
    pendingCheckTimeoutId,
    activeCheckId,
    showThemeSelectionModal,
    isLoading,
    themeRepository,
    channelRepository,
    acl,
    getTheme,
    checkPendingAssignment,
    loadPendingThemeId,
    loadLiveThemeId,
    schedulePendingCheck,
    clearPendingCheck,
    openThemeModal,
    closeThemeModal,
    openInThemeManager,
    onChangeTheme,
});
</script>

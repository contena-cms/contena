<template>
    <ct-block name="sw_settings_frontend_index">
        <ct-page class="ct-settings-frontend">
            <template #smart-bar-header>
                <ct-block name="sw_settings_frontend_smart_bar_header">
                    <ct-block name="sw_settings_frontend_smart_bar_header_title">
                        <h2>
                            <ct-block name="sw_settings_frontend_smart_bar_header_title_text">
                                {{ $t('ct-settings.index.title') }}
                                <mt-icon name="regular-chevron-right-xs" size="12px" />
                                {{ $t('ct-settings-frontend.general.textHeadline') }}
                            </ct-block>
                        </h2>
                    </ct-block>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_frontend_smart_bar_actions">
                    <ct-block name="sw_settings_frontend_actions_save">
                        <mt-button
                            class="ct-settings-frontend__save-action"
                            variant="primary"
                            :is-loading="isLoading"
                            :disabled="isLoading || undefined"
                            @click="saveFrontendSettings"
                        >
                            {{ $t('global.default.save') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_frontend_content">
                    <ct-card-view>
                        <ct-skeleton v-if="isLoading" />

                        <ct-block name="sw_settings_frontend">
                            <mt-card
                                position-identifier="ct-settings-frontend--settings"
                                :title="$t('ct-settings-frontend.configuration.cardTitle')"
                                class="ct-settings-frontend__input-fields"
                            >
                                <template #toolbar>
                                    <ct-block name="sw_settings_frontend_channel_switch">
                                        <div class="ct-settings-frontend__channel-switch">
                                            <ct-channel-switch label="" @change-channel-id="onChannelChanged" />
                                        </div>
                                    </ct-block>
                                </template>

                                <ct-block name="sw_settings_frontend_settings_icon_cache">
                                    <ct-inherit-wrapper
                                        v-model:value="currentChannelFrontendSettings['core.frontendSettings.iconCache']"
                                        :inherited-value="frontendSettings['core.frontendSettings.iconCache']"
                                        :has-parent="!isGlobalConfig"
                                    >
                                        <template
                                            #content="{
                                                currentValue,
                                                updateCurrentValue,
                                                isInherited,
                                                isInheritField,
                                                restoreInheritance,
                                                removeInheritance,
                                            }"
                                        >
                                            <mt-switch
                                                :model-value="currentValue"
                                                :inherited-value="frontendSettings['core.frontendSettings.iconCache']"
                                                :disabled="isInherited || undefined"
                                                :is-inheritance-field="isInheritField"
                                                :is-inherited="isInherited"
                                                bordered
                                                :label="$t('ct-settings-frontend.configuration.iconCache')"
                                                :help-text="$t('ct-settings-frontend.configuration.iconCacheToolTip')"
                                                @update:model-value="updateCurrentValue"
                                                @inheritance-restore="restoreInheritance"
                                                @inheritance-remove="removeInheritance"
                                            />
                                        </template>
                                    </ct-inherit-wrapper>
                                </ct-block>

                                <ct-block name="sw_settings_frontend_settings_speculation_rules">
                                    <ct-inherit-wrapper
                                        v-model:value="
                                            currentChannelFrontendSettings['core.frontendSettings.speculationRules']
                                        "
                                        :inherited-value="frontendSettings['core.frontendSettings.speculationRules']"
                                        :has-parent="!isGlobalConfig"
                                    >
                                        <template
                                            #content="{
                                                currentValue,
                                                updateCurrentValue,
                                                isInherited,
                                                isInheritField,
                                                restoreInheritance,
                                                removeInheritance,
                                            }"
                                        >
                                            <mt-switch
                                                :model-value="currentValue"
                                                :inherited-value="frontendSettings['core.frontendSettings.speculationRules']"
                                                :disabled="isInherited || undefined"
                                                :is-inheritance-field="isInheritField"
                                                :is-inherited="isInherited"
                                                bordered
                                                :label="$t('ct-settings-frontend.configuration.speculationRules.title')"
                                                :help-text="
                                                    $t('ct-settings-frontend.configuration.speculationRules.description')
                                                "
                                                @update:model-value="updateCurrentValue"
                                                @inheritance-restore="restoreInheritance"
                                                @inheritance-remove="removeInheritance"
                                            />
                                        </template>
                                    </ct-inherit-wrapper>
                                </ct-block>
                            </mt-card>
                        </ct-block>

                        <ct-block name="sw_settings_frontend_global">
                            <mt-card
                                position-identifier="ct-settings-frontend--global-settings"
                                :title="$t('ct-settings-frontend.configuration.theme.cardTitle')"
                                class="ct-settings-frontend__input-fields"
                            >
                                <ct-block name="sw_settings_frontend_settings_theme_async">
                                    <mt-switch
                                        v-model="frontendSettings['core.frontendSettings.asyncThemeCompilation']"
                                        bordered
                                        :label="$t('ct-settings-frontend.configuration.theme.async')"
                                        :help-text="$t('ct-settings-frontend.configuration.theme.asyncTooltip')"
                                    />
                                </ct-block>
                            </mt-card>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

type ToggleValue = boolean | null | '';

interface FrontendSettings {
    'core.frontendSettings.iconCache': ToggleValue;
    'core.frontendSettings.asyncThemeCompilation': ToggleValue;
    'core.frontendSettings.speculationRules': ToggleValue;
}

interface ChannelFrontendSettings {
    'core.frontendSettings.iconCache': ToggleValue;
    'core.frontendSettings.speculationRules': ToggleValue;
}

interface SystemConfigApiService {
    getValues(domain: string, channelId?: string | null): Promise<Record<string, unknown>>;
    saveValues(values: Record<string, ToggleValue>, channelId?: string | null): Promise<unknown>;
}

const defaultFrontendSettings: FrontendSettings = {
    'core.frontendSettings.iconCache': true,
    'core.frontendSettings.asyncThemeCompilation': false,
    'core.frontendSettings.speculationRules': false,
};
const defaultChannelFrontendSettings: ChannelFrontendSettings = {
    'core.frontendSettings.iconCache': null,
    'core.frontendSettings.speculationRules': null,
};

const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const injectedSystemConfigApiService = inject<SystemConfigApiService>('systemConfigApiService');

if (!injectedSystemConfigApiService) {
    throw new Error('System config API service is not available.');
}

const systemConfigApiService = injectedSystemConfigApiService;

const isLoading = ref(true);
const isSaveSuccessful = ref(false);
const selectedChannelId = ref<string | null>(null);
const frontendSettings = reactive<FrontendSettings>({
    ...defaultFrontendSettings,
});
const channelFrontendSettings = reactive<ChannelFrontendSettings>({
    ...defaultChannelFrontendSettings,
});
const isGlobalConfig = computed(() => selectedChannelId.value === null);
const currentChannelFrontendSettings = computed(() => (isGlobalConfig.value ? frontendSettings : channelFrontendSettings));

async function loadPageContent(): Promise<void> {
    isLoading.value = true;

    try {
        const values = (await systemConfigApiService.getValues('core.frontendSettings')) as Partial<FrontendSettings>;
        Object.assign(frontendSettings, defaultFrontendSettings, values);

        if (!isGlobalConfig.value) {
            await loadChannelFrontendSettings();
        }
    } catch (error) {
        createNotificationError({
            message: error instanceof Error ? error.message : t('global.notification.notificationLoadingErrorMessage'),
        });
    } finally {
        isLoading.value = false;
    }
}

async function loadChannelFrontendSettings(): Promise<void> {
    const values = (await systemConfigApiService.getValues(
        'core.frontendSettings',
        selectedChannelId.value,
    )) as Partial<ChannelFrontendSettings>;

    Object.assign(channelFrontendSettings, defaultChannelFrontendSettings, {
        'core.frontendSettings.iconCache': values['core.frontendSettings.iconCache'] ?? null,
        'core.frontendSettings.speculationRules': values['core.frontendSettings.speculationRules'] ?? null,
    });
}

async function saveFrontendSettings(): Promise<void> {
    isLoading.value = true;
    isSaveSuccessful.value = false;

    if (frontendSettings['core.frontendSettings.asyncThemeCompilation'] === '') {
        frontendSettings['core.frontendSettings.asyncThemeCompilation'] = false;
    }

    if (currentChannelFrontendSettings.value['core.frontendSettings.iconCache'] === '') {
        currentChannelFrontendSettings.value['core.frontendSettings.iconCache'] = isGlobalConfig.value ? true : null;
    }

    if (currentChannelFrontendSettings.value['core.frontendSettings.speculationRules'] === '') {
        currentChannelFrontendSettings.value['core.frontendSettings.speculationRules'] = isGlobalConfig.value ? false : null;
    }

    try {
        await Promise.all([
            systemConfigApiService.saveValues({
                'core.frontendSettings.asyncThemeCompilation':
                    frontendSettings['core.frontendSettings.asyncThemeCompilation'],
            }),
            systemConfigApiService.saveValues(
                {
                    'core.frontendSettings.iconCache':
                        currentChannelFrontendSettings.value['core.frontendSettings.iconCache'],
                    'core.frontendSettings.speculationRules':
                        currentChannelFrontendSettings.value['core.frontendSettings.speculationRules'],
                },
                selectedChannelId.value,
            ),
        ]);

        isSaveSuccessful.value = true;
        createNotificationSuccess({
            message: t('ct-settings-frontend.general.messageSaveSuccess'),
        });
    } catch (error) {
        createNotificationError({
            message: error instanceof Error ? error.message : t('global.notification.notificationSaveErrorMessage'),
        });
    } finally {
        isLoading.value = false;
    }
}

async function onChannelChanged(channelId?: string | null): Promise<void> {
    selectedChannelId.value = channelId || null;

    if (isGlobalConfig.value) {
        return;
    }

    isLoading.value = true;

    try {
        await loadChannelFrontendSettings();
    } finally {
        isLoading.value = false;
    }
}

onMounted(loadPageContent);

swDefinePublic({
    isLoading,
    isSaveSuccessful,
    selectedChannelId,
    frontendSettings,
    channelFrontendSettings,
    isGlobalConfig,
    currentChannelFrontendSettings,
    loadPageContent,
    loadChannelFrontendSettings,
    saveFrontendSettings,
    onChannelChanged,
});

usePageTitle();

defineExpose({
    isLoading,
    isSaveSuccessful,
    selectedChannelId,
    frontendSettings,
    channelFrontendSettings,
    isGlobalConfig,
    currentChannelFrontendSettings,
    loadPageContent,
    loadChannelFrontendSettings,
    saveFrontendSettings,
    onChannelChanged,
});
</script>

<style lang="scss" src="./ct-settings-frontend-index.scss"></style>

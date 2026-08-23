<template>
    <ct-block name="sw_settings_sitemap_index">
        <ct-page class="ct-settings-sitemap" show-search-bar>
            <template #smart-bar-header>
                <ct-block name="sw_settings_sitemap_smart_bar_header">
                    <ct-block name="sw_settings_sitemap_smart_bar_header_title">
                        <h2>
                            <ct-block name="sw_settings_sitemap_smart_bar_header_title_text">
                                {{ $t('ct-settings.index.title') }}
                                <mt-icon name="regular-chevron-right-xs" size="12px" />
                                {{ $t('ct-settings-sitemap.general.textHeadline') }}
                            </ct-block>
                        </h2>
                    </ct-block>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_sitemap_smart_bar_actions">
                    <ct-block name="sw_settings_sitemap_actions_save">
                        <mt-button
                            class="ct-settings-sitemap__save-action"
                            variant="primary"
                            :is-loading="isLoading"
                            :disabled="isLoading || undefined"
                            @click="onSave"
                        >
                            {{ $t('global.default.save') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_sitemap_content">
                    <ct-card-view>
                        <ct-skeleton v-if="isLoading" />

                        <!-- v-show keeps the channel-aware config component mounted while it controls loading state. -->
                        <ct-system-config
                            v-show="!isLoading"
                            ref="systemConfig"
                            channel-switchable
                            domain="core.sitemap"
                            @loading-changed="onLoadingChanged"
                        />
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

interface SystemConfigComponent {
    saveAll: () => Promise<void>;
}

defineProps({});
const systemConfig = ref<SystemConfigComponent>();
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();

const isLoading = ref(false);
const isSaveSuccessful = ref(false);

const saveFinish = (): void => {
    isSaveSuccessful.value = false;
};

const onSave = async (): Promise<void> => {
    isSaveSuccessful.value = false;
    isLoading.value = true;

    try {
        await systemConfig.value?.saveAll();
        isSaveSuccessful.value = true;
        createNotificationSuccess({
            message: t('ct-settings-sitemap.general.messageSaveSuccess'),
        });
    } catch (error) {
        createNotificationError({
            message: error instanceof Error ? error.message : String(error),
        });
    } finally {
        isLoading.value = false;
    }
};

const onLoadingChanged = (loading: boolean): void => {
    isLoading.value = loading;
};

swDefinePublic({
    isLoading,
    isSaveSuccessful,
    saveFinish,
    onSave,
    onLoadingChanged,
});

usePageTitle();

defineExpose({
    isLoading,
    isSaveSuccessful,
    saveFinish,
    onSave,
    onLoadingChanged,
});
</script>

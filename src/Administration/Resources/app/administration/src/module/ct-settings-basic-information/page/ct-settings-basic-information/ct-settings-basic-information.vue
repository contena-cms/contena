<template>
    <ct-block name="ct_settings_basic_information_index">
        <ct-page class="ct-settings-basic-information" show-search-bar>
            <template #smart-bar-header>
                <ct-block name="ct_settings_basic_information_smart_bar_header">
                    <ct-block name="ct_settings_basic_information_smart_bar_header_title">
                        <h2>
                            <ct-block name="ct_settings_basic_information_smart_bar_header_title_text">
                                {{ $t('ct-settings.index.title') }}
                                <mt-icon name="regular-chevron-right-xs" size="12px" />
                                {{ $t('ct-settings-basic-information.general.textHeadline') }}
                            </ct-block>
                        </h2>
                    </ct-block>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_basic_information_smart_bar_actions">
                    <ct-block name="ct_settings_basic_information_actions_save">
                        <mt-button
                            class="ct-settings-basic-information__save-action"
                            :is-loading="isLoading"
                            :disabled="isLoading || undefined"
                            variant="primary"
                            @click="onSave"
                        >
                            {{ $t('global.default.save') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_basic_information_content">
                    <ct-card-view>
                        <template v-if="isLoading">
                            <ct-skeleton />
                            <ct-skeleton />
                        </template>

                        <!-- v-show keeps the channel-aware config component mounted while it controls loading state. -->
                        <ct-system-config
                            v-show="!isLoading"
                            ref="systemConfig"
                            channel-switchable
                            domain="core.basicInformation"
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
            message: t('ct-settings-basic-information.general.messageSaveSuccess'),
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

ctDefinePublic({
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

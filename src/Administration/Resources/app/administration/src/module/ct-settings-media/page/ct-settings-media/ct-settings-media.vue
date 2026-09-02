<template>
    <ct-block name="ct_settings_media_index">
        <ct-page class="ct-settings-media">
            <template #smart-bar-header>
                <ct-block name="ct_settings_media_header">
                    <h2>
                        {{ $t('ct-settings.index.title') }} <mt-icon name="regular-chevron-right-xs" size="12px" />
                        {{ $t('ct-settings-media.general.title') }}
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_media_smart_bar_actions">
                    <ct-block name="ct_settings_media_actions_save">
                        <ct-button-process
                            size="default"
                            class="ct-settings-media__save-action"
                            :is-loading="isLoading"
                            :process-success="isSaveSuccessful"
                            :disabled="isLoading"
                            variant="primary"
                            @update:process-success="saveFinish"
                            @click="onSave"
                        >
                            {{ $t('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_media_content">
                    <ct-card-view>
                        <template v-if="isLoading">
                            <ct-skeleton />
                            <ct-skeleton />
                        </template>

                        <ct-system-config
                            v-show="!isLoading"
                            ref="systemConfig"
                            domain="core.media"
                            @loading-changed="onLoadingChanged"
                        />
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup>
import './ct-settings-media.scss';

defineOptions({
    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
});

defineProps({});

import { ref, inject } from 'vue';
import { useNotification } from 'src/app/composables/use-notification';

const { createNotificationError } = useNotification();

const systemConfig = ref(null);

const systemConfigApiService = inject('systemConfigApiService');

const isLoading = ref(false);
const isSaveSuccessful = ref(false);

const createdComponent = () => {
    isLoading.value = true;
};
const saveFinish = () => {
    isSaveSuccessful.value = false;
};
const onSave = () => {
    isSaveSuccessful.value = false;
    isLoading.value = true;

    systemConfig.value
        .saveAll()
        .then(() => {
            isLoading.value = false;
            isSaveSuccessful.value = true;
        })
        .catch((err) => {
            isLoading.value = false;
            createNotificationError({
                message: err,
            });
        });
};
const onLoadingChanged = (loading) => {
    isLoading.value = loading;
};

void createdComponent();

ctDefinePublic({
    systemConfigApiService,
    isLoading,
    isSaveSuccessful,
    createdComponent,
    saveFinish,
    onSave,
    onLoadingChanged,
});

defineExpose({
    systemConfigApiService,
    isLoading,
    isSaveSuccessful,
    createdComponent,
    saveFinish,
    onSave,
    onLoadingChanged,
});
</script>

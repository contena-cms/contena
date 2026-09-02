<template>
    <ct-block name="ct_settings_seo">
        <ct-page class="ct-settings-seo">
            <template #smart-bar-header>
                <ct-block name="ct_settings_seo_smart_bar_header">
                    <ct-block name="ct_settings_seo_smart_bar_header_title">
                        <h2>
                            <ct-block name="ct_settings_seo_smart_bar_header_title_text">
                                {{ $t('ct-settings.index.title') }}
                                <mt-icon name="regular-chevron-right-xs" size="var(--scale-size-12)" />
                                {{ $t('ct-settings-seo.general.textHeadline') }}
                            </ct-block>
                        </h2>
                    </ct-block>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_seo_smart_bar_actions">
                    <ct-block name="ct_settings_seo_smart_bar_actions_add">
                        <mt-button variant="primary" size="default" @click="onClickSave()">
                            {{ $t('global.default.save') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_seo_content">
                    <ct-card-view>
                        <ct-block name="ct_settings_seo_content_inner">
                            <template v-if="isLoading">
                                <ct-skeleton />
                                <ct-skeleton />
                            </template>

                            <!-- v-show is used here as underlying components influence the loading state and v-if would destroy this behaviour -->
                            <div v-show="!isLoading">
                                <ct-block name="ct_settings_seo_content_inner_seo_url_template">
                                    <ct-seo-url-template-card ref="seoUrlTemplateCard" />
                                </ct-block>

                                <ct-block name="ct_settings_seo_content_inner_redirect">
                                    <ct-system-config
                                        ref="systemConfig"
                                        domain="core.seo"
                                        @loading-changed="onLoadingChanged"
                                    />
                                </ct-block>
                            </div>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { usePageTitle } from 'src/app/composables/use-page-title';

interface SystemConfigComponent {
    saveAll: () => Promise<void>;
}

interface SeoUrlTemplateCardComponent {
    onClickSave: () => void;
}

defineProps({});
const seoUrlTemplateCard = ref<SeoUrlTemplateCardComponent>();
const systemConfig = ref<SystemConfigComponent>();

const isLoading = ref(false);

const onClickSave = (): void => {
    seoUrlTemplateCard.value?.onClickSave();
    void systemConfig.value?.saveAll();
};
const onLoadingChanged = (loading: boolean): void => {
    isLoading.value = loading;
};

ctDefinePublic({
    isLoading,
    onClickSave,
    onLoadingChanged,
});
usePageTitle();

defineExpose({
    isLoading,
    onClickSave,
    onLoadingChanged,
});
</script>

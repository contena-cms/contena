<template>
    <ct-block name="sw_landing_page_view">
        <ct-card-view class="ct-landing-page-view" position-identifier="ct-landing-page-view">
            <ct-block name="sw_landing_page_view_language_info">
                <ct-language-info
                    :entity-description="
                        placeholder(landingPage, 'name', $t('ct-landing-page.general.headlineLandingPages'))
                    "
                />
            </ct-block>

            <ct-block name="sw_landing_page_view_tabs">
                <ct-block name="sw_landing_page_view_mt_tabs">
                    <mt-tabs
                        v-if="!isLoading"
                        class="ct-landing-page-detail-page__tabs"
                        position-identifier="ct-landing-page-view"
                        :default-item="$route.name"
                        :items="landingPageViewTabs"
                        :small="true"
                    />
                </ct-block>

                <ct-block name="sw_landing_page_view_mt_tabs_permission_warning">
                    <mt-banner
                        v-if="!acl.can('landing_page.editor')"
                        class="ct-landing-page-view__cms-permission-warning"
                        variant="attention"
                    >
                        {{ $t('ct-privileges.tooltip.warning') }}
                    </mt-banner>
                </ct-block>
            </ct-block>

            <ct-block name="sw_landing_page_view_content">
                <router-view v-slot="{ Component }">
                    <component :is="Component" :is-loading="isLoading" />
                </router-view>
            </ct-block>
        </ct-card-view>
    </ct-block>
</template>

<script setup>
import { computed, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import { usePlaceholder } from 'src/app/composables/use-placeholder';

defineProps({
    isLoading: {
        type: Boolean,
        required: true,
        default: false,
    },
});

const router = useRouter();
const { t } = useI18n();
const { placeholder } = usePlaceholder();
const acl = inject('acl');

const landingPage = computed(() => Contena.Store.get('swCategoryDetail').landingPage);
const landingPageViewTabs = computed(() => [
    {
        label: t('ct-landing-page.view.general'),
        name: 'ct.category.landingPageDetail.base',
        onClick: () => void router.push({ name: 'ct.category.landingPageDetail.base' }),
    },
    {
        label: t('ct-landing-page.view.layout'),
        name: 'ct.category.landingPageDetail.layout',
        disabled: !acl.can('landing_page.editor'),
        onClick: () => void router.push({ name: 'ct.category.landingPageDetail.layout' }),
    },
]);

swDefinePublic({
    landingPage,
    landingPageViewTabs,
});

defineExpose({
    landingPage,
    landingPageViewTabs,
});
</script>

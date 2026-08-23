<template>
    <ct-block name="sw_category_view">
        <ct-card-view class="ct-category-view" position-identifier="ct-category-view">
            <ct-block name="sw_category_view_language_info">
                <ct-language-info
                    :entity-description="placeholder(category, 'name', $t('ct-category.general.headlineCategories'))"
                />
            </ct-block>

            <ct-block name="sw_category_view_column_info">
                <mt-banner v-if="isCategoryColumn" class="ct-category-view__column-info" variant="info">
                    <div class="ct-category-view__column-info-header">
                        {{ $t('ct-category.view.columnInfoHeader') }}
                    </div>
                    <div class="ct-category-view__column-info-content">
                        {{ $t('ct-category.view.columnInfo') }}
                    </div>
                </mt-banner>
            </ct-block>

            <ct-block name="sw_category_view_tabs">
                <ct-block name="sw_category_view_mt_tabs">
                    <mt-tabs
                        v-if="!isLoading"
                        position-identifier="ct-category-view"
                        class="ct-category-detail-page__tabs"
                        :default-item="$route.name"
                        :items="categoryViewTabs"
                        :small="true"
                    />
                </ct-block>
            </ct-block>

            <ct-block name="sw_category_view_content">
                <router-view v-slot="{ Component }">
                    <component :is="Component" :is-loading="isLoading" />
                </router-view>
            </ct-block>
        </ct-card-view>
    </ct-block>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import { usePlaceholder } from 'src/app/composables/use-placeholder';
import errorConfig from '../../error-config.json';
import './ct-category-view.scss';

const { mapPageErrors } = Contena.Component.getComponentHelper();

const props = defineProps({
    isLoading: {
        type: Boolean,
        required: true,
        default: false,
    },
    type: {
        type: String,
        required: false,
        default: 'page',
    },
});

const router = useRouter();
const { t } = useI18n();
const { placeholder } = usePlaceholder();

const category = computed(() => Contena.Store.get('swCategoryDetail').category);
const isCategoryColumn = computed(() => Contena.Store.get('swCategoryDetail').isCategoryColumn);
const isPage = computed(() => props.type !== 'folder' && props.type !== 'link');
const pageErrors = mapPageErrors(errorConfig);
const swCategoryViewError = computed(() => pageErrors.swCategoryViewError());
const categoryViewTabs = computed(() => {
    const tabs = [
        {
            label: t('ct-category.view.general'),
            name: 'ct.category.detail.base',
            hasError: swCategoryViewError.value,
            onClick: () => void router.push({ name: 'ct.category.detail.base' }),
        },
    ];

    if (isPage.value) {
        tabs.push({
            label: t('ct-category.view.layout'),
            name: 'ct.category.detail.layout',
            onClick: () => void router.push({ name: 'ct.category.detail.layout' }),
        });
        tabs.push({
            label: t('ct-category.view.seo'),
            name: 'ct.category.detail.seo',
            onClick: () => void router.push({ name: 'ct.category.detail.seo' }),
        });
    }

    return tabs;
});

swDefinePublic({
    category,
    isCategoryColumn,
    isPage,
    categoryViewTabs,
    swCategoryViewError,
});

defineExpose({
    category,
    isCategoryColumn,
    isPage,
    categoryViewTabs,
    swCategoryViewError,
});
</script>

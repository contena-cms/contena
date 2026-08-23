<template>
    <ct-block name="sw_category_detail_seo">
        <div class="ct-category-detail-seo">
            <ct-block name="sw_category_detail_seo_config">
                <mt-card
                    position-identifier="ct-category-detail-seo"
                    :title="$t('ct-category.base.seo.title')"
                    :is-loading="isLoading"
                >
                    <ct-category-seo-form :category="category" />
                </mt-card>
            </ct-block>

            <ct-block name="sw_category_detail_seo_list">
                <ct-seo-url
                    v-if="category.seoUrls"
                    :is-loading="isLoading"
                    :has-default-template="false"
                    :disabled="!acl.can('category.editor')"
                    :urls="category.seoUrls"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
defineProps({
    isLoading: {
        type: Boolean,
        required: true,
    },
});

import { computed, inject } from 'vue';

const acl = inject('acl');

const category = computed(() => {
    return Contena.Store.get('swCategoryDetail').category;
});

swDefinePublic({
    acl,
    category,
});

defineExpose({
    acl,
    category,
});
</script>

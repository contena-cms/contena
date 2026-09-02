<!-- eslint-disable vue/no-mutating-props -->
<template>
    <ct-block name="ct_category_seo_form">
        <div class="ct-category-seo-form">
            <ct-block name="ct_category_seo_form_meta_title">
                <mt-text-field
                    v-model="category.metaTitle"
                    maxlength="255"
                    :disabled="!acl.can('category.editor')"
                    :label="$t('ct-category.base.seo.labelMetaTitle')"
                    :help-text="$t('ct-landing-page.base.seo.helpTextMetaTitle')"
                    :placeholder="$t('ct-category.base.seo.placeholderMetaTitle')"
                />
            </ct-block>

            <ct-block name="ct_category_seo_form_meta_description">
                <mt-textarea
                    v-model="category.metaDescription"
                    maxlength="255"
                    :disabled="!acl.can('category.editor')"
                    :label="$t('ct-category.base.seo.labelMetaDescription')"
                    :help-text="$t('ct-landing-page.base.seo.helpTextMetaDescription')"
                    :placeholder="$t('ct-category.base.seo.placeholderMetaDescription')"
                />
            </ct-block>

            <ct-block name="ct_category_seo_form_keywords">
                <mt-text-field
                    v-model="category.keywords"
                    :disabled="!acl.can('category.editor')"
                    :label="$t('ct-category.base.seo.labelKeywords')"
                    :placeholder="$t('ct-category.base.seo.placeholderKeywords')"
                />
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
/* eslint-disable vue/no-mutating-props */
defineProps({
    category: {
        type: Object,
        required: true,
    },
});

import { inject } from 'vue';

const acl = inject('acl');

ctDefinePublic({
    acl,
});

defineExpose({
    acl,
});
</script>

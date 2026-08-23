<template>
    <ct-block name="sw_blog_category_form">
        <div class="ct-blog-category-form">
            <ct-block name="sw_blog_category_form_visibility">
                <ct-container class="ct-blog-category-form__description">
                    <span class="ct-blog-category-form__visibility-title">
                        {{ $t('ct-blog.visibility.labelVisibility') }}
                    </span>
                    <p class="ct-blog-category-form__visibility-body">
                        {{ $t('ct-blog.visibility.bodyVisibility') }}
                    </p>
                    <ct-blog-visibility-select
                        v-if="blog.visibilities"
                        class="ct-blog-detail__select-visibility"
                        :entity-collection="blog.visibilities"
                        :placeholder="$t('ct-blog.visibility.placeholderVisibility')"
                        :disabled="!allowEdit || undefined"
                        @update:entity-collection="updateVisibilities"
                    />
                </ct-container>
            </ct-block>

            <ct-block name="sw_blog_category_form_advanced_visibility">
                <mt-link
                    v-if="hasSelectedVisibilities"
                    as="button"
                    class="advanced-visibility"
                    @click="displayAdvancedVisibility"
                    @keydown.enter="displayAdvancedVisibility"
                >
                    {{ $t('ct-blog.visibility.linkAdvancedVisibility') }}
                    <mt-icon name="regular-long-arrow-right" size="var(--scale-size-16)" />
                </mt-link>
            </ct-block>

            <ct-block name="sw_blog_category_form_categories">
                <ct-category-tree-field
                    v-if="blog.categories"
                    class="ct-blog-detail__select-category"
                    :categories-collection="blog.categories"
                    :disabled="!allowEdit || undefined"
                    :label="$t('ct-blog.categoryForm.labelCategory')"
                    :placeholder="$t('ct-blog.categoryForm.placeholderCategory')"
                />
            </ct-block>

            <ct-block name="sw_blog_category_form_tags">
                <ct-entity-tag-select
                    v-if="blog.tags"
                    class="ct-blog-category-form__tag-field"
                    :disabled="!allowEdit || undefined"
                    :placeholder="$t('ct-blog.categoryForm.placeholderTags')"
                    :error="blogTagsError"
                    :entity-collection="blog.tags"
                    @update:entity-collection="updateTags"
                />
            </ct-block>

            <ct-block name="sw_blog_category_form_search_keywords">
                <ct-multi-tag-select
                    :value="blog.customSearchKeywords ?? []"
                    class="ct-blog-category-form__search-keyword-field"
                    :label="$t('ct-blog.categoryForm.labelSearchKeyword')"
                    :help-text="$t('ct-blog.categoryForm.helpTextSearchKeyword')"
                    :placeholder="$t('ct-blog.categoryForm.placeholderSearchKeywords')"
                    :disabled="!allowEdit || undefined"
                    @update:value="updateSearchKeywords"
                >
                    <template #message-add-data>
                        <span>{{ $t('ct-blog.categoryForm.textAddSearchKeyword') }}</span>
                    </template>
                    <template #message-enter-valid-data>
                        <span>{{ $t('ct-blog.categoryForm.textEnterValidSearchKeyword') }}</span>
                    </template>
                </ct-multi-tag-select>
            </ct-block>

            <ct-modal
                v-if="displayVisibilityDetail"
                :title="$t('ct-blog.visibility.textHeadline')"
                class="ct-blog-category-form__visibility-modal"
                variant="large"
                @modal-close="closeAdvancedVisibility"
            >
                <p>{{ $t('ct-blog.visibility.visibilityModalDescription') }}</p>
                <ct-blog-visibility-detail :disabled="!allowEdit" />
                <template #modal-footer>
                    <mt-button variant="primary" size="small" @click="closeAdvancedVisibility">
                        {{ $t('global.default.apply') }}
                    </mt-button>
                </template>
            </ct-modal>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global EntityCollection */

import { computed, ref } from 'vue';

import './ct-blog-category-form.scss';

defineProps({
    allowEdit: {
        type: Boolean,
        default: true,
    },
});

const blog = computed(() => Contena.Store.get('swBlogDetail').blog);
const displayVisibilityDetail = ref(false);
const hasSelectedVisibilities = computed(() => Boolean(blog.value.visibilities?.length));
const blogTagsError = computed(() => Contena.Store.get('error').getApiError(blog.value, 'tags'));
const displayAdvancedVisibility = (): void => {
    displayVisibilityDetail.value = true;
};
const closeAdvancedVisibility = (): void => {
    displayVisibilityDetail.value = false;
};
const updateVisibilities = (visibilities: EntityCollection<'blog_visibility'>): void => {
    blog.value.visibilities = visibilities;
};
const updateTags = (tags: EntityCollection<'tag'>): void => {
    blog.value.tags = tags;
};
const updateSearchKeywords = (keywords: string[]): void => {
    blog.value.customSearchKeywords = keywords;
};

swDefinePublic({
    blog,
    displayVisibilityDetail,
    hasSelectedVisibilities,
    blogTagsError,
    displayAdvancedVisibility,
    closeAdvancedVisibility,
    updateVisibilities,
    updateTags,
    updateSearchKeywords,
});

defineExpose({
    blog,
    displayVisibilityDetail,
    hasSelectedVisibilities,
    blogTagsError,
    displayAdvancedVisibility,
    closeAdvancedVisibility,
    updateVisibilities,
    updateTags,
    updateSearchKeywords,
});
</script>

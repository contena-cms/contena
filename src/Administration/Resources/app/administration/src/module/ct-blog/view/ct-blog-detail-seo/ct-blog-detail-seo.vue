<template>
    <ct-block name="sw_blog_detail_seo">
        <div v-if="isLoading">
            <ct-skeleton variant="detail-bold" />
            <ct-skeleton />
        </div>

        <div v-else>
            <ct-block name="sw_blog_detail_seo_general">
                <mt-card position-identifier="ct-blog-detail-seo" :title="$t('ct-blog.seo.cardTitleSeo')">
                    <ct-blog-seo-form :allow-edit="allowEdit" />
                </mt-card>
            </ct-block>

            <ct-block name="sw_blog_detail_seo_urls">
                <ct-seo-url
                    v-if="blog.seoUrls"
                    :has-default-template="false"
                    :disabled="!allowEdit || undefined"
                    :urls="blog.seoUrls"
                    @on-change-channel="onChangeChannel"
                >
                    <template #seo-additional="{ currentChannelId: selectedChannelId }">
                        <ct-block name="sw_blog_detail_seo_urls_main_category">
                            <ct-seo-main-category
                                v-if="blog.mainCategories"
                                :current-channel-id="selectedChannelId"
                                :categories="categories"
                                :main-categories="blog.mainCategories"
                                :overwrite-label="true"
                                :allow-edit="allowEdit"
                                @main-category-add="onAddMainCategory"
                                @main-category-remove="onRemoveMainCategory"
                            />
                        </ct-block>
                    </template>
                </ct-seo-url>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import type AclService from 'src/app/service/acl.service';
import { computed, inject, ref } from 'vue';

defineProps({});

const acl = inject<AclService>('acl')!;
const currentChannelId = ref<string | null>(null);
const blog = computed(() => Contena.Store.get('swBlogDetail').blog);
const isLoading = computed(() => Contena.Store.get('swBlogDetail').isLoading);
const allowEdit = computed(() => acl.can('blog.editor'));
const categories = computed(() => [...blog.value.categories]);
const onAddMainCategory = (mainCategory: Entity<'blog_main_category'>): void => {
    mainCategory.blogId = blog.value.id;
    mainCategory.blogVersionId = blog.value.versionId;
    blog.value.mainCategories.add(mainCategory);
};
const onRemoveMainCategory = (mainCategory: Entity<'blog_main_category'>): void => {
    blog.value.mainCategories.remove(mainCategory.id);
};
const onChangeChannel = (channelId: string | null): void => {
    currentChannelId.value = channelId;
};

swDefinePublic({
    currentChannelId,
    blog,
    isLoading,
    allowEdit,
    categories,
    onAddMainCategory,
    onRemoveMainCategory,
    onChangeChannel,
});

defineExpose({
    currentChannelId,
    blog,
    isLoading,
    allowEdit,
    categories,
    onAddMainCategory,
    onRemoveMainCategory,
    onChangeChannel,
});
</script>

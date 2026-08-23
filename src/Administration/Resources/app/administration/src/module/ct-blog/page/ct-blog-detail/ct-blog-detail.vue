<template>
    <ct-block name="sw_blog_detail">
        <ct-page class="ct-blog-detail">
            <template #smart-bar-header>
                <ct-block name="sw_blog_detail_header">
                    <h2>{{ blogTitle }}</h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_blog_detail_actions">
                    <mt-button variant="secondary" :disabled="isLoading || undefined" @click="onCancel">
                        {{ $t('global.default.cancel') }}
                    </mt-button>
                    <ct-button-process
                        class="ct-blog-detail__save-action"
                        variant="primary"
                        :is-loading="isLoading"
                        :process-success="isSaveSuccessful"
                        :disabled="isLoading || !allowEdit || undefined"
                        @update:process-success="saveFinish"
                        @click.prevent="onSave"
                    >
                        {{ $t('global.default.save') }}
                    </ct-button-process>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-language-switch
                    :save-changes-function="saveOnLanguageChange"
                    :abort-change-function="abortOnLanguageChange"
                    :disabled="!blogId || undefined"
                    @on-change="onChangeLanguage"
                />
            </template>

            <template #content>
                <ct-block name="sw_blog_detail_content">
                    <ct-card-view>
                        <ct-language-info :entity-description="blogTitle" :is-new-entity="!blogId" />

                        <ct-block name="sw_blog_detail_tabs">
                            <mt-tabs
                                v-if="blogId"
                                class="ct-blog-detail__tabs"
                                position-identifier="ct-blog-detail"
                                :default-item="$route.name"
                                :items="blogDetailTabs"
                                small
                            />
                        </ct-block>

                        <ct-block name="sw_blog_detail_view">
                            <router-view v-slot="{ Component }">
                                <component :is="Component" />
                            </router-view>
                        </ct-block>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';
import { usePlaceholder } from 'src/app/composables/use-placeholder';

import './ct-blog-detail.scss';

interface TabItem {
    label: string;
    name: string;
    onClick: () => void;
}

const props = defineProps({
    createMode: {
        type: Boolean,
        default: false,
    },
    creationType: {
        type: String,
        default: 'post',
    },
});

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { placeholder } = usePlaceholder();
const { createNotificationError, createNotificationSuccess } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const acl = inject<AclService>('acl')!;
const isSaveSuccessful = ref(false);
const blog = computed(() => Contena.Store.get('swBlogDetail').blog);
const blogId = computed(() => (typeof route.params.id === 'string' ? route.params.id : null));
const isLoading = computed(() => Contena.Store.get('swBlogDetail').isLoading);
const blogRepository = computed(() => repositoryFactory.create('blog'));
const blogCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(1, 1);
    criteria.setTotalCountMode(0);
    criteria
        .addAssociation('categories')
        .addAssociation('tags')
        .addAssociation('visibilities.channel')
        .addAssociation('media.media')
        .addAssociation('cover.media')
        .addAssociation('mainCategories.category')
        .addAssociation('seoUrls')
        .addAssociation('openGraphMedia')
        .addAssociation('translations');

    return criteria;
});
const blogTitle = computed(() =>
    placeholder(
        blog.value,
        'name',
        t(props.createMode ? 'ct-blog.detail.textHeadline' : 'ct-blog.general.mainMenuItemGeneral'),
    ),
);
const createRouteTab = (label: string, name: string): TabItem => ({
    label: t(label),
    name,
    onClick: () => {
        void router.push({ name, params: { id: blogId.value } });
    },
});
const blogDetailTabs = computed<TabItem[]>(() => [
    createRouteTab('ct-blog.detail.tabGeneral', 'ct.blog.detail.base'),
    createRouteTab('ct-blog.detail.tabLayout', 'ct.blog.detail.layout'),
    createRouteTab('ct-blog.detail.tabSeo', 'ct.blog.detail.seo'),
]);
const allowEdit = computed(() => acl.can(props.createMode ? 'blog.creator' : 'blog.editor'));

const createState = (): void => {
    Contena.Store.get('swBlogDetail').blog = blogRepository.value.create(Contena.Context.api);
    Contena.Store.get('swBlogDetail').creationType = props.creationType;
    blog.value.active = true;
    blog.value.type = props.creationType;
    blog.value.metaTitle = '';
    blog.value.metaDescription = '';
};
const loadBlog = async (): Promise<void> => {
    if (!blogId.value) {
        createState();
        return;
    }

    Contena.Store.get('swBlogDetail').setLoading([
        'blog',
        true,
    ]);

    try {
        Contena.Store.get('swBlogDetail').blog = await blogRepository.value.get(
            blogId.value,
            Contena.Context.api,
            blogCriteria.value,
        );
    } catch {
        createNotificationError({ message: t('ct-blog.detail.loadError') });
        await router.push({ name: 'ct.blog.index' });
    } finally {
        Contena.Store.get('swBlogDetail').setLoading([
            'blog',
            false,
        ]);
    }
};
const saveBlog = async (): Promise<void> => {
    Contena.Store.get('swBlogDetail').setLoading([
        'blog',
        true,
    ]);

    try {
        if (blogRepository.value.hasChanges(blog.value)) {
            await blogRepository.value.save(blog.value, Contena.Context.api);
        }
    } finally {
        Contena.Store.get('swBlogDetail').setLoading([
            'blog',
            false,
        ]);
    }
};
const onSave = async (): Promise<void> => {
    isSaveSuccessful.value = false;

    try {
        await saveBlog();
        isSaveSuccessful.value = true;
        createNotificationSuccess({ message: t('ct-blog.detail.saveSuccess') });
        Contena.Utils.EventBus.emit('ct-blog-detail-save-finish');

        if (!blogId.value) {
            await router.push({ name: 'ct.blog.detail.base', params: { id: blog.value.id } });
        }
        await loadBlog();
    } catch {
        createNotificationError({
            message: t('global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'),
        });
    }
};
const onCancel = (): void => {
    void router.push({ name: 'ct.blog.index' });
};
const saveFinish = (): void => {
    isSaveSuccessful.value = false;
};
const abortOnLanguageChange = (): boolean => blogRepository.value.hasChanges(blog.value);
const saveOnLanguageChange = (): Promise<void> => onSave();
const onChangeLanguage = (languageId: string): void => {
    Contena.Store.get('context').setApiLanguageId(languageId);
    void loadBlog();
};

watch(blogId, () => void loadBlog());
void loadBlog();

swDefinePublic({
    acl,
    blog,
    blogId,
    isLoading,
    isSaveSuccessful,
    blogRepository,
    blogCriteria,
    blogTitle,
    blogDetailTabs,
    allowEdit,
    createState,
    loadBlog,
    onSave,
    saveBlog,
    onCancel,
    saveFinish,
    abortOnLanguageChange,
    saveOnLanguageChange,
    onChangeLanguage,
});

usePageTitle(() => blogTitle.value);

defineExpose({
    acl,
    blog,
    blogId,
    isLoading,
    isSaveSuccessful,
    blogRepository,
    blogCriteria,
    blogTitle,
    blogDetailTabs,
    allowEdit,
    createState,
    loadBlog,
    onSave,
    saveBlog,
    onCancel,
    saveFinish,
    abortOnLanguageChange,
    saveOnLanguageChange,
    onChangeLanguage,
});
</script>

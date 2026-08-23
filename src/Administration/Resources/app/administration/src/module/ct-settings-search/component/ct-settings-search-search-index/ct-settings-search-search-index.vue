<template>
    <ct-block name="sw_settings_search_search_index">
        <mt-card
            position-identifier="ct-settings-search-search-index"
            :title="t('ct-settings-search.generalTab.labelSearchIndex')"
            :is-loading="isLoading"
        >
            <ct-block name="sw_settings_search_search_index_description">
                <mt-banner
                    v-if="isRebuildInProgress"
                    class="ct-settings-search__search-index-warning-text"
                    variant="attention"
                    :title="t('ct-settings-search.generalTab.textWarningOpenTab')"
                >
                    {{ t('ct-settings-search.generalTab.textRebuildSearchIndexDescription') }}
                </mt-banner>
            </ct-block>
            <ct-block name="sw_settings_search_search_index_rebuild_button">
                <div class="ct-settings-search__search-index-actions">
                    <mt-button
                        class="ct-settings-search__search-index-rebuild-button"
                        variant="primary"
                        :disabled="isRebuildInProgress || !acl.can('blog_search_config.editor') || undefined"
                        :is-loading="isRebuildInProgress"
                        @click="rebuildSearchIndex"
                    >
                        {{ t('ct-settings-search.generalTab.buttonRebuildSearchIndex') }}
                    </mt-button>
                    <span class="ct-settings-search__search-index-latest-build">
                        <template v-if="latestIndex">
                            {{ t('ct-settings-search.generalTab.textLastedBuild') }}
                            <ct-time-ago
                                :date="latestIndex.lastDate"
                                :date-time-format="{ month: '2-digit', day: '2-digit' }"
                            />
                        </template>
                        <template v-else>{{ t('ct-settings-search.generalTab.textSearchNotIndexedYet') }}</template>
                    </span>
                </div>
            </ct-block>
            <ct-block name="sw_settings_search_search_index_rebuild_progress">
                <mt-progress-bar
                    v-if="progressBarValue"
                    class="ct-settings-search__search-index-rebuilding-progress"
                    :label="t('ct-settings-search.generalTab.textRebuildingSearchIndex')"
                    :model-value="progressBarValue"
                    :max-value="100"
                />
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type BlogIndexApiService from '../../service/blog-index.api.service';
import { useNotification } from 'src/app/composables/use-notification';

import './ct-settings-search-search-index.scss';

const BLOG_INDEXER_INTERVAL = 3000;
const props = defineProps({ isLoading: { type: Boolean, default: false } });
const emit = defineEmits<{ 'edit-change': [editing: boolean] }>();
const { t } = useI18n();
const { createNotificationError, createNotificationInfo, createNotificationSuccess } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const blogIndexService = inject<BlogIndexApiService>('blogIndexService');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !blogIndexService || !acl) throw new Error('Blog search index dependencies are unavailable.');

const internalLoading = ref(true);
const isRebuildSuccess = ref(false);
const isRebuildInProgress = ref(false);
const progressBarValue = ref(0);
const offset = ref(0);
const syncPolling = ref<ReturnType<typeof setTimeout> | null>(null);
const totalBlog = ref(0);
const latestIndex = ref<{ lastDate: string } | null>(null);
const isLoading = computed(() => props.isLoading || internalLoading.value);
const blogRepository = repositoryFactory.create('blog');
const blogSearchKeywordRepository = repositoryFactory.create('blog_search_keyword');
const blogCriteria = computed(() => new Contena.Data.Criteria(1, 1));
const blogSearchKeywordsCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(1, 1);
    criteria.addAggregation(Contena.Data.Criteria.max('lastDate', 'createdAt'));
    return criteria;
});
const getLatestBlogKeywordIndexed = async (): Promise<void> => {
    internalLoading.value = true;
    try {
        const result = await blogSearchKeywordRepository.search(blogSearchKeywordsCriteria.value, Contena.Context.api);
        if (result.total) {
            const aggregations = result.aggregations as { lastDate?: { max?: string } } | null;
            if (aggregations?.lastDate?.max) latestIndex.value = { lastDate: aggregations.lastDate.max };
        }
    } catch (error) {
        createNotificationError({ message: error instanceof Error ? error.message : String(error) });
    } finally {
        internalLoading.value = false;
    }
};
const getTotalBlog = async (): Promise<void> => {
    internalLoading.value = true;
    try {
        const result = await blogRepository.search(blogCriteria.value, Contena.Context.api);
        totalBlog.value = result.total;
    } catch (error) {
        createNotificationError({ message: error instanceof Error ? error.message : String(error) });
    } finally {
        internalLoading.value = false;
    }
};
const clearPolling = (): void => {
    if (syncPolling.value !== null) {
        clearTimeout(syncPolling.value);
        syncPolling.value = null;
    }
};
const buildFinish = (): void => {
    isRebuildSuccess.value = false;
    isRebuildInProgress.value = false;
    progressBarValue.value = 0;
    emit('edit-change', false);
};
const updateProgress = async (): Promise<void> => {
    try {
        const { data } = await blogIndexService.index(offset.value);
        isRebuildSuccess.value = data.finish;
        if (data.finish) {
            clearPolling();
            await getLatestBlogKeywordIndexed();
            progressBarValue.value = 100;
            createNotificationSuccess({ message: t('ct-settings-search.notification.index.success') });
            buildFinish();
            return;
        }
        progressBarValue.value = Math.min(99, ((offset.value || 1) / Math.max(totalBlog.value, 1)) * 100);
        offset.value = typeof data.offset === 'number' ? data.offset : (data.offset?.offset ?? offset.value);
        await updateProgress();
    } catch (error) {
        createNotificationError({ message: error instanceof Error ? error.message : String(error) });
        isRebuildSuccess.value = false;
    }
};
const pollData = (): void => {
    if (syncPolling.value === null) syncPolling.value = setTimeout(() => void updateProgress(), BLOG_INDEXER_INTERVAL);
};
const rebuildSearchIndex = (): void => {
    isRebuildInProgress.value = true;
    progressBarValue.value = 1;
    offset.value = 0;
    emit('edit-change', true);
    pollData();
    createNotificationInfo({ message: t('ct-settings-search.notification.index.started') });
};
const createdComponent = (): void => {
    internalLoading.value = false;
    void getTotalBlog();
    void getLatestBlogKeywordIndexed();
};
createdComponent();
onBeforeUnmount(clearPolling);

swDefinePublic({
    internalLoading,
    isRebuildSuccess,
    isRebuildInProgress,
    progressBarValue,
    offset,
    syncPolling,
    totalBlog,
    latestIndex,
    isLoading,
    blogRepository,
    blogSearchKeywordRepository,
    blogCriteria,
    blogSearchKeywordsCriteria,
    createdComponent,
    getLatestBlogKeywordIndexed,
    getTotalBlog,
    updateProgress,
    pollData,
    clearPolling,
    rebuildSearchIndex,
    buildFinish,
});

defineExpose({
    internalLoading,
    isRebuildSuccess,
    isRebuildInProgress,
    progressBarValue,
    offset,
    syncPolling,
    totalBlog,
    latestIndex,
    isLoading,
    blogRepository,
    blogSearchKeywordRepository,
    blogCriteria,
    blogSearchKeywordsCriteria,
    createdComponent,
    getLatestBlogKeywordIndexed,
    getTotalBlog,
    updateProgress,
    pollData,
    clearPolling,
    rebuildSearchIndex,
    buildFinish,
});
</script>

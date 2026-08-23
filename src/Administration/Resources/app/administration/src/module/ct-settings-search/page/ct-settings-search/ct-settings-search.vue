<template>
    <ct-block name="sw_settings_search_index">
        <ct-page class="ct-settings-search">
            <template #smart-bar-header>
                <ct-block name="sw_settings_search_smart_bar_header">
                    <h2>
                        {{ t('ct-settings.index.title') }} <mt-icon name="regular-chevron-right-xs" size="12px" />
                        {{ t('ct-settings-search.general.mainMenuItemGeneral') }}
                    </h2>
                </ct-block>
            </template>
            <template #language-switch><ct-language-switch @on-change="onChangeLanguage" /></template>
            <template #smart-bar-actions>
                <mt-button
                    class="ct-settings-search__button-save"
                    variant="primary"
                    :disabled="!allowSave || undefined"
                    :is-loading="isLoading"
                    @click="onSaveSearchSettings"
                >
                    {{ t('global.default.save') }}
                </mt-button>
            </template>
            <template #content>
                <ct-card-view>
                    <mt-tabs
                        position-identifier="ct-settings-search-header"
                        :default-item="route.name"
                        :items="settingsSearchTabs"
                        :small="true"
                    />
                    <template v-if="isLoading"><ct-skeleton /><ct-skeleton /></template>
                    <router-view v-show="!isLoading" v-slot="{ Component }">
                        <component
                            :is="Component"
                            :is-loading="isLoading"
                            :blog-search-configs="blogSearchConfigs"
                            :current-channel-id="currentChannelId"
                            :search-terms="searchTerms"
                            :search-results="searchResults"
                            @edit-change="onEditChanged"
                            @channel-change="onChannelChanged"
                            @live-search-results-change="onLiveSearchResultsChanged"
                            @excluded-search-terms-load="getBlogSearchConfigs"
                        />
                    </router-view>
                </ct-card-view>
                <mt-modal-root v-if="isDisplayingLeavePageWarning" :is-open="true" @change="onLeaveModalChange">
                    <mt-modal :title="t('ct-settings-search.general.mainMenuItemGeneral')" width="s">
                        {{ t('ct-settings-search.textLeaveConfirm') }}
                        <template #footer>
                            <mt-button variant="secondary" @click="onCloseLeaveModal">{{
                                t('global.default.cancel')
                            }}</mt-button>
                            <mt-button variant="critical" @click="onConfirmLeave">{{ t('global.default.leave') }}</mt-button>
                        </template>
                    </mt-modal>
                </mt-modal-root>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
/* global Entity, EntityCollection */
import { computed, inject, nextTick, ref } from 'vue';
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter, type RouteLocationNormalized } from 'vue-router';
import { useI18n } from 'vue-i18n';
import type AclService from 'src/app/service/acl.service';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

defineOptions({
    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.allowSave;
            },
            method: 'onSaveSearchSettings',
        },
        ESCAPE: 'onCloseLeaveModal',
    },
});
defineProps({});
const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const acl = inject<AclService>('acl');
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const { createNotificationError, createNotificationSuccess } = useNotification();
if (!acl || !repositoryFactory) throw new Error('Search settings dependencies are unavailable.');

const blogSearchConfigs = ref<Entity<'blog_search_config'> | null>(null);
const isLoading = ref(false);
const currentChannelId = ref<string | null>(null);
const searchTerms = ref('');
const searchResults = ref<unknown>(null);
const defaultConfig = ref<Entity<'blog_search_config'> | null>(null);
const isSaveSuccessful = ref(false);
const nextRoute = ref<RouteLocationNormalized | null>(null);
const isDisplayingLeavePageWarning = ref(false);
const leaveConfirmation = ref(false);
const isEditing = ref(false);
const blogSearchRepository = repositoryFactory.create('blog_search_config');
const blogSearchFieldRepository = repositoryFactory.create('blog_search_config_field');
const criteriaForLanguage = (languageId: string) =>
    new Contena.Data.Criteria(1, 25)
        .addAssociation('configFields')
        .addFilter(Contena.Data.Criteria.equals('languageId', languageId));
const blogSearchConfigsCriteria = computed(() => criteriaForLanguage(Contena.Context.api.languageId));
const blogDefaultConfigsCriteria = computed(() => criteriaForLanguage(Contena.Context.api.systemLanguageId));
const allowSave = computed(() => acl.can('blog_search_config.editor') || acl.can('blog_search_config.creator'));
const onTabChange = (): void => {
    void getBlogSearchConfigs();
};
const settingsSearchTabs = computed(() => [
    {
        label: t('ct-settings-search.page.generalTab'),
        name: 'ct.settings.search.index.general',
        onClick: () => {
            onTabChange();
            void router.push({ name: 'ct.settings.search.index.general' });
        },
    },
    {
        label: t('ct-settings-search.page.liveSearchTab'),
        name: 'ct.settings.search.index.liveSearch',
        onClick: () => void router.push({ name: 'ct.settings.search.index.liveSearch' }),
    },
]);
const getBlogSearchConfigs = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const items = await blogSearchRepository.search(blogSearchConfigsCriteria.value, Contena.Context.api);
        if (!items.total) await onSaveDefaultSearchConfig();
        else blogSearchConfigs.value = items.first();
    } catch (error) {
        createNotificationError({ message: error instanceof Error ? error.message : String(error) });
    } finally {
        isLoading.value = false;
    }
};
const getDefaultSearchConfig = async (): Promise<void> => {
    try {
        const items = await blogSearchRepository.search(blogDefaultConfigsCriteria.value, Contena.Context.api);
        defaultConfig.value = items.first();
    } catch (error) {
        createNotificationError({ message: error instanceof Error ? error.message : String(error) });
    }
};
const createDefaultSearchConfig = (): Entity<'blog_search_config'> | null => {
    if (!defaultConfig.value) return null;
    const config = blogSearchRepository.create(Contena.Context.api);
    config.andLogic = defaultConfig.value.andLogic;
    config.minSearchLength = defaultConfig.value.minSearchLength;
    config.excludedTerms = [];
    config.languageId = Contena.Context.api.languageId;
    return config;
};
const createConfigFields = (): EntityCollection<'blog_search_config_field'> | null => {
    if (!defaultConfig.value?.configFields?.length || !blogSearchConfigs.value) return null;
    const collection = new Contena.Data.EntityCollection<'blog_search_config_field'>(
        blogSearchFieldRepository.route,
        blogSearchFieldRepository.entityName,
        Contena.Context.api,
        new Contena.Data.Criteria(1, 25),
    );
    defaultConfig.value.configFields.forEach((item) => {
        const field = blogSearchFieldRepository.create(Contena.Context.api);
        Object.assign(field, {
            field: item.field,
            ranking: item.ranking,
            searchable: item.searchable,
            tokenize: item.tokenize,
            useExactSubfield: item.useExactSubfield,
            customFieldId: null,
            searchConfigId: blogSearchConfigs.value?.id,
        });
        collection.add(field);
    });
    return collection;
};
const onSaveDefaultSearchConfig = async (): Promise<void> => {
    const config = createDefaultSearchConfig();
    if (!config) return;
    blogSearchConfigs.value = config;
    config.configFields = createConfigFields() ?? undefined;
    try {
        await blogSearchRepository.save(config, Contena.Context.api);
        await getBlogSearchConfigs();
    } catch {
        createNotificationError({ message: t('ct-settings-search.notification.saveError') });
    }
};
const onChangeLanguage = (): void => {
    void getDefaultSearchConfig();
    void getBlogSearchConfigs();
};
const onSaveSearchSettings = async (): Promise<void> => {
    if (!blogSearchConfigs.value) {
        createNotificationError({ message: t('ct-settings-search.notification.saveError') });
        return;
    }
    isLoading.value = true;
    try {
        await blogSearchRepository.save(blogSearchConfigs.value, Contena.Context.api);
        createNotificationSuccess({ message: t('ct-settings-search.notification.saveSuccess') });
        await getBlogSearchConfigs();
        isSaveSuccessful.value = true;
    } catch {
        createNotificationError({ message: t('ct-settings-search.notification.saveError') });
    } finally {
        isLoading.value = false;
        isEditing.value = false;
    }
};
const saveFinish = (): void => {
    isSaveSuccessful.value = false;
};
const unsavedDataLeaveHandler = (to: RouteLocationNormalized): boolean => {
    if (leaveConfirmation.value) {
        leaveConfirmation.value = false;
        return true;
    }
    if (isEditing.value) {
        isDisplayingLeavePageWarning.value = true;
        nextRoute.value = to;
        return false;
    }
    return true;
};
const onChannelChanged = (id: string | null): void => {
    currentChannelId.value = id;
};
const onLiveSearchResultsChanged = (payload: { searchTerms: string; searchResults: unknown }): void => {
    searchTerms.value = payload.searchTerms;
    searchResults.value = payload.searchResults;
};
const onEditChanged = (editing: boolean): void => {
    isEditing.value = editing;
};
const onConfirmLeave = (): void => {
    leaveConfirmation.value = true;
    isDisplayingLeavePageWarning.value = false;
    isEditing.value = false;
    const target = nextRoute.value;
    void nextTick(() => {
        if (target) void router.push({ name: target.name ?? undefined, params: target.params });
    });
};
const onCloseLeaveModal = (): void => {
    isDisplayingLeavePageWarning.value = false;
};
const onLeaveModalChange = (open: boolean): void => {
    if (!open) onCloseLeaveModal();
};
const createdComponent = (): void => {
    void getDefaultSearchConfig();
    void getBlogSearchConfigs();
    Contena.ExtensionAPI.publishData({
        id: 'ct-settings-search__defaultConfig',
        path: 'defaultConfig',
        scope: { defaultConfig },
    });
    Contena.ExtensionAPI.publishData({
        id: 'ct-settings-search__blogSearchConfigs',
        path: 'blogSearchConfigs',
        scope: { blogSearchConfigs },
    });
};
createdComponent();

swDefinePublic({
    blogSearchConfigs,
    isLoading,
    currentChannelId,
    searchTerms,
    searchResults,
    defaultConfig,
    isSaveSuccessful,
    nextRoute,
    isDisplayingLeavePageWarning,
    leaveConfirmation,
    isEditing,
    blogSearchRepository,
    blogSearchFieldRepository,
    blogSearchConfigsCriteria,
    blogDefaultConfigsCriteria,
    allowSave,
    settingsSearchTabs,
    createdComponent,
    getBlogSearchConfigs,
    getDefaultSearchConfig,
    createDefaultSearchConfig,
    createConfigFields,
    onSaveDefaultSearchConfig,
    onChangeLanguage,
    onTabChange,
    onSaveSearchSettings,
    saveFinish,
    unsavedDataLeaveHandler,
    onChannelChanged,
    onLiveSearchResultsChanged,
    onEditChanged,
    onConfirmLeave,
    onCloseLeaveModal,
    onLeaveModalChange,
});

onBeforeRouteUpdate((to) => unsavedDataLeaveHandler.value(to));
onBeforeRouteLeave((to) => unsavedDataLeaveHandler.value(to));
usePageTitle();

defineExpose({
    blogSearchConfigs,
    isLoading,
    currentChannelId,
    searchTerms,
    searchResults,
    defaultConfig,
    isSaveSuccessful,
    nextRoute,
    isDisplayingLeavePageWarning,
    leaveConfirmation,
    isEditing,
    blogSearchRepository,
    blogSearchFieldRepository,
    blogSearchConfigsCriteria,
    blogDefaultConfigsCriteria,
    allowSave,
    settingsSearchTabs,
    createdComponent,
    getBlogSearchConfigs,
    getDefaultSearchConfig,
    createDefaultSearchConfig,
    createConfigFields,
    onSaveDefaultSearchConfig,
    onChangeLanguage,
    onTabChange,
    onSaveSearchSettings,
    saveFinish,
    unsavedDataLeaveHandler,
    onChannelChanged,
    onLiveSearchResultsChanged,
    onEditChanged,
    onConfirmLeave,
    onCloseLeaveModal,
    onLeaveModalChange,
});
</script>

<template>
    <ct-block name="ct_settings_search_live_search">
        <mt-card
            class="ct-settings-search-live-search"
            position-identifier="ct-settings-search-live-search"
            :title="t('ct-settings-search.liveSearchTab.titleCard')"
        >
            <ct-block name="ct_settings_search_live_search_description">
                <div class="ct-settings-search-live-search__rebuild-index-row">
                    <span class="ct-settings-search-live-search__description">
                        {{ t('ct-settings-search.liveSearchTab.textDescription') }}
                    </span>
                    <mt-link as="button" @click="showExampleModal = true">
                        {{ t('ct-settings-search.generalTab.linkExample') }}
                    </mt-link>
                    <ct-settings-search-example-modal v-if="showExampleModal" @modal-close="showExampleModal = false" />
                </div>
            </ct-block>

            <ct-block name="ct_settings_search_live_search_channel">
                <mt-select
                    class="ct-settings-search-live-search__channel-select"
                    :model-value="channelId"
                    :options="channels"
                    value-property="id"
                    label-property="translated.name"
                    :placeholder="t('ct-settings-search.liveSearchTab.textPlaceholderChannel')"
                    :disabled="undefined"
                    @update:model-value="changeChannel"
                />
            </ct-block>

            <ct-block name="ct_settings_search_live_search_input">
                <div class="ct-settings-search-live-search__search-container">
                    <mt-search
                        v-model="liveSearchTerm"
                        class="ct-settings-search-live-search__search-box"
                        :disabled="!isSearchEnabled"
                        @change="searchOnChannel"
                    />
                    <mt-select
                        v-model="sortingKey"
                        class="ct-settings-search-live-search__sorting-select"
                        value-property="key"
                        label-property="translated.label"
                        :options="blogSortings"
                        :disabled="!isSearchEnabled"
                        :placeholder="t('ct-settings-search.liveSearchTab.textPlaceholderSorting')"
                        @update:model-value="searchOnChannel()"
                    />
                </div>
            </ct-block>

            <ct-block name="ct_settings_search_live_search_results">
                <div class="ct-settings-search-live-search__search-results">
                    <mt-loader v-if="searchInProgress" />
                    <mt-data-table
                        v-else-if="resultItems.length"
                        class="ct-settings-search-live-search__grid-result"
                        :data-source="resultItems"
                        :columns="columns"
                        disable-search
                        disable-pagination
                        :disable-edit="true"
                        :disable-delete="true"
                    >
                        <template #column-name="{ data }">
                            <ct-settings-search-live-search-keyword
                                :text="data.translated?.name || data.name || ''"
                                :search-term="liveSearchTerm"
                            />
                        </template>
                        <template #column-score="{ data }">
                            <button
                                v-if="hasExplain(data)"
                                type="button"
                                class="ct-settings-search-live-search__score"
                                :aria-expanded="String(isExplainOpen(data))"
                                aria-controls="ct-settings-search-live-search-explain"
                                @click="toggleExplain(data)"
                            >
                                <span class="ct-settings-search-live-search__score-rank">#{{ getRank(data) }}</span>
                                <span class="ct-settings-search-live-search__score-bar">
                                    <span
                                        class="ct-settings-search-live-search__score-bar-fill"
                                        :style="{ width: getScoreBarWidth(data) }"
                                    />
                                </span>
                                <span class="ct-settings-search-live-search__score-value">
                                    {{ formatScore(getScoreValue(data)) }}
                                </span>
                                <mt-icon
                                    :name="isExplainOpen(data) ? 'regular-chevron-up-xs' : 'regular-chevron-down-xs'"
                                    size="12px"
                                />
                            </button>
                            <span v-else class="ct-settings-search-live-search__score">
                                <span class="ct-settings-search-live-search__score-rank">#{{ getRank(data) }}</span>
                                <span class="ct-settings-search-live-search__score-bar">
                                    <span
                                        class="ct-settings-search-live-search__score-bar-fill"
                                        :style="{ width: getScoreBarWidth(data) }"
                                    />
                                </span>
                                <span class="ct-settings-search-live-search__score-value">
                                    {{ formatScore(getScoreValue(data)) }}
                                </span>
                            </span>
                        </template>
                    </mt-data-table>
                    <mt-empty-state
                        v-else
                        class="ct-settings-search-live-search__no-result"
                        icon="regular-search"
                        :headline="t('ct-settings-search.liveSearchTab.textNoResult')"
                        :description="t('ct-settings-search.liveSearchTab.textNoResultDescription')"
                    />

                    <ct-settings-search-live-search-explain
                        v-if="selectedExplainItem"
                        :item="selectedExplainItem"
                        :search-term="currentSearchTerm"
                        :scores-are-uniform="scoresAreUniform"
                    />
                </div>
            </ct-block>
        </mt-card>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type RepositoryFactory from 'src/core/data/repository-factory.data';

import { useNotification } from 'src/app/composables/use-notification';
import type LiveSearchApiService from '../../service/live-search.api.service';
import '../ct-settings-search-live-search-keyword';
import '../ct-settings-search-live-search-explain';
import '../ct-settings-search-example-modal';
import { isFieldClause, parseClauses } from '../../helper/explain.helper';
import './ct-settings-search-live-search.scss';

type SearchResult = Entity<'blog'> & {
    extensions?: { search?: { _score?: string | number; matched_queries?: Record<string, unknown> } };
};
type Column = {
    property: string;
    label: string;
    renderer: 'text' | 'number';
    position: number;
    sortable: boolean;
    width?: number;
};

const props = withDefaults(
    defineProps<{
        currentChannelId?: string | null;
        searchTerms?: string | null;
        searchResults?: { elements?: SearchResult[] } | null;
    }>(),
    { currentChannelId: null, searchTerms: '', searchResults: null },
);
const emit = defineEmits<{
    'live-search-results-change': [payload: { searchTerms: string; searchResults: unknown }];
    'channel-change': [id: string | null];
}>();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const liveSearchService = inject<LiveSearchApiService>('liveSearchService');
if (!repositoryFactory || !liveSearchService) throw new Error('Search services are unavailable.');
const channelRepository = repositoryFactory.create('channel');
const blogSortingRepository = repositoryFactory.create('blog_sorting');
const channels = ref<Entity<'channel'>[]>([]);
const blogSortings = ref<Entity<'blog_sorting'>[]>([]);
const channelId = ref(props.currentChannelId);
const liveSearchTerm = ref(props.searchTerms ?? '');
const executedSearchTerm = ref(props.searchTerms ?? '');
const sortingKey = ref<string | null>(null);
const liveSearchResults = ref<{ elements?: SearchResult[] } | null>(props.searchResults);
const isLoading = ref(false);
const searchInProgress = ref(false);
const showExampleModal = ref(false);
const selectedExplainId = ref<string | null>(null);
const columns: Column[] = [
    {
        property: 'name',
        label: t('ct-settings-search.liveSearchTab.labelName'),
        renderer: 'text',
        position: 100,
        sortable: false,
    },
    {
        property: 'score',
        label: t('ct-settings-search.liveSearchTab.labelScore'),
        renderer: 'number',
        position: 200,
        sortable: false,
    },
];
const resultItems = computed(() => liveSearchResults.value?.elements ?? []);
const isSearchEnabled = computed(() => channelId.value !== null);
const selectedExplainItem = computed(() => resultItems.value.find((item) => item.id === selectedExplainId.value) ?? null);
const scoresAreUniform = computed(
    () =>
        resultItems.value.length > 1 &&
        resultItems.value.every((item) => getScoreValue(item) === getScoreValue(resultItems.value[0])),
);
const topScore = computed(() => resultItems.value.reduce((max, item) => Math.max(max, getScoreValue(item)), 0));
const currentSearchTerm = computed(() => executedSearchTerm.value);
const resultOffset = computed(() => 0);
const getScoreValue = (item: SearchResult): number => Number.parseFloat(String(item.extensions?.search?._score ?? 0)) || 0;
const formatScore = (value: string | number): string => {
    const score = Number.parseFloat(String(value)) || 0;
    return Number.isInteger(score) ? `${score}` : score.toFixed(1);
};
const getRank = (item: SearchResult): number | null => {
    const index = resultItems.value.indexOf(item);
    return index === -1 ? null : resultOffset.value + index + 1;
};
const getScoreBarWidth = (item: SearchResult): string =>
    topScore.value ? `${Math.max(2, (getScoreValue(item) / topScore.value) * 100)}%` : '0%';
const explainCache = computed(() => {
    void resultItems.value;
    return new WeakMap<object, boolean>();
});
const hasExplain = (item: SearchResult): boolean => {
    if (explainCache.value.has(item)) return explainCache.value.get(item) ?? false;
    const explainable = parseClauses(item.extensions?.search?.matched_queries).some(({ parsed }) => isFieldClause(parsed));
    explainCache.value.set(item, explainable);
    return explainable;
};
const toggleExplain = (item: SearchResult): void => {
    selectedExplainId.value = selectedExplainId.value === item.id ? null : item.id;
};
const isExplainOpen = (item: SearchResult): boolean => selectedExplainId.value === item.id;
const searchOnChannel = (term = liveSearchTerm.value): void => {
    selectedExplainId.value = null;
    liveSearchTerm.value = term ?? '';
    if (!channelId.value || !liveSearchTerm.value.length) return;
    const searchedTerm = liveSearchTerm.value;
    searchInProgress.value = true;
    void liveSearchService
        .search(
            { channelId: channelId.value, search: searchedTerm, order: sortingKey.value ?? undefined },
            '',
            {},
            { 'ct-language-id': Contena.Context.api.languageId },
        )
        .then((response) => {
            liveSearchResults.value = response.data as { elements?: SearchResult[] };
            executedSearchTerm.value = searchedTerm;
            emit('live-search-results-change', { searchTerms: searchedTerm, searchResults: liveSearchResults.value });
        })
        .catch((error: { response?: { status?: number }; message?: string }) =>
            createNotificationError({
                message:
                    error.response?.status === 500
                        ? t('ct-settings-search.notification.notSupportedLanguageError')
                        : (error.message ?? ''),
            }),
        )
        .finally(() => {
            searchInProgress.value = false;
        });
};
const changeChannel = (id: string | null): void => {
    channelId.value = id;
    emit('channel-change', id);
};
const load = async (): Promise<void> => {
    isLoading.value = true;
    try {
        channels.value = Array.from(await channelRepository.search(new Contena.Data.Criteria(1, 25), Contena.Context.api));
        const criteria = new Contena.Data.Criteria(1, 25);
        criteria.addFilter(Contena.Data.Criteria.equals('active', true));
        criteria.addSorting(Contena.Data.Criteria.sort('priority', 'DESC'));
        blogSortings.value = Array.from(await blogSortingRepository.search(criteria, Contena.Context.api));
        sortingKey.value =
            blogSortings.value.find((sorting) => sorting.key === 'score')?.key ?? blogSortings.value[0]?.key ?? null;
    } finally {
        isLoading.value = false;
    }
};
void load();

ctDefinePublic({
    channels,
    blogSortings,
    resultItems,
    isLoading,
    channelId,
    liveSearchTerm,
    executedSearchTerm,
    sortingKey,
    liveSearchResults,
    searchInProgress,
    showExampleModal,
    selectedExplainId,
    columns,
    isSearchEnabled,
    selectedExplainItem,
    scoresAreUniform,
    topScore,
    currentSearchTerm,
    resultOffset,
    explainCache,
    searchOnChannel,
    changeChannel,
    hasExplain,
    toggleExplain,
    getRank,
    getScoreBarWidth,
    getScoreValue,
    formatScore,
    isExplainOpen,
    load,
});

defineExpose({
    channels,
    blogSortings,
    resultItems,
    isLoading,
    searchOnChannel,
    changeChannel,
    hasExplain,
    toggleExplain,
    channelId,
    liveSearchTerm,
    executedSearchTerm,
    sortingKey,
    liveSearchResults,
    searchInProgress,
    showExampleModal,
    selectedExplainId,
    columns,
    isSearchEnabled,
    selectedExplainItem,
    scoresAreUniform,
    topScore,
    currentSearchTerm,
    resultOffset,
    explainCache,
    isExplainOpen,
    getScoreValue,
    formatScore,
    getRank,
    getScoreBarWidth,
    load,
});
</script>

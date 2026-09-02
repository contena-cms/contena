<template>
    <ct-block name="ct_settings_snippet_list">
        <ct-page class="ct-settings-snippet-list">
            <template #search-bar>
                <mt-search
                    :model-value="term"
                    :placeholder="t('ct-settings-snippet.general.placeholderSearchBarSnippets')"
                    @change="onSearch"
                />
            </template>

            <template #smart-bar-header>
                <h2 v-if="snippetSets">
                    {{ t('ct-settings-snippet.list.textSnippetList', { setName: metaName }, snippetSets.length) }}
                    <span class="ct-page__smart-bar-amount">({{ total }})</span>
                </h2>
            </template>

            <template #smart-bar-actions>
                <mt-button
                    class="ct-settings-snippet-list__button-add"
                    variant="primary"
                    :disabled="isLoading || !acl.can('snippet.creator') || undefined"
                    @click="onCreate"
                >
                    {{ t('ct-settings-snippet.list.buttonAdd') }}
                </mt-button>
            </template>

            <template #content>
                <ct-block name="ct_settings_snippet_list_content">
                    <mt-data-table
                        class="ct-settings-snippet-list__grid"
                        layout="full"
                        :caption="
                            t('ct-settings-snippet.list.textSnippetList', { setName: metaName }, snippetSets?.length ?? 0)
                        "
                        :data-source="grid"
                        :columns="columns"
                        :is-loading="isLoading || !snippetSets"
                        :pagination-total-items="total"
                        :current-page="page"
                        :pagination-limit="limit"
                        :sort-by="sortBy"
                        :sort-direction="sortDirection"
                        :disable-search="true"
                        :disable-edit="true"
                        :disable-delete="true"
                        @reload="getList"
                        @pagination-current-page-change="onPageChange"
                        @pagination-limit-change="onLimitChange"
                        @sort-change="onSortChange"
                    >
                        <template #column-id="{ data: item }">
                            <router-link
                                :to="{
                                    name: 'ct.settings.snippet.detail',
                                    params: {
                                        key: item[metaId].translationKey,
                                        origin: item[metaId].translationKey,
                                    },
                                    query: { ids: queryIds, limit, page },
                                }"
                            >
                                {{ item.id }}
                            </router-link>
                        </template>

                        <template v-for="set in snippetSets ?? []" :key="set.id" #[`column-${set.id}`]="{ data: item }">
                            <mt-text-field
                                v-if="editingKey === item.id"
                                v-model="item[set.id].value"
                                size="small"
                                :placeholder="item[set.id].origin || t('ct-settings-snippet.general.placeholderValue')"
                            />
                            <span v-else>{{ item[set.id].value }}</span>
                        </template>

                        <template #column-actions="{ data: item }">
                            <div class="ct-settings-snippet-list__actions">
                                <template v-if="editingKey === item.id">
                                    <mt-button variant="primary" square @click="onInlineEditSave(item)">
                                        <mt-icon name="regular-checkmark-xs" size="16px" />
                                    </mt-button>
                                    <mt-button variant="secondary" square @click="onInlineEditCancel(item)">
                                        <mt-icon name="regular-times-s" size="16px" />
                                    </mt-button>
                                </template>
                                <template v-else>
                                    <mt-button
                                        variant="secondary"
                                        square
                                        :disabled="!acl.can('snippet.editor') || undefined"
                                        @click="editingKey = item.id"
                                    >
                                        <mt-icon name="regular-pencil-s" size="16px" />
                                    </mt-button>
                                    <mt-button
                                        class="ct-settings-snippet-list__delete-action"
                                        variant="critical"
                                        square
                                        :disabled="!acl.can('snippet.deleter') || undefined"
                                        @click="onReset(item)"
                                    >
                                        <mt-icon name="regular-trash" size="16px" />
                                    </mt-button>
                                </template>
                            </div>
                        </template>

                        <template #empty-state>
                            <mt-empty-state
                                icon="regular-globe-stand"
                                :headline="t('ct-settings-snippet.list.emptyStateHeadLine')"
                            >
                                <mt-button v-if="showOnlyEdited" variant="secondary" @click="onEmptyClick">
                                    {{ t('ct-settings-snippet.list.buttonEmpty') }}
                                </mt-button>
                            </mt-empty-state>
                        </template>
                    </mt-data-table>
                </ct-block>
            </template>

            <template #sidebar>
                <ct-settings-snippet-sidebar
                    :filter-items="filterItems"
                    :author-filters="authorFilters"
                    :filter-settings="filterSettings"
                    @change="onChange"
                    @ct-sidebar-collaps-refresh-grid="getList"
                    @sidebar-reset-all="onResetAll"
                />
            </template>
        </ct-page>

        <mt-modal-root v-if="showDeleteModal" :is-open="true" @change="onDeleteModalChange">
            <mt-modal :title="t('global.default.warning')" width="m">
                <p>
                    {{
                        showDeleteModal.isCustomSnippet
                            ? t('ct-settings-snippet.list.textDeleteConfirm', {
                                  key: showDeleteModal[metaId].translationKey,
                              })
                            : t(
                                  'ct-settings-snippet.list.textResetConfirm',
                                  { key: showDeleteModal[metaId].translationKey },
                                  queryIdCount,
                              )
                    }}
                </p>
                <mt-checkbox
                    v-if="resetItems.length > 1"
                    :checked="modalDeleteAll"
                    :label="t('ct-settings-snippet.list.checkboxDeleteAll', {}, showDeleteModal.isCustomSnippet ? 1 : 0)"
                    @update:checked="onToggleDeleteAll"
                />
                <div class="ct-settings-snippet-list__reset-items">
                    <mt-checkbox
                        v-for="item in resetItems"
                        :key="`${item.setId}-${item.id}`"
                        :checked="selectedResetIds.includes(String(item.id))"
                        :label="item.setName"
                        @update:checked="onToggleResetItem(item, $event)"
                    />
                </div>
                <template #footer>
                    <mt-button variant="secondary" @click="onCloseDeleteModal">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button
                        variant="critical"
                        :disabled="!selectedResetIds.length || undefined"
                        @click="onConfirmReset(showDeleteModal)"
                    >
                        {{ t('global.default.delete') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity, $TSFixMe */
import { computed, inject, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import Sanitizer from 'src/core/helper/sanitizer.helper';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import type SnippetApiService from 'src/core/service/api/snippet.api.service';
import type SnippetSetApiService from 'src/core/service/api/snippet-set.api.service';
import type { SnippetListItem } from 'src/core/service/api/snippet-set.api.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-settings-snippet-list.scss';

type SnippetSet = Entity<'snippet_set'>;
type FilterSettings = Record<string, boolean>;
type FilterChange = { value: boolean; name: string; group: string | null };
type ResetItem = SnippetListItem & { setName: string; isFileSnippet?: boolean; isCustomSnippet?: boolean };
type GridRow = Record<string, $TSFixMe> & { id: string; isCustomSnippet: boolean };
type SortDirection = 'ASC' | 'DESC';

defineOptions({
    metaInfo() {
        return { title: this.$createTitle(this.metaName) };
    },
});

defineProps({});
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { createNotificationError } = useNotification();

const snippetSetService = inject<SnippetSetApiService>('snippetSetService');
const snippetService = inject<SnippetApiService>('snippetService');
const userService = inject<$TSFixMe>('userService');
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!snippetSetService || !snippetService || !userService || !repositoryFactory || !acl) {
    throw new Error('The snippet list services are unavailable.');
}

const page = ref(Number(route.query.page) || 1);
const limit = ref(Number(route.query.limit) || 25);
const total = ref(0);
const term = ref(String(route.query.term ?? ''));
const isLoading = ref(false);
const sortBy = ref(String(route.query.sortBy ?? 'id'));
const sortDirection = ref<SortDirection>((route.query.sortDirection as SortDirection) ?? 'ASC');
const metaId = ref('');
const currentAuthor = ref('');
const snippetSets = ref<SnippetSet[] | null>(null);
const showOnlyEdited = ref(false);
const showOnlyAdded = ref(false);
const emptySnippets = ref(false);
const grid = ref<GridRow[]>([]);
const resetItems = ref<ResetItem[]>([]);
const filterItems = ref<string[]>([]);
const authorFilters = ref<string[]>([]);
const appliedFilter = ref<string[]>([]);
const appliedAuthors = ref<string[]>([]);
const filterSettings = ref<FilterSettings | null>(null);
const modalDeleteAll = ref(false);
const showDeleteModal = ref<GridRow | null>(null);
const selectedResetIds = ref<string[]>([]);
const editingKey = ref<string | null>(null);

const snippetRepository = computed(() => repositoryFactory.create('snippet'));
const snippetSetRepository = computed(() => repositoryFactory.create('snippet_set'));
const queryIds = computed(() => {
    const ids = route.query.ids;
    return (Array.isArray(ids) ? ids : [ids]).filter((id): id is string => typeof id === 'string');
});
const snippetSetCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(1, 25);
    criteria.addFilter(Contena.Data.Criteria.equalsAny('id', queryIds.value));
    criteria.addSorting(Contena.Data.Criteria.sort('name', 'ASC'));
    return criteria;
});
const queryIdCount = computed(() => queryIds.value.length);
const metaName = computed(() => snippetSets.value?.[0]?.name ?? '');
const filter = computed<Record<string, unknown>>(() => {
    const currentFilter: Record<string, unknown> = {};
    if (showOnlyEdited.value) currentFilter.edited = true;
    if (showOnlyAdded.value) currentFilter.added = true;
    if (emptySnippets.value) currentFilter.empty = true;
    if (term.value) currentFilter.term = term.value;
    if (appliedFilter.value.length) currentFilter.namespace = appliedFilter.value;
    if (appliedAuthors.value.length) currentFilter.author = appliedAuthors.value;
    return currentFilter;
});
const hasActiveFilters = computed(() =>
    Boolean(filterSettings.value && Object.values(filterSettings.value).some((value) => value)),
);
const activeFilters = computed<Record<string, unknown>>(() => {
    const settings = filterSettings.value;
    if (!hasActiveFilters.value || !settings) return {};
    const active: Record<string, unknown> = {};
    if (settings.editedSnippets) active.edited = true;
    if (settings.addedSnippets) active.added = true;
    if (settings.emptySnippets) active.empty = true;
    active.author = authorFilters.value.filter((item) => settings[item]);
    active.namespace = filterItems.value.filter((item) => settings[item]);
    return active;
});
const columns = computed(() => [
    {
        property: 'id',
        label: t('ct-settings-snippet.list.columnKey'),
        renderer: 'text',
        position: 100,
        width: 320,
        sortable: true,
    },
    ...(snippetSets.value ?? []).map((set, index) => ({
        property: set.id,
        label: set.name,
        renderer: 'text' as const,
        position: (index + 2) * 100,
        width: 320,
        sortable: false,
    })),
    { property: 'actions', label: '', renderer: 'text' as const, position: 9999, width: 120, sortable: false },
]);

const createFilterSettings = (): FilterSettings => ({
    emptySnippets: false,
    editedSnippets: false,
    addedSnippets: false,
    ...Object.fromEntries(
        authorFilters.value.map((item) => [
            item,
            false,
        ]),
    ),
    ...Object.fromEntries(
        filterItems.value.map((item) => [
            item,
            false,
        ]),
    ),
});
const getFilterSettings = async (): Promise<void> => {
    const response = await Contena.Service('userConfigService').search(['grid.filter.setting-snippet-list']);
    filterSettings.value =
        (response?.data?.['grid.filter.setting-snippet-list'] as FilterSettings | undefined) ?? createFilterSettings();
};
const saveUserConfig = () =>
    Contena.Service('userConfigService').upsert({
        'grid.filter.setting-snippet-list': filterSettings.value,
    });
const updateRoute = (): void => {
    void router.replace({
        query: {
            ...route.query,
            ids: queryIds.value,
            term: term.value || undefined,
            page: page.value,
            limit: limit.value,
            sortBy: sortBy.value,
            sortDirection: sortDirection.value,
        },
    });
};
const prepareGrid = (rawGrid: Record<string, SnippetListItem[]>): GridRow[] =>
    Object.values(rawGrid).map((items) => {
        const content = items.reduce<GridRow>(
            (row, item) => {
                item.resetTo = item.value;
                row[item.setId] = item;
                row.isCustomSnippet = item.author.includes('user/') || item.author.length < 1;
                return row;
            },
            { id: items[0].translationKey, isCustomSnippet: false },
        );
        return content;
    });
const initializeSnippetSet = async (currentFilter: Record<string, unknown> = filter.value): Promise<void> => {
    if (!queryIds.value.length) {
        backRoutingError();
        return;
    }
    isLoading.value = true;
    try {
        const response = await snippetSetService.getCustomList(page.value, limit.value, currentFilter, {
            sortBy: sortBy.value,
            sortDirection: sortDirection.value,
        });
        metaId.value = queryIds.value[0];
        total.value = response.total;
        grid.value = prepareGrid(response.data);
    } finally {
        isLoading.value = false;
    }
};
const getList = (): Promise<void> => initializeSnippetSet(hasActiveFilters.value ? activeFilters.value : filter.value);
const initialize = async (): Promise<void> => {
    snippetSets.value = Array.from(await snippetSetRepository.value.search(snippetSetCriteria.value, Contena.Context.api));
    const user = await userService.getUser();
    currentAuthor.value = `user/${user.data.username}`;
    filterItems.value = (await snippetService.getFilter()).data;
    authorFilters.value = (await snippetSetService.getAuthors()).data;
    await getFilterSettings();
    await getList();
};
const onInlineEditSave = async (result: GridRow): Promise<void> => {
    const responses: Promise<unknown>[] = [];
    const key = result[metaId.value].translationKey as string;
    (snippetSets.value ?? []).forEach((set) => {
        const snippet = result[set.id] as SnippetListItem;
        snippet.value = Sanitizer.sanitize(snippet.value ?? '');
        if (!snippet.value && typeof snippet.value !== 'string') snippet.value = snippet.origin;
        if (!Object.hasOwn(snippet, 'author') || !snippet.author) snippet.author = currentAuthor.value;
        if (snippet.origin !== snippet.value) {
            const entity = snippetRepository.value.create(Contena.Context.api);
            if (snippet.id) entity._isNew = false;
            Object.assign(entity, snippet);
            responses.push(snippetRepository.value.save(entity, Contena.Context.api));
        } else if (snippet.id !== null && !snippet.author.startsWith('user/')) {
            responses.push(snippetRepository.value.delete(snippet.id, Contena.Context.api));
        }
    });
    try {
        await Promise.all(responses);
    } catch {
        inlineSaveErrorMessage(key);
    } finally {
        editingKey.value = null;
        await getList();
    }
};
const onInlineEditCancel = (row: GridRow): void => {
    Object.values(row).forEach((item) => {
        if (typeof item === 'object' && item && 'value' in item) item.value = item.resetTo;
    });
    editingKey.value = null;
};
const onEmptyClick = (): void => {
    showOnlyEdited.value = false;
    void getList();
};
const onSearch = (value: string): void => {
    term.value = value;
    page.value = 1;
    updateRoute();
    void getList();
};
const backRoutingError = (): void => {
    void router.push({ name: 'ct.settings.snippet.index' });
    createNotificationError({ message: t('ct-settings-snippet.general.errorBackRoutingMessage') });
};
const inlineSaveErrorMessage = (key: string): void => {
    createNotificationError({
        message: t('ct-settings-snippet.list.messageSaveError', { key }, queryIdCount.value),
    });
};
const getName = (list: SnippetSet[], id: string): string => list.find((item) => item.id === id)?.name ?? '';
const onReset = async (item: GridRow): Promise<void> => {
    isLoading.value = true;
    try {
        const sets = Array.from(
            await snippetSetRepository.value.search(snippetSetCriteria.value, Contena.Context.api),
        ) as SnippetSet[];
        resetItems.value = Object.values(item)
            .filter(
                (currentItem): currentItem is ResetItem =>
                    typeof currentItem === 'object' && currentItem !== null && queryIds.value.includes(currentItem.setId),
            )
            .map((currentItem, index) => ({
                ...currentItem,
                setName: getName(sets, currentItem.setId),
                id: currentItem.id ?? String(index),
                isFileSnippet: currentItem.id === null,
            }))
            .sort((a, b) => a.setName.localeCompare(b.setName));
        selectedResetIds.value = resetItems.value
            .filter((resetItem) => !resetItem.isFileSnippet)
            .map((resetItem) => String(resetItem.id));
        modalDeleteAll.value = selectedResetIds.value.length === resetItems.value.length;
        showDeleteModal.value = item;
    } finally {
        isLoading.value = false;
    }
};
const onCloseDeleteModal = (): void => {
    showDeleteModal.value = null;
    modalDeleteAll.value = false;
    resetItems.value = [];
    selectedResetIds.value = [];
};
const onDeleteModalChange = (open: boolean): void => {
    if (!open) onCloseDeleteModal();
};
const createResetErrorNote = (item: ResetItem): void => {
    createNotificationError({
        message: t('ct-settings-snippet.list.resetErrorMessage', { key: item.value }, item.isCustomSnippet ? 0 : 1),
    });
};
const onConfirmReset = async (fullSelection: GridRow): Promise<void> => {
    const items = resetItems.value.filter((item) => selectedResetIds.value.includes(String(item.id)));
    onCloseDeleteModal();
    isLoading.value = true;
    try {
        await Promise.all(
            items
                .filter((item) => !item.isFileSnippet && item.id !== null)
                .map((item) => {
                    item.isCustomSnippet = fullSelection.isCustomSnippet;
                    return snippetRepository.value
                        .delete(String(item.id), Contena.Context.api)
                        .catch(() => createResetErrorNote(item));
                }),
        );
        await getList();
    } finally {
        isLoading.value = false;
    }
};
const onChange = (field: FilterChange): void => {
    if (!filterSettings.value) return;
    filterSettings.value[field.name] = field.value;
    page.value = 1;
    if (field.group === 'editedSnippets') showOnlyEdited.value = field.value;
    else if (field.group === 'addedSnippets') showOnlyAdded.value = field.value;
    else if (field.group === 'emptySnippets') emptySnippets.value = field.value;
    else {
        const target = field.group === 'authorFilter' ? appliedAuthors : appliedFilter;
        target.value = field.value
            ? [
                  ...new Set([
                      ...target.value,
                      field.name,
                  ]),
              ]
            : target.value.filter((item) => item !== field.name);
    }
    void getList();
};
const onResetAll = (): void => {
    showOnlyEdited.value = false;
    showOnlyAdded.value = false;
    emptySnippets.value = false;
    appliedFilter.value = [];
    appliedAuthors.value = [];
    if (filterSettings.value) {
        Object.keys(filterSettings.value).forEach((key) => {
            if (filterSettings.value) filterSettings.value[key] = false;
        });
    }
    void initializeSnippetSet({});
};
const onSortChange = ({ sortBy: property, sortDirection: direction }: $TSFixMe): void => {
    sortBy.value = property;
    sortDirection.value = direction;
    updateRoute();
    void getList();
};
const onPageChange = (value: number): void => {
    page.value = value;
    updateRoute();
    void getList();
};
const onLimitChange = (value: number): void => {
    limit.value = value;
    page.value = 1;
    updateRoute();
    void getList();
};
const onToggleDeleteAll = (checked: boolean): void => {
    modalDeleteAll.value = checked;
    selectedResetIds.value = checked
        ? resetItems.value.filter((item) => !item.isFileSnippet).map((item) => String(item.id))
        : [];
};
const onToggleResetItem = (item: ResetItem, checked: boolean): void => {
    const id = String(item.id);
    selectedResetIds.value = checked
        ? [
              ...new Set([
                  ...selectedResetIds.value,
                  id,
              ]),
          ]
        : selectedResetIds.value.filter((selectedId) => selectedId !== id);
    modalDeleteAll.value =
        selectedResetIds.value.length === resetItems.value.filter((resetItem) => !resetItem.isFileSnippet).length;
};
const onCreate = (): void => {
    void router.push({
        name: 'ct.settings.snippet.create',
        query: { ids: queryIds.value, limit: limit.value, page: page.value },
    });
};

const onBeforeUnload = (): void => {
    void saveUserConfig();
};

window.addEventListener('beforeunload', onBeforeUnload);
onBeforeUnmount(() => {
    void saveUserConfig();
    window.removeEventListener('beforeunload', onBeforeUnload);
});
void initialize();

ctDefinePublic({
    acl,
    page,
    limit,
    total,
    term,
    isLoading,
    sortBy,
    sortDirection,
    metaId,
    currentAuthor,
    snippetSets,
    showOnlyEdited,
    showOnlyAdded,
    emptySnippets,
    grid,
    resetItems,
    filterItems,
    authorFilters,
    appliedFilter,
    appliedAuthors,
    filterSettings,
    modalDeleteAll,
    showDeleteModal,
    selectedResetIds,
    editingKey,
    snippetRepository,
    snippetSetRepository,
    queryIds,
    snippetSetCriteria,
    queryIdCount,
    metaName,
    filter,
    hasActiveFilters,
    activeFilters,
    columns,
    getFilterSettings,
    saveUserConfig,
    createFilterSettings,
    getList,
    initializeSnippetSet,
    prepareGrid,
    onInlineEditSave,
    onInlineEditCancel,
    onEmptyClick,
    onSearch,
    backRoutingError,
    inlineSaveErrorMessage,
    onReset,
    getName,
    onCloseDeleteModal,
    onDeleteModalChange,
    onConfirmReset,
    createResetErrorNote,
    onChange,
    onResetAll,
    onSortChange,
    onPageChange,
    onLimitChange,
    onToggleDeleteAll,
    onToggleResetItem,
    onCreate,
});

defineExpose({
    acl,
    page,
    limit,
    total,
    term,
    isLoading,
    sortBy,
    sortDirection,
    metaId,
    currentAuthor,
    snippetSets,
    showOnlyEdited,
    showOnlyAdded,
    emptySnippets,
    grid,
    resetItems,
    filterItems,
    authorFilters,
    appliedFilter,
    appliedAuthors,
    filterSettings,
    modalDeleteAll,
    showDeleteModal,
    selectedResetIds,
    editingKey,
    snippetRepository,
    snippetSetRepository,
    queryIds,
    snippetSetCriteria,
    queryIdCount,
    metaName,
    filter,
    hasActiveFilters,
    activeFilters,
    columns,
    getFilterSettings,
    saveUserConfig,
    createFilterSettings,
    getList,
    initializeSnippetSet,
    prepareGrid,
    onInlineEditSave,
    onInlineEditCancel,
    onEmptyClick,
    onSearch,
    backRoutingError,
    inlineSaveErrorMessage,
    onReset,
    getName,
    onCloseDeleteModal,
    onDeleteModalChange,
    onConfirmReset,
    createResetErrorNote,
    onChange,
    onResetAll,
    onSortChange,
    onPageChange,
    onLimitChange,
    onToggleDeleteAll,
    onToggleResetItem,
    onCreate,
});
</script>

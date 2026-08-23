<template>
    <ct-block name="sw_settings_snippet_set_list">
        <ct-page class="ct-settings-snippet-set-list">
            <template #search-bar>
                <mt-search
                    :model-value="term"
                    :placeholder="t('ct-settings-snippet.general.placeholderSearchBarSets')"
                    @change="onSearch"
                />
            </template>

            <template #smart-bar-header>
                <h2>
                    {{ t('ct-settings.index.title') }}
                    <mt-icon name="regular-chevron-right-xs" size="12px" />
                    {{ t('ct-settings-snippet.general.mainMenuItemGeneral') }}
                </h2>
            </template>

            <template #smart-bar-actions>
                <mt-button
                    class="ct-settings-snippet-set-list__action-add"
                    variant="primary"
                    :is-loading="isLoading"
                    :disabled="!acl.can('snippet.creator') || undefined"
                    @click="onAddSnippetSet"
                >
                    {{ t('ct-settings-snippet.setList.buttonAddSet') }}
                </mt-button>
            </template>

            <template #content>
                <ct-block name="sw_settings_snippet_set_list_content">
                    <mt-data-table
                        class="ct-settings-snippet-set-list__table"
                        layout="full"
                        :caption="t('ct-settings-snippet.setList.cardTitle')"
                        :data-source="snippetSets"
                        :columns="snippetSetColumns"
                        :is-loading="isLoading"
                        :pagination-total-items="total"
                        :current-page="page"
                        :pagination-limit="limit"
                        :sort-by="sortBy"
                        :sort-direction="sortDirection"
                        :selected-rows="selectedIds"
                        :allow-row-selection="true"
                        :disable-search="true"
                        :disable-edit="true"
                        :disable-delete="true"
                        @reload="getList"
                        @pagination-current-page-change="onPageChange"
                        @pagination-limit-change="onLimitChange"
                        @selection-change="onSelectionChange"
                        @multiple-selection-change="onMultipleSelectionChange"
                    >
                        <template v-if="selectedIds.length" #toolbar>
                            <mt-button variant="secondary" @click="onEditSnippetSets">
                                {{ contextMenuEditSnippet }}
                            </mt-button>
                        </template>

                        <template #column-name="{ data: item }">
                            <mt-text-field
                                v-if="editingId === item.id"
                                v-model="item.name"
                                size="small"
                                :placeholder="t('ct-settings-snippet.setList.placeholderName')"
                            />
                            <router-link v-else :to="{ name: 'ct.settings.snippet.list', query: { ids: [item.id] } }">
                                {{ item.name }}
                            </router-link>
                        </template>

                        <template #column-baseFile="{ data: item }">
                            <mt-select
                                v-if="editingId === item.id"
                                v-model="item.baseFile"
                                size="small"
                                :options="baseFileOptions"
                                :placeholder="t('ct-settings-snippet.setList.placeholderBaseFile')"
                            />
                            <span v-else>{{ item.baseFile }}</span>
                        </template>

                        <!-- eslint-disable-next-line vue/no-unused-vars -->
                        <template #column-updatedAt="{ data: item }">
                            {{ formatDate(item.updatedAt || item.createdAt) }}
                        </template>

                        <template #column-actions="{ data: item }">
                            <div class="ct-settings-snippet-set-list__actions">
                                <template v-if="editingId === item.id">
                                    <mt-button variant="primary" square @click="onInlineEditSave(item)">
                                        <mt-icon name="regular-checkmark-xs" size="16px" />
                                    </mt-button>
                                    <mt-button variant="secondary" square @click="onInlineEditCancel">
                                        <mt-icon name="regular-times-s" size="16px" />
                                    </mt-button>
                                </template>
                                <template v-else>
                                    <mt-button
                                        variant="secondary"
                                        square
                                        :disabled="!acl.can('snippet.editor') || undefined"
                                        @click="toggleInlineEdit(item.id)"
                                    >
                                        <mt-icon name="regular-pencil-s" size="16px" />
                                    </mt-button>
                                    <mt-button
                                        variant="secondary"
                                        square
                                        :disabled="!acl.can('snippet.creator') || undefined"
                                        @click="onConfirmClone(item.id)"
                                    >
                                        <mt-icon name="regular-duplicate" size="16px" />
                                    </mt-button>
                                    <mt-button
                                        variant="critical"
                                        square
                                        :disabled="!acl.can('snippet.deleter') || undefined"
                                        @click="onDeleteSet(item)"
                                    >
                                        <mt-icon name="regular-trash" size="16px" />
                                    </mt-button>
                                </template>
                            </div>
                        </template>

                        <template #empty-state>
                            <mt-empty-state
                                icon="regular-globe-stand"
                                :headline="t('ct-settings-snippet.setList.cardTitle')"
                            />
                        </template>
                    </mt-data-table>
                </ct-block>
            </template>
        </ct-page>

        <mt-modal-root v-if="snippetSetToDelete" :is-open="true" @change="onDeleteModalChange">
            <mt-modal :title="t('global.default.warning')" width="s">
                {{ t('ct-settings-snippet.setList.textDeleteConfirm', { name: snippetSetToDelete.name }) }}
                <template #footer>
                    <mt-button variant="secondary" @click="closeDeleteModal">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button variant="critical" :is-loading="isLoading" @click="onConfirmDelete">
                        {{ t('global.default.delete') }}
                    </mt-button>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import type SnippetSetApiService from 'src/core/service/api/snippet-set.api.service';
import type { SnippetBaseFile } from 'src/core/service/api/snippet-set.api.service';

import { useNotification } from 'src/app/composables/use-notification';
import './ct-settings-snippet-set-list.scss';

type SortDirection = 'ASC' | 'DESC';
type SnippetSet = Entity<'snippet_set'>;
type Column = {
    property: string;
    label: string;
    renderer: 'text';
    position: number;
    sortable?: boolean;
    width?: number;
};

defineOptions({
    metaInfo() {
        return { title: this.$createTitle() };
    },
});

defineProps({});
const { t, d } = useI18n();
const router = useRouter();
const { createNotificationSuccess, createNotificationError } = useNotification();

const snippetSetService = inject<SnippetSetApiService>('snippetSetService');
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!snippetSetService || !repositoryFactory || !acl) {
    throw new Error('The snippet set services are unavailable.');
}

const snippetSetRepository = computed(() => repositoryFactory.create('snippet_set'));
const isLoading = ref(false);
const term = ref('');
const page = ref(1);
const limit = ref(25);
const total = ref(0);
const sortBy = ref('name');
const sortDirection = ref<SortDirection>('ASC');
const baseFiles = ref<SnippetBaseFile[]>([]);
const snippetSets = ref<SnippetSet[]>([]);
const editingId = ref<string | null>(null);
const selectedIds = ref<string[]>([]);
const snippetSetToDelete = ref<SnippetSet | null>(null);

const snippetSetCriteria = computed(() => {
    const criteria = new Contena.Data.Criteria(page.value, limit.value);
    criteria.addSorting(Contena.Data.Criteria.sort('name', 'ASC'));
    if (term.value) criteria.setTerm(term.value);
    return criteria;
});
const contextMenuEditSnippet = computed(() =>
    acl.can('snippet.editor') ? t('global.default.edit') : t('global.default.view'),
);
const baseFileOptions = computed(() => baseFiles.value.map((file) => ({ value: file.name, label: file.name })));
const snippetSetColumns = computed<Column[]>(() => [
    {
        property: 'name',
        label: t('ct-settings-snippet.setList.columnName'),
        renderer: 'text',
        position: 100,
        width: 300,
    },
    {
        property: 'iso',
        label: t('ct-settings-snippet.setList.columnIso'),
        renderer: 'text',
        position: 200,
        width: 140,
    },
    {
        property: 'baseFile',
        label: t('ct-settings-snippet.setList.columnBaseFile'),
        renderer: 'text',
        position: 300,
        width: 220,
    },
    {
        property: 'updatedAt',
        label: t('ct-settings-snippet.setList.columnChangedAt'),
        renderer: 'text',
        position: 400,
        width: 180,
    },
    { property: 'actions', label: '', renderer: 'text', position: 500, sortable: false, width: 180 },
]);

const loadBaseFiles = async (): Promise<void> => {
    const response = await snippetSetService.getBaseFiles();
    baseFiles.value = Object.values(response.items ?? {})
        .filter((file, index, files) => index === files.findIndex((other) => other.name === file.name))
        .sort((a, b) => a.name.localeCompare(b.name));
};
const getList = async (): Promise<void> => {
    isLoading.value = true;
    try {
        await loadBaseFiles();
        const response = await snippetSetRepository.value.search(snippetSetCriteria.value, Contena.Context.api);
        snippetSets.value = Array.from(response);
        total.value = response.total;
    } finally {
        isLoading.value = false;
    }
};
const onSearch = (value: string): void => {
    term.value = value;
    page.value = 1;
    void getList();
};
const onPageChange = (value: number): void => {
    page.value = value;
    void getList();
};
const onLimitChange = (value: number): void => {
    limit.value = value;
    page.value = 1;
    void getList();
};
const onAddSnippetSet = async (): Promise<void> => {
    if (!baseFiles.value.length) return;
    const newSnippetSet = snippetSetRepository.value.create(Contena.Context.api);
    newSnippetSet.iso = baseFiles.value[0].iso;
    newSnippetSet.baseFile = baseFiles.value[0].name;
    newSnippetSet.name = t('ct-settings-snippet.setList.newSnippetName');
    const baseName = newSnippetSet.name;
    let copyCounter = 1;
    while (snippetSets.value.some((item) => item.name === newSnippetSet.name)) {
        copyCounter += 1;
        newSnippetSet.name = `${baseName} (${copyCounter})`;
    }
    await snippetSetRepository.value.save(newSnippetSet, Contena.Context.api);
    await getList();
    toggleInlineEdit(newSnippetSet.id);
};
const toggleInlineEdit = (id: string): void => {
    if (acl.can('snippet.editor') && snippetSets.value.some((item) => item.id === id)) editingId.value = id;
};
const onInlineEditSave = async (item: SnippetSet): Promise<void> => {
    isLoading.value = true;
    const match = baseFiles.value.find((file) => file.name === item.baseFile);
    try {
        if (!match?.iso) throw new Error('The selected snippet base file is invalid.');
        item.iso = match.iso;
        await snippetSetRepository.value.save(item, Contena.Context.api);
        createNotificationSuccess({
            message: t('ct-settings-snippet.setList.inlineEditSuccessMessage', { name: item.name }),
        });
        editingId.value = null;
        await getList();
    } catch {
        createNotificationError({
            message: t('ct-settings-snippet.setList.inlineEditErrorMessage', { name: item.name }),
        });
        await getList();
    } finally {
        isLoading.value = false;
    }
};
const onInlineEditCancel = (): void => {
    editingId.value = null;
    void getList();
};
const onEditSnippetSets = (): void => {
    if (!selectedIds.value.length) {
        createNotificationError({ message: t('ct-settings-snippet.setList.notEditableNoteErrorMessage') });
        return;
    }
    void router.push({ name: 'ct.settings.snippet.list', query: { ids: selectedIds.value } });
};
const onSelectionChange = (id: string): void => {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((selectedId) => selectedId !== id)
        : [
              ...selectedIds.value,
              id,
          ];
};
const onMultipleSelectionChange = (ids: string[]): void => {
    selectedIds.value = ids;
};
const onDeleteSet = (item: SnippetSet): void => {
    snippetSetToDelete.value = item;
};
const onDeleteModalChange = (open: boolean): void => {
    if (!open) closeDeleteModal();
};
const closeDeleteModal = (): void => {
    snippetSetToDelete.value = null;
};
const onConfirmDelete = async (): Promise<void> => {
    if (!snippetSetToDelete.value) return;
    isLoading.value = true;
    try {
        await snippetSetRepository.value.delete(snippetSetToDelete.value.id, Contena.Context.api);
        createNotificationSuccess({ message: t('ct-settings-snippet.setList.deleteNoteSuccessMessage') });
        closeDeleteModal();
        await getList();
    } catch {
        createNotificationError({ message: t('ct-settings-snippet.setList.deleteNoteErrorMessage') });
    } finally {
        isLoading.value = false;
    }
};
const onConfirmClone = async (id: string): Promise<void> => {
    isLoading.value = true;
    try {
        const clone = await snippetSetRepository.value.clone(id, Contena.Context.api);
        const set = await snippetSetRepository.value.get(clone.id, Contena.Context.api);
        if (!set) return;
        set.name = `${set.name} ${t('ct-settings-snippet.general.copyName')}`;
        const baseName = set.name;
        let copyCounter = 1;
        while (snippetSets.value.some((item) => item.name === set.name)) {
            copyCounter += 1;
            set.name = `${baseName} (${copyCounter})`;
        }
        try {
            await snippetSetRepository.value.save(set, Contena.Context.api);
            createNotificationSuccess({ message: t('ct-settings-snippet.setList.cloneSuccessMessage') });
        } catch {
            await snippetSetRepository.value.delete(set.id, Contena.Context.api);
            throw new Error('Unable to save the cloned snippet set.');
        }
        await getList();
    } catch {
        createNotificationError({ message: t('ct-settings-snippet.setList.cloneErrorMessage') });
    } finally {
        isLoading.value = false;
    }
};
const formatDate = (value: string | null): string => (value ? d(new Date(value), 'short') : '');

void getList();

swDefinePublic({
    acl,
    isLoading,
    term,
    page,
    limit,
    total,
    sortBy,
    sortDirection,
    baseFiles,
    snippetSets,
    editingId,
    selectedIds,
    snippetSetToDelete,
    snippetSetRepository,
    snippetSetCriteria,
    contextMenuEditSnippet,
    baseFileOptions,
    snippetSetColumns,
    getList,
    loadBaseFiles,
    onSearch,
    onPageChange,
    onLimitChange,
    onAddSnippetSet,
    toggleInlineEdit,
    onInlineEditSave,
    onInlineEditCancel,
    onEditSnippetSets,
    onSelectionChange,
    onMultipleSelectionChange,
    onDeleteSet,
    onDeleteModalChange,
    onConfirmDelete,
    closeDeleteModal,
    onConfirmClone,
    formatDate,
});

defineExpose({
    acl,
    isLoading,
    term,
    page,
    limit,
    total,
    sortBy,
    sortDirection,
    baseFiles,
    snippetSets,
    editingId,
    selectedIds,
    snippetSetToDelete,
    snippetSetRepository,
    snippetSetCriteria,
    contextMenuEditSnippet,
    baseFileOptions,
    snippetSetColumns,
    getList,
    loadBaseFiles,
    onSearch,
    onPageChange,
    onLimitChange,
    onAddSnippetSet,
    toggleInlineEdit,
    onInlineEditSave,
    onInlineEditCancel,
    onEditSnippetSets,
    onSelectionChange,
    onMultipleSelectionChange,
    onDeleteSet,
    onDeleteModalChange,
    onConfirmDelete,
    closeDeleteModal,
    onConfirmClone,
    formatDate,
});
</script>

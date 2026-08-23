<template>
    <ct-block name="sw_data_dictionary_list">
        <ct-page class="ct-data-dictionary-list">
            <template #search-bar>
                <mt-search
                    :model-value="term"
                    :placeholder="translate('ct-data-dictionary.list.placeholderSearchBar')"
                    @change="onSearch"
                />
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_data_dictionary_list_smart_bar_header">
                    <h2>
                        {{ translate('ct-settings.index.title') }}
                        <mt-icon name="regular-chevron-right-xs" size="12px" />
                        {{ translate('ct-data-dictionary.general.mainMenuItemGeneral') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_data_dictionary_list_smart_bar_actions">
                    <mt-button
                        variant="primary"
                        :disabled="!acl.can('data_dictionary.creator') || undefined"
                        @click="onCreate"
                    >
                        {{ translate('global.default.add') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="sw_data_dictionary_list_language_switch">
                    <ct-language-switch @on-change="onChangeLanguage" />
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_data_dictionary_list_content">
                    <mt-data-table
                        layout="full"
                        :caption="translate('ct-data-dictionary.general.mainMenuItemGeneral')"
                        :data-source="dictionaries"
                        :columns="columns"
                        :is-loading="isLoading"
                        :pagination-total-items="total"
                        :current-page="page"
                        :pagination-limit="limit"
                        disable-search
                        disable-edit
                        :disable-delete="!acl.can('data_dictionary.deleter')"
                        :additional-context-buttons="additionalContextButtons"
                        @reload="load"
                        @pagination-current-page-change="onPageChange"
                        @pagination-limit-change="onLimitChange"
                        @item-delete="onDelete"
                        @context-select="onContextSelect"
                    >
                        <template #column-label="{ data }">
                            <router-link :to="{ name: 'ct.data.dictionary.detail', params: { id: data.id } }">
                                {{ data.label }}
                            </router-link>
                        </template>
                        <template #column-active="{ data }">
                            <mt-icon
                                :name="data.active ? 'regular-checkmark-xs' : 'regular-times-s'"
                                :class="data.active ? 'is--active' : 'is--inactive'"
                                size="16px"
                            />
                        </template>
                        <template #empty-state>
                            <mt-empty-state
                                icon="regular-bars-square"
                                :headline="translate('ct-data-dictionary.list.emptyTitle')"
                                :description="translate('ct-data-dictionary.list.emptyDescription')"
                            />
                        </template>
                    </mt-data-table>

                    <ct-block name="sw_data_dictionary_list_detail_modal">
                        <ct-data-dictionary-detail
                            v-if="isDetailModalOpen"
                            :dictionary-id="editingDictionaryId"
                            embedded
                            @modal-close="closeDetailModal"
                            @save-success="onDetailSaved"
                        />
                    </ct-block>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>

    <mt-modal-root v-if="itemToDelete" :is-open="true" @change="closeDelete">
        <mt-modal :title="translate('global.default.warning')" width="s">
            <p class="ct-data-dictionary-list__confirm-delete-text">
                {{ translate('global.entity-components.deleteMessage') }}
            </p>
            <template #footer>
                <mt-button variant="secondary" @click="closeDelete">
                    {{ translate('global.default.cancel') }}
                </mt-button>
                <mt-button variant="critical" @click="confirmDelete">
                    {{ translate('global.default.delete') }}
                </mt-button>
            </template>
        </mt-modal>
    </mt-modal-root>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { Criteria } = Contena.Data;
const { t } = useI18n();
const translate = t;
defineProps({});

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const repository = computed(() => repositoryFactory?.create('data_dictionary'));
const dictionaries = ref<EntityCollection<'data_dictionary'> | null>(null);
const total = ref(0);
const isLoading = ref(false);
const term = ref('');
const page = ref(1);
const limit = ref(25);
const itemToDelete = ref<Entity<'data_dictionary'> | null>(null);
const isDetailModalOpen = ref(false);
const editingDictionaryId = ref<string | null>(null);
const columns = computed(() => [
    {
        property: 'label',
        dataIndex: 'label',
        label: translate('ct-data-dictionary.list.columns.label'),
    },
    {
        property: 'technicalName',
        dataIndex: 'technicalName',
        label: translate('ct-data-dictionary.list.columns.technicalName'),
    },
    {
        property: 'description',
        dataIndex: 'description',
        label: translate('ct-data-dictionary.list.columns.description'),
    },
    {
        property: 'active',
        dataIndex: 'active',
        label: translate('ct-data-dictionary.list.columns.active'),
    },
]);
const load = async (): Promise<void> => {
    if (!repository.value) return;
    isLoading.value = true;
    try {
        const criteria = new Criteria(page.value, limit.value);
        criteria.setTerm(term.value);
        criteria.addSorting(Criteria.sort('technicalName', 'ASC'));
        const result = await repository.value.search(criteria);
        dictionaries.value = result;
        total.value = result.total;
    } finally {
        isLoading.value = false;
    }
};
const onSearch = (value: string): void => {
    term.value = value;
    page.value = 1;
    void load();
};
const onChangeLanguage = (): void => {
    void load();
};
const onPageChange = (nextPage: number | { page: number; limit?: number }): void => {
    page.value = typeof nextPage === 'number' ? nextPage : nextPage.page;
    if (typeof nextPage !== 'number' && nextPage.limit) {
        limit.value = nextPage.limit;
    }
    void load();
};
const onLimitChange = (newLimit: number): void => {
    limit.value = newLimit;
    page.value = 1;
    void load();
};
const onCreate = (): void => {
    editingDictionaryId.value = null;
    isDetailModalOpen.value = true;
};
const onEdit = (item: Entity<'data_dictionary'>): void => {
    editingDictionaryId.value = item.id;
    isDetailModalOpen.value = true;
};
const closeDetailModal = (): void => {
    isDetailModalOpen.value = false;
    editingDictionaryId.value = null;
};
const onDetailSaved = async (): Promise<void> => {
    closeDetailModal();
    await load();
};
const showDelete = (item: Entity<'data_dictionary'>): void => {
    itemToDelete.value = item;
};
const closeDelete = (): void => {
    itemToDelete.value = null;
};
const confirmDelete = async (): Promise<void> => {
    if (!itemToDelete.value) return;
    const item = itemToDelete.value;
    itemToDelete.value = null;
    await onDelete(item);
};
const onDelete = async (item: Entity<'data_dictionary'>): Promise<void> => {
    await repository.value?.delete(item.id);
    await load();
};
const additionalContextButtons = computed(() => {
    const buttons = [];
    if (acl?.can('data_dictionary.editor') || acl?.can('data_dictionary.viewer')) {
        buttons.push({
            key: 'edit',
            label: acl.can('data_dictionary.editor') ? translate('global.default.edit') : translate('global.default.view'),
        });
    }
    return buttons;
});
const onContextSelect = ({ key, data }): void => {
    if (key === 'edit') onEdit(data);
};

void load();

swDefinePublic({
    repositoryFactory,
    acl,
    repository,
    dictionaries,
    total,
    isLoading,
    term,
    page,
    limit,
    columns,
    load,
    onSearch,
    onChangeLanguage,
    onPageChange,
    onLimitChange,
    onCreate,
    onEdit,
    isDetailModalOpen,
    editingDictionaryId,
    closeDetailModal,
    onDetailSaved,
    itemToDelete,
    showDelete,
    closeDelete,
    confirmDelete,
    onDelete,
    additionalContextButtons,
    onContextSelect,
});

defineExpose({
    repositoryFactory,
    acl,
    repository,
    dictionaries,
    total,
    isLoading,
    term,
    page,
    limit,
    columns,
    load,
    onSearch,
    onChangeLanguage,
    onPageChange,
    onLimitChange,
    onCreate,
    onEdit,
    isDetailModalOpen,
    editingDictionaryId,
    closeDetailModal,
    onDetailSaved,
    itemToDelete,
    showDelete,
    closeDelete,
    confirmDelete,
    onDelete,
    additionalContextButtons,
    onContextSelect,
});
</script>

<style lang="scss">
.ct-data-dictionary-list {
    .ct-data-grid__cell--active:not(.ct-data-grid__cell--header) .is--inactive {
        color: var(--color-icon-critical-default);
    }

    .ct-data-grid__cell--active:not(.ct-data-grid__cell--header) .is--active {
        color: var(--color-icon-positive-default);
    }

    &__empty-state {
        border-top: 1px solid var(--mt-color-border-primary);
    }

    &__confirm-delete-text {
        margin-bottom: var(--scale-size-24);
    }
}
</style>

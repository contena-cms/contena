<template>
    <ct-block name="sw_data_dictionary_detail">
        <ct-modal
            class="ct-data-dictionary-detail"
            :title="dictionary?.label || translate('ct-data-dictionary.detail.newTitle')"
            variant="large"
            :is-loading="isLoading && !dictionary"
            @modal-close="onCancel"
        >
            <ct-block name="sw_data_dictionary_detail_content">
                <ct-card-view>
                    <ct-block name="sw_data_dictionary_detail_settings_card">
                        <template v-if="dictionary">
                            <mt-card
                                position-identifier="ct-data-dictionary-detail-settings"
                                :title="translate('ct-data-dictionary.detail.settingsTitle')"
                                :subtitle="translate('ct-data-dictionary.detail.settingsSubtitle')"
                                :is-loading="isLoading && !dictionary"
                            >
                                <ct-block name="sw_data_dictionary_detail_settings_fields">
                                    <template v-if="dictionary">
                                        <ct-container
                                            class="ct-data-dictionary-detail__settings-fields"
                                            columns="repeat(2, minmax(0, 1fr))"
                                            gap="0 24px"
                                            :breakpoints="{ 960: { columns: '1fr' } }"
                                        >
                                            <ct-block name="sw_data_dictionary_detail_settings_technical_name">
                                                <mt-text-field
                                                    v-model="dictionary.technicalName"
                                                    :label="translate('ct-data-dictionary.detail.technicalName')"
                                                    :disabled="!canEdit"
                                                    required
                                                />
                                            </ct-block>

                                            <ct-block name="sw_data_dictionary_detail_settings_label">
                                                <mt-text-field
                                                    v-model="dictionary.label"
                                                    :label="translate('ct-data-dictionary.detail.label')"
                                                    :disabled="!canEdit"
                                                    required
                                                />
                                            </ct-block>

                                            <ct-block name="sw_data_dictionary_detail_settings_description">
                                                <mt-textarea
                                                    v-model="dictionary.description"
                                                    class="ct-data-dictionary-detail__settings-description"
                                                    :label="translate('ct-data-dictionary.detail.description')"
                                                    :disabled="!canEdit"
                                                />
                                            </ct-block>

                                            <ct-block name="sw_data_dictionary_detail_settings_active">
                                                <mt-switch
                                                    v-model="dictionary.active"
                                                    :label="translate('ct-data-dictionary.detail.active')"
                                                    :disabled="!canEdit"
                                                />
                                            </ct-block>
                                        </ct-container>
                                    </template>
                                </ct-block>
                            </mt-card>
                        </template>
                    </ct-block>

                    <ct-block name="sw_data_dictionary_detail_items_workspace">
                        <template v-if="dictionary && !dictionary.isNew()">
                            <mt-card
                                class="ct-data-dictionary-detail__tree-card"
                                position-identifier="ct-data-dictionary-detail-tree"
                                :title="translate('ct-data-dictionary.detail.itemsTitle')"
                                :subtitle="translate('ct-data-dictionary.detail.itemsSubtitle')"
                                :is-loading="isLoading"
                            >
                                <template #headerRight>
                                    <ct-block name="sw_data_dictionary_detail_tree_count">
                                        <span class="ct-data-dictionary-detail__tree-count">
                                            {{ translate('ct-data-dictionary.detail.itemCount', { count: items.length }) }}
                                        </span>
                                    </ct-block>
                                </template>

                                <ct-block name="sw_data_dictionary_detail_tree">
                                    <ct-data-dictionary-tree
                                        v-if="dictionary"
                                        :items="treeSourceItems"
                                        :root-id="dictionary.id"
                                        :root-label="dictionary.label"
                                        :active-item-id="selectedItemId"
                                        :is-loading="isLoading"
                                        :can-edit="canEditItem"
                                        :can-create="canCreateItem"
                                        :can-delete="canDeleteItem"
                                        @select-item="selectItem"
                                        @add-child="addItem"
                                        @delete-item="removeItem"
                                        @drag-end="onTreeDragEnd"
                                    />
                                </ct-block>
                            </mt-card>
                        </template>
                    </ct-block>

                    <ct-block name="sw_data_dictionary_detail_item_editor_modal">
                        <ct-data-dictionary-item-modal
                            v-if="isItemEditorOpen && selectedItem"
                            :item="selectedItem"
                            :parent-label="selectedParentLabel"
                            :can-edit="canEditItem"
                            :can-create="canCreateItem"
                            :can-delete="canDeleteItem"
                            @modal-close="closeItemEditor"
                            @save-item="saveItem"
                            @add-child="addItem"
                            @delete-item="removeItemFromEditor"
                        />
                    </ct-block>
                </ct-card-view>
            </ct-block>

            <template #modal-footer>
                <ct-block name="sw_data_dictionary_detail_modal_footer">
                    <ct-block name="sw_data_dictionary_detail_modal_cancel">
                        <mt-button size="small" variant="secondary" @click="onCancel">
                            {{ translate('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_data_dictionary_detail_modal_save">
                        <ct-button-process
                            variant="primary"
                            size="small"
                            :is-loading="isLoading"
                            :disabled="
                                !canEdit || isLoading || !dictionary?.technicalName || !dictionary?.label || hasInvalidItems
                            "
                            @click="onSave"
                        >
                            {{ translate('global.default.save') }}
                        </ct-button-process>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import { computed, inject, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useNotification } from 'src/app/composables/use-notification';

type DictionaryItem = Entity<'data_dictionary_item'>;

const { Criteria } = Contena.Data;
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const translate = t;
const route = useRoute();
const router = useRouter();
const props = defineProps({
    dictionaryId: {
        type: String,
        default: null,
    },
    embedded: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits([
    'modal-close',
    'save-success',
]);

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const repository = computed(() => repositoryFactory?.create('data_dictionary'));
const itemRepository = computed(() => repositoryFactory?.create('data_dictionary_item'));
const dictionary = ref<Entity<'data_dictionary'> | null>(null);
const items = ref<DictionaryItem[]>([]);
const pendingItem = ref<DictionaryItem | null>(null);
const selectedItemId = ref<string | null>(null);
const isItemEditorOpen = ref(false);
const isLoading = ref(false);
const canEdit = computed(() =>
    Boolean(acl?.can('data_dictionary.editor') || (dictionary.value?.isNew() && acl?.can('data_dictionary.creator'))),
);
const canEditItem = computed(() => Boolean(acl?.can('data_dictionary.editor')));
const canCreateItem = computed(() => Boolean(acl?.can('data_dictionary.creator') || acl?.can('data_dictionary.editor')));
const canDeleteItem = computed(() => Boolean(acl?.can('data_dictionary.deleter')));
const dictionaryRouteId = computed(() => props.dictionaryId || (route.params.id ? String(route.params.id) : null));
const selectedItem = computed(
    () => pendingItem.value ?? items.value.find((item) => item.id === selectedItemId.value) ?? null,
);
const selectedParentLabel = computed(() => {
    if (!selectedItem.value?.parentId) {
        return dictionary.value?.label || translate('ct-data-dictionary.detail.rootLevel');
    }

    const parent = items.value.find((item) => item.id === selectedItem.value?.parentId);

    return parent?.label || parent?.code || translate('ct-data-dictionary.detail.rootLevel');
});
const treeSourceItems = computed(() => {
    const childCounts = new Map<string, number>();
    const sortedItems = [...items.value].sort(
        (left, right) => (left.position ?? 0) - (right.position ?? 0) || left.id.localeCompare(right.id),
    );
    const previousSiblingIds = new Map<string, string>();

    for (const item of sortedItems) {
        if (item.parentId) {
            childCounts.set(item.parentId, (childCounts.get(item.parentId) ?? 0) + 1);
        }
    }

    return sortedItems.map((item) => {
        const siblingKey = item.parentId ?? 'root';
        const afterId = previousSiblingIds.get(siblingKey) ?? null;
        previousSiblingIds.set(siblingKey, item.id);

        return {
            id: item.id,
            parentId: item.parentId ?? null,
            childCount: childCounts.has(item.id) ? childCounts.get(item.id) : (item.childCount ?? 0),
            level: item.level ?? (item.parentId ? 2 : 1),
            path: item.path ?? null,
            afterId,
            entity: item,
            name: item.label || item.code,
        };
    });
});
const hasInvalidItems = computed(
    () =>
        items.value.some((item) => !item.code.trim() || !item.label.trim()) ||
        Boolean(pendingItem.value && (!pendingItem.value.code.trim() || !pendingItem.value.label.trim())),
);
const load = async (): Promise<void> => {
    if (!repository.value || !itemRepository.value) return;
    isLoading.value = true;
    isItemEditorOpen.value = false;
    try {
        if (dictionaryRouteId.value) {
            dictionary.value = await repository.value.get(dictionaryRouteId.value);
            const itemCriteria = new Criteria(1, 500);
            itemCriteria.addFilter(Criteria.equals('dictionaryId', dictionary.value.id));
            itemCriteria.addSorting(Criteria.sort('position', 'ASC'));
            items.value = Array.from(await itemRepository.value.search(itemCriteria));
            selectedItemId.value = treeSourceItems.value[0]?.id ?? null;
        } else {
            dictionary.value = repository.value.create();
            dictionary.value.technicalName = '';
            dictionary.value.label = '';
            dictionary.value.description = '';
            dictionary.value.active = true;
            items.value = [];
            selectedItemId.value = null;
        }
    } finally {
        isLoading.value = false;
    }
};
const selectItem = (item: DictionaryItem): void => {
    selectedItemId.value = item.id;
    isItemEditorOpen.value = true;
};
const closeItemEditor = (): void => {
    isItemEditorOpen.value = false;
    pendingItem.value = null;
};
const addItem = (parent: DictionaryItem | null = null): void => {
    if (!dictionary.value || !itemRepository.value) return;
    const siblings = items.value.filter((item) => (item.parentId ?? null) === (parent?.id ?? null));
    const item = itemRepository.value.create();
    item.dictionaryId = dictionary.value.id;
    item.parentId = parent?.id;
    item.level = (parent?.level ?? 0) + 1;
    item.childCount = 0;
    item.path = null;
    item.code = '';
    item.label = '';
    item.description = '';
    item.position = Math.max(0, ...siblings.map((sibling) => sibling.position ?? 0)) + 1;
    item.active = true;
    pendingItem.value = item;
    selectedItemId.value = item.id;
    isItemEditorOpen.value = true;
};
const saveItem = (item: DictionaryItem): void => {
    if (pendingItem.value?.id === item.id && !items.value.some((candidate) => candidate.id === item.id)) {
        items.value.push(item);
    }

    pendingItem.value = null;
};
const getBranchItems = (item: DictionaryItem): DictionaryItem[] => {
    const branch = [item];

    for (const child of items.value.filter((candidate) => candidate.parentId === item.id)) {
        branch.push(...getBranchItems(child));
    }

    return branch;
};
const removeItem = async (item: DictionaryItem): Promise<void> => {
    const branch = getBranchItems(item);

    for (const branchItem of [...branch].reverse()) {
        if (!branchItem.isNew()) {
            await itemRepository.value?.delete(branchItem.id);
        }
    }

    const removedIds = new Set(branch.map((branchItem) => branchItem.id));
    items.value = items.value.filter((candidate) => !removedIds.has(candidate.id));
    selectedItemId.value = item.parentId ?? treeSourceItems.value[0]?.id ?? null;
};
const removeItemFromEditor = async (item: DictionaryItem): Promise<void> => {
    closeItemEditor();
    await removeItem(item);
};
const onTreeDragEnd = (payload: unknown): void => {
    if (!payload || typeof payload !== 'object') {
        return;
    }

    const { draggedItem, oldParentId, newParentId } = payload as {
        draggedItem?: { data?: { id?: string; afterId?: string | null } };
        oldParentId?: string | null;
        newParentId?: string | null;
    };
    const draggedId = draggedItem?.data?.id;
    const draggedEntity = items.value.find((item) => item.id === draggedId);

    if (!draggedEntity) {
        return;
    }

    const targetParentId = newParentId ?? null;
    const afterId = draggedItem?.data?.afterId ?? null;
    draggedEntity.parentId = targetParentId;

    const normalizeSiblings = (parentId: string | null, insertDragged: boolean): void => {
        const siblings = items.value
            .filter((item) => item.id !== draggedEntity.id && (item.parentId ?? null) === parentId)
            .sort((left, right) => (left.position ?? 0) - (right.position ?? 0) || left.id.localeCompare(right.id));

        if (insertDragged) {
            const afterIndex = afterId ? siblings.findIndex((item) => item.id === afterId) : -1;
            siblings.splice(afterIndex + 1, 0, draggedEntity);
        }

        siblings.forEach((item, index) => {
            item.position = index + 1;
        });
    };

    normalizeSiblings(targetParentId, true);
    if ((oldParentId ?? null) !== targetParentId) {
        normalizeSiblings(oldParentId ?? null, false);
    }
};
const getItemsInSaveOrder = (): DictionaryItem[] => {
    const sortedItems: DictionaryItem[] = [];
    const appendChildren = (parentId: string | null): void => {
        items.value
            .filter((item) => (item.parentId ?? null) === parentId)
            .sort((left, right) => (left.position ?? 0) - (right.position ?? 0) || left.id.localeCompare(right.id))
            .forEach((item) => {
                sortedItems.push(item);
                appendChildren(item.id);
            });
    };

    appendChildren(null);

    return sortedItems;
};
const onSave = async (): Promise<void> => {
    if (!dictionary.value || !repository.value || !itemRepository.value || hasInvalidItems.value) return;
    isLoading.value = true;
    try {
        await repository.value.save(dictionary.value);
        dictionary.value = await repository.value.get(dictionary.value.id);
        for (const item of getItemsInSaveOrder()) {
            item.dictionaryId = dictionary.value.id;
            await itemRepository.value.save(item);
        }
        createNotificationSuccess({
            title: translate('global.default.success'),
            message: translate('ct-data-dictionary.notification.saveSuccess'),
        });
        emit('save-success', dictionary.value);
        if (props.embedded) {
            emit('modal-close');
        } else {
            await router.push({ name: 'ct.data.dictionary.index' });
        }
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-data-dictionary.notification.saveError'),
        });
    } finally {
        isLoading.value = false;
    }
};
const onCancel = (): void => {
    if (props.embedded) {
        emit('modal-close');

        return;
    }

    void router.push({ name: 'ct.data.dictionary.index' });
};

watch(
    [
        () => route.params.id,
        () => props.dictionaryId,
    ],
    () => {
        void load();
    },
);

void load();

swDefinePublic({
    repositoryFactory,
    acl,
    repository,
    itemRepository,
    dictionaryRouteId,
    dictionary,
    items,
    selectedItemId,
    selectedItem,
    selectedParentLabel,
    isItemEditorOpen,
    pendingItem,
    treeSourceItems,
    hasInvalidItems,
    isLoading,
    canEdit,
    canEditItem,
    canCreateItem,
    canDeleteItem,
    load,
    selectItem,
    closeItemEditor,
    saveItem,
    removeItemFromEditor,
    addItem,
    getBranchItems,
    removeItem,
    onTreeDragEnd,
    getItemsInSaveOrder,
    onSave,
    onCancel,
});

defineExpose({
    repositoryFactory,
    acl,
    repository,
    itemRepository,
    dictionaryRouteId,
    dictionary,
    items,
    selectedItemId,
    selectedItem,
    selectedParentLabel,
    isItemEditorOpen,
    pendingItem,
    treeSourceItems,
    hasInvalidItems,
    isLoading,
    canEdit,
    canEditItem,
    canCreateItem,
    canDeleteItem,
    load,
    selectItem,
    closeItemEditor,
    saveItem,
    removeItemFromEditor,
    addItem,
    getBranchItems,
    removeItem,
    onTreeDragEnd,
    getItemsInSaveOrder,
    onSave,
    onCancel,
});
</script>

<style lang="scss">
.ct-data-dictionary-detail {
    &.ct-modal {
        .ct-modal__dialog {
            height: min(760px, calc(100vh - 64px));
            min-height: 0;
        }

        .ct-modal__body {
            padding: 0;
        }

        .ct-card-view {
            position: relative;
            height: 100%;
        }

        .ct-card-view__content {
            height: auto;
            min-height: 100%;
            overflow: visible;
            padding: 24px 30px;
        }
    }

    &__settings-fields {
        align-items: start;
    }

    &__settings-description {
        grid-column: 1 / -1;
    }

    &__tree-count {
        color: var(--color-text-secondary-default);
        font-size: var(--mt-font-size-xs);
        white-space: nowrap;
    }

    @media (max-width: 960px) {
        &__settings-description {
            grid-column: auto;
        }
    }
}
</style>

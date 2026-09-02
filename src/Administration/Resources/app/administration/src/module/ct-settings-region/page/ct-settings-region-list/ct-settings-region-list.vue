<template>
    <ct-block name="ct_settings_region_list">
        <ct-page class="ct-settings-region-list">
            <template #search-bar>
                <ct-block name="ct_settings_region_list_search_bar">
                    <mt-search
                        :model-value="term"
                        :placeholder="translate('ct-settings-region.list.searchPlaceholder')"
                        @change="onSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_settings_region_list_header">
                    <h2>
                        {{ translate('ct-settings.index.title') }}
                        <mt-icon name="regular-chevron-right-xs" size="12px" />
                        {{ translate('ct-settings-region.general.mainMenuItemGeneral') }}
                    </h2>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="ct_settings_region_list_context">
                    <div class="ct-settings-region-list__context">
                        <mt-entity-select
                            v-model="selectedCountryId"
                            class="ct-settings-region-list__country-select"
                            entity="country"
                            label-property="name"
                            small
                            :placeholder="translate('ct-settings-region.list.countryPlaceholder')"
                            @update:model-value="onCountryChange"
                        />
                        <ct-language-switch @on-change="onChangeLanguage" />
                    </div>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="ct_settings_region_list_actions">
                    <mt-button
                        v-tooltip.bottom="createTooltip"
                        class="ct-settings-region-list__add-root-action"
                        variant="secondary"
                        :disabled="!selectedCountryId || !canCreate || undefined"
                        @click="onAddRegion"
                    >
                        {{ translate('global.default.add') }}
                    </mt-button>
                    <mt-button
                        class="ct-settings-region-list__cancel-action"
                        variant="secondary"
                        :disabled="!currentRegion || undefined"
                        @click="onCancelRegion"
                    >
                        {{ translate('global.default.cancel') }}
                    </mt-button>
                    <mt-button
                        class="ct-settings-region-list__save-action"
                        variant="primary"
                        :disabled="!canSaveCurrent || !currentRegion?.name || !currentRegion?.type || undefined"
                        @click="onSaveRegion"
                    >
                        {{ translate('global.default.save') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_settings_region_list_content">
                    <ct-card-view>
                        <div class="ct-settings-region-list__workspace">
                            <ct-block name="ct_settings_region_list_tree_panel">
                                <mt-card
                                    class="ct-settings-region-list__tree-panel"
                                    position-identifier="ct-settings-region-list-tree"
                                    :title="translate('ct-settings-region.list.treeTitle')"
                                    :is-loading="isLoading"
                                >
                                    <template #grid>
                                        <ct-region-tree
                                            v-if="selectedCountryId && treeItems.length > 0"
                                            :items="treeItems"
                                            :selected-region-id="selectedRegionId"
                                            :can-create="canCreate"
                                            :can-edit="canEdit"
                                            :can-delete="canDelete"
                                            @load-children="onLoadRegionChildren"
                                            @select-region="onSelectTreeRegion"
                                            @add-child-region="onAddChildRegion"
                                            @delete-region="onDeleteTreeRegion"
                                            @batch-delete="onBatchDeleteTreeRegions"
                                        />

                                        <mt-empty-state
                                            v-else-if="!isLoading && !selectedCountryId"
                                            icon="regular-globe"
                                            :headline="translate('ct-settings-region.list.selectCountryTitle')"
                                            :description="translate('ct-settings-region.list.selectCountryDescription')"
                                        />
                                        <mt-empty-state
                                            v-else-if="!isLoading"
                                            icon="regular-sitemap"
                                            :headline="translate('ct-settings-region.list.emptyTitle')"
                                            :description="translate('ct-settings-region.list.emptyDescription')"
                                        />
                                    </template>
                                </mt-card>
                            </ct-block>

                            <ct-block name="ct_settings_region_list_form_panel">
                                <mt-card
                                    class="ct-settings-region-list__form-panel"
                                    position-identifier="ct-settings-region-list-form"
                                    :title="detailTitle"
                                    :is-loading="isLoading"
                                >
                                    <ct-region-form
                                        v-if="currentRegion"
                                        :region="currentRegion"
                                        :custom-field-sets="customFieldSets"
                                        :disabled="!canSaveCurrent"
                                        @update:region="onUpdateRegion"
                                    />
                                    <mt-empty-state
                                        v-else
                                        icon="regular-map"
                                        :headline="translate('ct-settings-region.list.selectRegionTitle')"
                                        :description="translate('ct-settings-region.list.selectRegionDescription')"
                                    />
                                </mt-card>
                            </ct-block>
                        </div>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

type Region = Entity<'region'>;
type TreeItem = Region & { afterId: string | null; childCount: number };
type RenderedTreeItem = { id: string; data: Region };
interface CustomFieldDataProviderService {
    // The tuple documents the injected service contract.

    getCustomFieldSets: (...args: [string]) => Promise<unknown[]>;
}

const { Criteria } = Contena.Data;
defineProps({});
const { t } = useI18n();
const translate = t;
const { createNotificationError, createNotificationSuccess } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const customFieldDataProviderService = inject<CustomFieldDataProviderService>('customFieldDataProviderService');
const regionRepository = computed(() => repositoryFactory?.create('region'));
const countryRepository = computed(() => repositoryFactory?.create('country'));
const selectedCountryId = ref<string | null>(null);
const term = ref('');
const isLoading = ref(false);
const treeItems = ref<TreeItem[]>([]);
const loadedParents = ref<Record<string, boolean>>({});
const loadingParents = ref<Record<string, Promise<void>>>({});
const treeGeneration = ref(0);
const currentRegion = ref<Region | null>(null);
const customFieldSets = ref<unknown[]>([]);
const canCreate = computed(() => Boolean(acl?.can('region.creator')));
const canEdit = computed(() => Boolean(acl?.can('region.editor')));
const canDelete = computed(() => Boolean(acl?.can('region.deleter')));
const canSaveCurrent = computed(() =>
    currentRegion.value?.isNew() ? canCreate.value : Boolean(currentRegion.value) && canEdit.value,
);
const selectedRegionId = computed(() =>
    currentRegion.value && !currentRegion.value.isNew() ? currentRegion.value.id : null,
);
const detailTitle = computed(() => {
    if (!currentRegion.value) return translate('ct-settings-region.list.detailTitle');
    return currentRegion.value.isNew()
        ? translate('ct-settings-region.detail.titleNew')
        : translate('ct-settings-region.detail.titleEdit');
});
const createTooltip = computed(() => ({
    message: translate('ct-privileges.tooltip.warning'),
    disabled: canCreate.value,
    showOnDisabledElements: true,
}));

const loadInitialCountry = async (): Promise<void> => {
    if (!countryRepository.value) return;

    const chinaCriteria = new Criteria(1, 1);
    chinaCriteria.addFilter(Criteria.equals('iso', 'CN'));
    let countries = await countryRepository.value.search(chinaCriteria, Contena.Context.api);

    if (countries.length === 0) {
        const fallbackCriteria = new Criteria(1, 1);
        fallbackCriteria.addSorting(Criteria.sort('position', 'ASC'));
        countries = await countryRepository.value.search(fallbackCriteria, Contena.Context.api);
    }

    selectedCountryId.value = countries.first()?.id ?? null;
    await resetTree();
};
const loadCustomFieldSets = async (): Promise<void> => {
    customFieldSets.value = (await customFieldDataProviderService?.getCustomFieldSets('region')) ?? [];
};
const onCountryChange = async (countryId: string | null): Promise<void> => {
    selectedCountryId.value = countryId;
    term.value = '';
    currentRegion.value = null;
    await resetTree();
};
const onSearch = async (searchTerm: string): Promise<void> => {
    term.value = searchTerm;
    currentRegion.value = null;
    await resetTree();
};
const onChangeLanguage = async (): Promise<void> => {
    const selectedId = selectedRegionId.value;
    currentRegion.value = null;
    await resetTree();

    if (selectedId && regionRepository.value) {
        currentRegion.value = await regionRepository.value.get(selectedId, Contena.Context.api);
    }
};
const getCriteria = (parentId: string | null = null): InstanceType<typeof Criteria> => {
    const criteria = new Criteria(1, term.value ? 500 : 100);
    criteria.addFilter(Criteria.equals('countryId', selectedCountryId.value));

    if (!term.value) criteria.addFilter(Criteria.equals('parentId', parentId));

    criteria.addSorting(Criteria.sort('position', 'ASC'));
    if (term.value) criteria.setTerm(term.value);

    return criteria;
};
const resetTree = async (): Promise<void> => {
    treeGeneration.value += 1;
    treeItems.value = [];
    loadedParents.value = {};
    loadingParents.value = {};

    if (selectedCountryId.value) await loadTreeItems();
};
const loadTreeItems = (parentId: string | null = null): Promise<void> => {
    if (!selectedCountryId.value || !regionRepository.value) return Promise.resolve();

    const parentKey = term.value ? '__search__' : (parentId ?? '__root__');
    if (loadedParents.value[parentKey]) return Promise.resolve();
    if (loadingParents.value[parentKey] !== undefined) return loadingParents.value[parentKey];

    const generation = treeGeneration.value;
    isLoading.value = true;
    const request = regionRepository.value
        .search(getCriteria(parentId), Contena.Context.api)
        .then((regions) => {
            if (generation !== treeGeneration.value) return;

            let previousId: string | null = null;
            const loadedItems = Array.from(regions).map((region) => {
                const item = {
                    ...region,
                    parentId: term.value ? null : region.parentId,
                    afterId: previousId,
                    childCount: term.value ? 0 : Number(region.childCount ?? 0),
                } as TreeItem;
                previousId = item.id;
                return item;
            });
            const loadedIds = new Set(loadedItems.map((item) => item.id));
            treeItems.value = [
                ...treeItems.value.filter((item) => !loadedIds.has(item.id)),
                ...loadedItems,
            ];
            loadedParents.value[parentKey] = true;
        })
        .catch(() => {
            createNotificationError({
                title: translate('global.default.error'),
                message: translate('ct-settings-region.notification.loadError'),
            });
        })
        .finally(() => {
            delete loadingParents.value[parentKey];
            isLoading.value = Object.keys(loadingParents.value).length > 0;
        });

    loadingParents.value[parentKey] = request;
    return request;
};
const onLoadRegionChildren = (parentId: string): Promise<void> => loadTreeItems(parentId);
const createRegion = (parentId: string | null): void => {
    if (!selectedCountryId.value || !regionRepository.value) return;
    const region = regionRepository.value.create(Contena.Context.api);
    region.countryId = selectedCountryId.value;
    region.parentId = parentId;
    region.type = 'region';
    region.position = 1;
    region.active = true;
    currentRegion.value = region;
};
const onAddRegion = (): void => createRegion(null);
const onAddChildRegion = (treeItem: RenderedTreeItem): void => createRegion(treeItem.data.id);
const onSelectTreeRegion = async (treeItem: RenderedTreeItem): Promise<void> => {
    if (!regionRepository.value) return;
    isLoading.value = true;
    try {
        currentRegion.value = await regionRepository.value.get(treeItem.data.id, Contena.Context.api);
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-region.notification.loadError'),
        });
    } finally {
        isLoading.value = false;
    }
};
const onUpdateRegion = (path: string, value: unknown): void => {
    if (currentRegion.value) Contena.Utils.object.set(currentRegion.value, path, value);
};
const onSaveRegion = async (): Promise<void> => {
    if (!regionRepository.value || !currentRegion.value || !canSaveCurrent.value) return;
    isLoading.value = true;
    try {
        const regionId = currentRegion.value.id;
        await regionRepository.value.save(currentRegion.value, Contena.Context.api);
        await resetTree();
        currentRegion.value = await regionRepository.value.get(regionId, Contena.Context.api);
        createNotificationSuccess({
            title: translate('global.default.success'),
            message: translate('ct-settings-region.notification.saveSuccess'),
        });
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-region.notification.saveError'),
        });
    } finally {
        isLoading.value = false;
    }
};
const onCancelRegion = (): void => {
    currentRegion.value = null;
};
const onDeleteTreeRegion = (treeItem: RenderedTreeItem): Promise<void> => deleteRegions([treeItem.data.id]);
const onBatchDeleteTreeRegions = (selection: unknown): Promise<void> => {
    const selectedItems = Array.isArray(selection) ? selection : Object.values((selection ?? {}) as Record<string, unknown>);
    const ids = selectedItems
        .map((item) => {
            if (typeof item === 'string') return item;

            const candidate = item as { id?: string; data?: { id?: string } };
            return candidate.data?.id ?? candidate.id ?? null;
        })
        .filter((id): id is string => Boolean(id));

    return deleteRegions(ids);
};
const deleteRegions = async (ids: string[]): Promise<void> => {
    if (!regionRepository.value || ids.length === 0) return;
    isLoading.value = true;
    try {
        await regionRepository.value.syncDeleted(ids, Contena.Context.api);
        currentRegion.value = null;
        await resetTree();
        createNotificationSuccess({
            title: translate('global.default.success'),
            message: translate('ct-settings-region.notification.deleteSuccess'),
        });
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-region.notification.deleteError'),
        });
    } finally {
        isLoading.value = false;
    }
};

void Promise.all([
    loadInitialCountry(),
    loadCustomFieldSets(),
]);

ctDefinePublic({
    repositoryFactory,
    acl,
    regionRepository,
    countryRepository,
    selectedCountryId,
    term,
    isLoading,
    treeItems,
    loadedParents,
    loadingParents,
    treeGeneration,
    currentRegion,
    customFieldSets,
    canCreate,
    canEdit,
    canDelete,
    canSaveCurrent,
    selectedRegionId,
    detailTitle,
    createTooltip,
    loadInitialCountry,
    loadCustomFieldSets,
    onCountryChange,
    onSearch,
    onChangeLanguage,
    getCriteria,
    resetTree,
    loadTreeItems,
    onLoadRegionChildren,
    onAddRegion,
    onAddChildRegion,
    createRegion,
    onSelectTreeRegion,
    onUpdateRegion,
    onSaveRegion,
    onCancelRegion,
    onDeleteTreeRegion,
    onBatchDeleteTreeRegions,
    deleteRegions,
});

defineExpose({
    repositoryFactory,
    acl,
    regionRepository,
    countryRepository,
    selectedCountryId,
    term,
    isLoading,
    treeItems,
    loadedParents,
    loadingParents,
    treeGeneration,
    currentRegion,
    customFieldSets,
    canCreate,
    canEdit,
    canDelete,
    canSaveCurrent,
    selectedRegionId,
    detailTitle,
    createTooltip,
    loadInitialCountry,
    loadCustomFieldSets,
    onCountryChange,
    onSearch,
    onChangeLanguage,
    getCriteria,
    resetTree,
    loadTreeItems,
    onLoadRegionChildren,
    onAddRegion,
    onAddChildRegion,
    createRegion,
    onSelectTreeRegion,
    onUpdateRegion,
    onSaveRegion,
    onCancelRegion,
    onDeleteTreeRegion,
    onBatchDeleteTreeRegions,
    deleteRegions,
});
</script>

<style lang="scss">
.ct-settings-region-list {
    &__context {
        display: flex;
        align-items: center;
        gap: var(--scale-size-8);
    }

    &__country-select {
        width: 240px;
    }

    &__workspace {
        display: grid;
        grid-template-columns: minmax(320px, 420px) minmax(520px, 1fr);
        gap: var(--scale-size-24);
        width: 100%;
        max-width: 1440px;
        min-height: 620px;
        margin-right: auto;
        margin-left: auto;
    }

    &__tree-panel,
    &__form-panel {
        width: 100%;
        max-width: none;
        margin: 0;
    }

    @media screen and (max-width: 1100px) {
        &__workspace {
            grid-template-columns: 1fr;
        }
    }
}
</style>

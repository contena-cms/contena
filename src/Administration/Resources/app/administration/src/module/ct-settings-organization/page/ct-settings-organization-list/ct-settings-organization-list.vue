<template>
    <ct-block name="sw_settings_organization_list">
        <ct-page class="ct-settings-organization-list">
            <template #search-bar>
                <ct-block name="sw_settings_organization_list_search_bar">
                    <mt-search
                        :model-value="term"
                        :placeholder="translate('ct-settings-organization.list.searchPlaceholder')"
                        @change="onSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_settings_organization_list_header">
                    <h2>
                        {{ translate('ct-settings.index.title') }}
                        <mt-icon name="regular-chevron-right-xs" size="12px" />
                        {{ translate('ct-settings-organization.general.mainMenuItemGeneral') }}
                    </h2>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="sw_settings_organization_list_language">
                    <ct-language-switch @on-change="onChangeLanguage" />
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_organization_list_actions">
                    <mt-button
                        v-tooltip.bottom="createTooltip"
                        class="ct-settings-organization-list__add-action"
                        variant="secondary"
                        :disabled="!canAddOrganization || undefined"
                        @click="onAddOrganization"
                    >
                        {{ translate('global.default.add') }}
                    </mt-button>
                    <mt-button
                        class="ct-settings-organization-list__cancel-action"
                        variant="secondary"
                        :disabled="!currentOrganization || undefined"
                        @click="onCancelOrganization"
                    >
                        {{ translate('global.default.cancel') }}
                    </mt-button>
                    <mt-button
                        class="ct-settings-organization-list__save-action"
                        variant="primary"
                        :disabled="
                            !canSaveCurrent ||
                            !currentOrganization?.name ||
                            !currentOrganization?.code ||
                            !currentOrganization?.organizationUnitId ||
                            undefined
                        "
                        @click="onSaveOrganization"
                    >
                        {{ translate('global.default.save') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_organization_list_content">
                    <ct-card-view>
                        <div class="ct-settings-organization-list__workspace">
                            <ct-block name="sw_settings_organization_list_tree_panel">
                                <mt-card
                                    class="ct-settings-organization-list__tree-panel"
                                    position-identifier="ct-settings-organization-list-tree"
                                    :title="translate('ct-settings-organization.list.treeTitle')"
                                    :is-loading="isLoading"
                                >
                                    <template #grid>
                                        <mt-organization-tree
                                            v-if="treeItems.length > 0"
                                            :items="treeItems"
                                            :selected-organization-id="selectedOrganizationId"
                                            :can-create="canCreate"
                                            :can-edit="canEdit"
                                            :can-delete="canDelete"
                                            @load-children="loadTreeItems"
                                            @select-organization="onSelectOrganization"
                                            @add-child="onAddChildOrganization"
                                            @delete-organization="onDeleteOrganization"
                                            @batch-delete="deleteOrganizations"
                                        />

                                        <mt-empty-state
                                            v-else-if="!isLoading"
                                            icon="regular-user"
                                            :headline="translate('ct-settings-organization.list.emptyTitle')"
                                            :description="translate('ct-settings-organization.list.emptyDescription')"
                                        />
                                    </template>
                                </mt-card>
                            </ct-block>

                            <ct-block name="sw_settings_organization_list_form_panel">
                                <mt-card
                                    class="ct-settings-organization-list__form-panel"
                                    position-identifier="ct-settings-organization-list-form"
                                    :title="detailTitle"
                                    :is-loading="isLoading"
                                >
                                    <mt-organization-form
                                        v-if="currentOrganization"
                                        :organization="currentOrganization"
                                        :custom-field-sets="customFieldSets"
                                        :disabled="!canSaveCurrent"
                                        @update:organization="onUpdateOrganization"
                                    />
                                    <mt-empty-state
                                        v-else
                                        icon="regular-user"
                                        :headline="translate('ct-settings-organization.list.selectOrganizationTitle')"
                                        :description="
                                            translate('ct-settings-organization.list.selectOrganizationDescription')
                                        "
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

type Organization = Entity<'organization'>;
type OrganizationUnit = Entity<'organization_unit'>;
type TreeItem = Organization & { afterId: string | null; childCount: number };
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
const organizationRepository = computed(() => repositoryFactory?.create('organization'));
const organizationUnitRepository = computed(() => repositoryFactory?.create('organization_unit'));
const term = ref('');
const isLoading = ref(false);
const treeItems = ref<TreeItem[]>([]);
const loadedParents = ref(new Set<string>());
const loadingParents = ref(new Map<string, Promise<void>>());
const treeGeneration = ref(0);
const currentOrganization = ref<Organization | null>(null);
const organizationUnits = ref<OrganizationUnit[]>([]);
const customFieldSets = ref<unknown[]>([]);
const canCreate = computed(() => Boolean(acl?.can('organization.creator')));
const canEdit = computed(() => Boolean(acl?.can('organization.editor')));
const canDelete = computed(() => Boolean(acl?.can('organization.deleter')));
const canAddOrganization = computed(() => canCreate.value && organizationUnits.value.length > 0);
const canSaveCurrent = computed(() =>
    currentOrganization.value?.isNew() ? canCreate.value : Boolean(currentOrganization.value) && canEdit.value,
);
const selectedOrganizationId = computed(() =>
    currentOrganization.value && !currentOrganization.value.isNew() ? currentOrganization.value.id : null,
);
const detailTitle = computed(() => {
    if (!currentOrganization.value) return translate('ct-settings-organization.list.detailTitle');
    return currentOrganization.value.isNew()
        ? translate('ct-settings-organization.detail.titleNew')
        : translate('ct-settings-organization.detail.titleEdit');
});
const createTooltip = computed(() => ({
    message: translate('ct-privileges.tooltip.warning'),
    disabled: canAddOrganization.value,
    showOnDisabledElements: true,
}));

const loadOrganizationUnits = async (): Promise<void> => {
    if (!organizationUnitRepository.value) return;
    const criteria = new Criteria(1, 100);
    criteria.addFilter(Criteria.equals('active', true));
    criteria.addSorting(Criteria.sort('position', 'ASC'));
    organizationUnits.value = Array.from(await organizationUnitRepository.value.search(criteria, Contena.Context.api));
};
const loadCustomFieldSets = async (): Promise<void> => {
    customFieldSets.value = (await customFieldDataProviderService?.getCustomFieldSets('organization')) ?? [];
};
const getCriteria = (parentId: string | null = null): InstanceType<typeof Criteria> => {
    const criteria = new Criteria(1, term.value ? 500 : 100);
    if (!term.value) criteria.addFilter(Criteria.equals('parentId', parentId));
    criteria.addAssociation('organizationUnit');
    criteria.addSorting(Criteria.sort('position', 'ASC'));
    if (term.value) criteria.setTerm(term.value);
    return criteria;
};
const resetTree = async (): Promise<void> => {
    treeGeneration.value += 1;
    treeItems.value = [];
    loadedParents.value = new Set<string>();
    loadingParents.value = new Map<string, Promise<void>>();
    await loadTreeItems();
};
const loadTreeItems = (parentId: string | null = null): Promise<void> => {
    if (!organizationRepository.value) return Promise.resolve();

    const parentKey = term.value ? '__search__' : (parentId ?? '__root__');
    if (loadedParents.value.has(parentKey)) return Promise.resolve();
    const loading = loadingParents.value.get(parentKey);
    if (loading) return loading;

    const generation = treeGeneration.value;
    isLoading.value = true;
    const request = organizationRepository.value
        .search(getCriteria(parentId), Contena.Context.api)
        .then((organizations) => {
            if (generation !== treeGeneration.value) return;

            let previousId: string | null = null;
            const loadedItems = Array.from(organizations).map((organization) => {
                const item = {
                    ...organization,
                    parentId: term.value ? null : organization.parentId,
                    afterId: previousId,
                    childCount: term.value ? 0 : Number(organization.childCount ?? 0),
                } as TreeItem;
                previousId = item.id;
                return item;
            });
            const loadedIds = new Set(loadedItems.map((item) => item.id));
            treeItems.value = [
                ...treeItems.value.filter((item) => !loadedIds.has(item.id)),
                ...loadedItems,
            ];
            loadedParents.value = new Set([
                ...loadedParents.value,
                parentKey,
            ]);
        })
        .catch(() => {
            createNotificationError({
                title: translate('global.default.error'),
                message: translate('ct-settings-organization.notification.loadError'),
            });
        })
        .finally(() => {
            const next = new Map(loadingParents.value);
            next.delete(parentKey);
            loadingParents.value = next;
            isLoading.value = next.size > 0;
        });

    loadingParents.value = new Map(loadingParents.value).set(parentKey, request);
    return request;
};
const onSearch = async (searchTerm: string): Promise<void> => {
    term.value = searchTerm;
    currentOrganization.value = null;
    await resetTree();
};
const onChangeLanguage = async (): Promise<void> => {
    const selectedId = selectedOrganizationId.value;
    currentOrganization.value = null;
    await Promise.all([
        resetTree(),
        loadOrganizationUnits(),
    ]);
    if (selectedId && organizationRepository.value) {
        currentOrganization.value = await organizationRepository.value.get(selectedId, Contena.Context.api);
    }
};
const getDefaultUnitId = (parentId: string | null): string | null => {
    const technicalName = parentId ? 'department' : 'company';
    return (
        organizationUnits.value.find((unit) => unit.technicalName === technicalName)?.id ??
        organizationUnits.value[0]?.id ??
        null
    );
};
const createOrganization = (parentId: string | null): void => {
    if (!organizationRepository.value) return;
    const organizationUnitId = getDefaultUnitId(parentId);
    if (!organizationUnitId) return;

    const organization = organizationRepository.value.create(Contena.Context.api);
    organization.parentId = parentId;
    organization.organizationUnitId = organizationUnitId;
    organization.position = 1;
    organization.active = true;
    currentOrganization.value = organization;
};
const onAddOrganization = (): void => createOrganization(null);
const onAddChildOrganization = (organization: Organization): void => createOrganization(organization.id);
const onSelectOrganization = async (organization: Organization): Promise<void> => {
    if (!organizationRepository.value) return;
    isLoading.value = true;
    try {
        currentOrganization.value = await organizationRepository.value.get(organization.id, Contena.Context.api);
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-organization.notification.loadError'),
        });
    } finally {
        isLoading.value = false;
    }
};
const onUpdateOrganization = (path: string, value: unknown): void => {
    if (currentOrganization.value) Contena.Utils.object.set(currentOrganization.value, path, value);
};
const onSaveOrganization = async (): Promise<void> => {
    if (!organizationRepository.value || !currentOrganization.value || !canSaveCurrent.value) return;
    isLoading.value = true;
    try {
        const organizationId = currentOrganization.value.id;
        await organizationRepository.value.save(currentOrganization.value, Contena.Context.api);
        await resetTree();
        currentOrganization.value = await organizationRepository.value.get(organizationId, Contena.Context.api);
        createNotificationSuccess({
            title: translate('global.default.success'),
            message: translate('ct-settings-organization.notification.saveSuccess'),
        });
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-organization.notification.saveError'),
        });
    } finally {
        isLoading.value = false;
    }
};
const onCancelOrganization = (): void => {
    currentOrganization.value = null;
};
const onDeleteOrganization = (organization: Organization): Promise<void> => deleteOrganizations([organization.id]);
const deleteOrganizations = async (ids: string[]): Promise<void> => {
    if (!organizationRepository.value || ids.length === 0) return;
    isLoading.value = true;
    try {
        await organizationRepository.value.syncDeleted(ids, Contena.Context.api);
        currentOrganization.value = null;
        await resetTree();
        createNotificationSuccess({
            title: translate('global.default.success'),
            message: translate('ct-settings-organization.notification.deleteSuccess'),
        });
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-organization.notification.deleteError'),
        });
    } finally {
        isLoading.value = false;
    }
};

void Promise.all([
    resetTree(),
    loadOrganizationUnits(),
    loadCustomFieldSets(),
]);

swDefinePublic({
    organizationRepository,
    organizationUnitRepository,
    term,
    isLoading,
    treeItems,
    currentOrganization,
    organizationUnits,
    customFieldSets,
    canCreate,
    canEdit,
    canDelete,
    canAddOrganization,
    canSaveCurrent,
    selectedOrganizationId,
    detailTitle,
    createTooltip,
    loadOrganizationUnits,
    loadCustomFieldSets,
    getCriteria,
    resetTree,
    loadTreeItems,
    onSearch,
    onChangeLanguage,
    createOrganization,
    onAddOrganization,
    onAddChildOrganization,
    onSelectOrganization,
    onUpdateOrganization,
    onSaveOrganization,
    onCancelOrganization,
    onDeleteOrganization,
    deleteOrganizations,
});

defineExpose({
    organizationRepository,
    organizationUnitRepository,
    term,
    isLoading,
    treeItems,
    currentOrganization,
    organizationUnits,
    customFieldSets,
    canCreate,
    canEdit,
    canDelete,
    canAddOrganization,
    canSaveCurrent,
    selectedOrganizationId,
    detailTitle,
    createTooltip,
    loadOrganizationUnits,
    loadCustomFieldSets,
    getCriteria,
    resetTree,
    loadTreeItems,
    onSearch,
    onChangeLanguage,
    createOrganization,
    onAddOrganization,
    onAddChildOrganization,
    onSelectOrganization,
    onUpdateOrganization,
    onSaveOrganization,
    onCancelOrganization,
    onDeleteOrganization,
    deleteOrganizations,
});
</script>

<style lang="scss">
.ct-settings-organization-list {
    &__workspace {
        display: grid;
        grid-template-columns: minmax(340px, 440px) minmax(520px, 1fr);
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

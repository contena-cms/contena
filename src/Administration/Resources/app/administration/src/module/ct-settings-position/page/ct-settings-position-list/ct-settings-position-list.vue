<template>
    <ct-block name="sw_settings_position_list">
        <ct-page class="ct-settings-position-list">
            <template #search-bar>
                <ct-block name="sw_settings_position_list_search_bar">
                    <ct-search-bar
                        initial-search-type="position"
                        :placeholder="translate('ct-settings-position.list.searchPlaceholder')"
                        @search="onSearch"
                    />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_settings_position_list_header">
                    <h2>
                        {{ translate('ct-settings-position.general.mainMenuItemGeneral') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount"> ({{ total }}) </span>
                    </h2>
                </ct-block>
            </template>

            <template #language-switch>
                <ct-block name="sw_settings_position_list_language">
                    <ct-language-switch @on-change="onChangeLanguage" />
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_settings_position_list_actions">
                    <mt-button
                        v-tooltip.bottom="createTooltip"
                        class="ct-settings-position-list__button-create"
                        variant="primary"
                        size="default"
                        :disabled="!canCreate || undefined"
                        @click="onAddPosition"
                    >
                        {{ translate('global.default.add') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_settings_position_list_content">
                    <ct-card-view>
                        <mt-data-table
                            class="ct-settings-position-list__content"
                            layout="full"
                            :caption="translate('ct-settings-position.general.mainMenuItemGeneral')"
                            :data-source="positions ?? []"
                            :columns="columns"
                            :is-loading="isLoading"
                            :pagination-total-items="total"
                            :current-page="page"
                            :pagination-limit="limit"
                            :sort-by="sortBy"
                            :sort-direction="sortDirection"
                            disable-search
                            disable-edit
                            :disable-delete="!canDelete"
                            :additional-context-buttons="additionalContextButtons"
                            @reload="getList"
                            @pagination-current-page-change="onPageChange"
                            @pagination-limit-change="onLimitChange"
                            @sort-change="onSort"
                            @item-delete="onItemDelete"
                            @context-select="onContextSelect"
                        >
                            <template #column-name="{ data }">
                                <router-link :to="{ name: 'ct.settings.position.detail', params: { id: data.id } }">
                                    {{ getPositionName(data) }}
                                </router-link>
                            </template>
                            <template #column-active="{ data }">
                                <mt-badge :variant="data.active ? 'positive' : 'neutral'">
                                    {{ data.active ? translate('global.default.yes') : translate('global.default.no') }}
                                </mt-badge>
                            </template>

                            <template #empty-state>
                                <mt-empty-state
                                    icon="regular-list"
                                    :headline="translate('ct-settings-position.list.emptyTitle')"
                                    :description="translate('ct-settings-position.list.emptyDescription')"
                                />
                            </template>
                        </mt-data-table>
                    </ct-card-view>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>

    <mt-modal-root v-if="positionToDelete" :is-open="true" @change="positionToDelete = null">
        <mt-modal :title="translate('global.default.warning')" width="s">
            {{ getPositionName(positionToDelete) }}
            <template #footer>
                <mt-button variant="secondary" @click="positionToDelete = null">
                    {{ translate('global.default.cancel') }}
                </mt-button>
                <mt-button variant="critical" :is-loading="isDeleting" @click="deletePosition">
                    {{ translate('global.default.delete') }}
                </mt-button>
            </template>
        </mt-modal>
    </mt-modal-root>
</template>

<script setup lang="ts">
/* global Entity, EntityCollection */
import './ct-settings-position-list.scss';

import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';

import { computed, inject, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';

type Position = Entity<'position'>;

const { Criteria } = Contena.Data;
defineProps({});
const { t } = useI18n();
const translate = t;
const router = useRouter();
const {
    page,
    limit,
    total,
    sortBy,
    sortDirection,
    term,
    disableRouteParams,
    onPageChange,
    onSearch,
    onSortColumn,
    initializeListing,
} = useListing();
const { createNotificationError } = useNotification();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
const positionRepository = computed(() => repositoryFactory?.create('position'));
const positions = ref<EntityCollection<'position'> | null>(null);
const isLoading = ref(true);
const canView = computed(() => Boolean(acl?.can('position.viewer')));
const canCreate = computed(() => Boolean(acl?.can('position.creator')));
const canEdit = computed(() => Boolean(acl?.can('position.editor')));
const canDelete = computed(() => Boolean(acl?.can('position.deleter')));
const isDeleting = ref(false);
const positionToDelete = ref<Position | null>(null);
const columns = computed(() => [
    {
        property: 'name',
        dataIndex: 'name',
        label: translate('ct-settings-position.list.columnName'),
        width: '240px',
        renderer: 'text' as const,
        routerLink: 'ct.settings.position.detail',
        primary: true,
    },
    {
        property: 'code',
        dataIndex: 'code',
        label: translate('ct-settings-position.list.columnCode'),
        width: '200px',
        renderer: 'text' as const,
    },
    {
        property: 'position',
        dataIndex: 'position',
        label: translate('ct-settings-position.list.columnPosition'),
        align: 'center',
        width: '100px',
        renderer: 'number' as const,
    },
    {
        property: 'active',
        dataIndex: 'active',
        label: translate('ct-settings-position.list.columnActive'),
        align: 'center',
        width: '110px',
        renderer: 'text' as const,
    },
    {
        property: 'extensions.positionCreatedAtLabel',
        dataIndex: 'createdAt',
        label: translate('ct-settings-position.list.columnCreatedAt'),
        width: '170px',
        renderer: 'text' as const,
    },
    {
        property: 'extensions.positionUpdatedAtLabel',
        dataIndex: 'updatedAt',
        label: translate('ct-settings-position.list.columnUpdatedAt'),
        width: '170px',
        renderer: 'text' as const,
    },
]);
const formatDate = Contena.Filter.getByName('date');
const additionalContextButtons = computed(() => {
    const buttons = [];

    if (canEdit.value || canView.value) {
        buttons.push({
            key: 'edit',
            label: canEdit.value ? translate('global.default.edit') : translate('global.default.view'),
        });
    }

    return buttons;
});
const createTooltip = computed(() => ({
    message: translate('ct-privileges.tooltip.warning'),
    disabled: canCreate.value,
    showOnDisabledElements: true,
}));
const getCriteria = (): InstanceType<typeof Criteria> => {
    const criteria = new Criteria(page.value, limit.value);
    if (term.value) criteria.setTerm(term.value);
    criteria.addSorting(Criteria.sort(sortBy.value || 'position', sortDirection.value || 'ASC'));
    if (sortBy.value !== 'name') criteria.addSorting(Criteria.sort('name', 'ASC'));
    return criteria;
};
const getList = async (): Promise<void> => {
    if (!positionRepository.value) return;
    isLoading.value = true;
    try {
        const result = await positionRepository.value.search(getCriteria(), Contena.Context.api);
        result.forEach((positionEntity) => {
            positionEntity.extensions = {
                ...(positionEntity.extensions ?? {}),
                positionCreatedAtLabel: formatDate(positionEntity.createdAt, {
                    hour: '2-digit',
                    minute: '2-digit',
                }),
                positionUpdatedAtLabel: positionEntity.updatedAt
                    ? formatDate(positionEntity.updatedAt, { hour: '2-digit', minute: '2-digit' })
                    : '—',
            };
        });
        positions.value = result;
        total.value = result.total ?? result.length;
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-position.notification.loadError'),
        });
    } finally {
        isLoading.value = false;
    }
};
const getPositionName = (positionEntity: Position): string =>
    positionEntity.translated?.name || positionEntity.name || positionEntity.code || '';
const onAddPosition = (): void => {
    if (!canCreate.value) return;
    void router.push({ name: 'ct.settings.position.create' });
};
const onChangeLanguage = (): void => {
    void getList();
};
const onLimitChange = (nextLimit: number): void => {
    limit.value = nextLimit;
    page.value = 1;
    void getList();
};
const onSort = ({ sortBy: nextSortBy, sortDirection: nextSortDirection }): void => {
    sortBy.value = nextSortBy;
    sortDirection.value = nextSortDirection;
    page.value = 1;
    void getList();
};
const onItemDelete = (position: Position): void => {
    positionToDelete.value = position;
};
const onContextSelect = ({ key, data }): void => {
    if (key === 'edit') {
        void router.push({
            name: 'ct.settings.position.detail',
            params: { id: data.id },
            query: canEdit.value ? { edit: 'edit' } : undefined,
        });
    }
};
const deletePosition = async (): Promise<void> => {
    if (!positionToDelete.value || !positionRepository.value) return;

    isDeleting.value = true;
    try {
        await positionRepository.value.delete(positionToDelete.value.id, Contena.Context.api);
        positionToDelete.value = null;
        await getList();
    } catch {
        createNotificationError({
            title: translate('global.default.error'),
            message: translate('ct-settings-position.notification.loadError'),
        });
    } finally {
        isDeleting.value = false;
    }
};

disableRouteParams.value = true;
sortBy.value = 'position';
sortDirection.value = 'ASC';
initializeListing({ getList });

swDefinePublic({
    positionRepository,
    positions,
    isLoading,
    columns,
    canView,
    canCreate,
    canEdit,
    canDelete,
    isDeleting,
    positionToDelete,
    additionalContextButtons,
    createTooltip,
    getCriteria,
    getList,
    getPositionName,
    onAddPosition,
    onChangeLanguage,
    onLimitChange,
    onSort,
    onItemDelete,
    onContextSelect,
    deletePosition,
    page,
    limit,
    total,
    sortBy,
    sortDirection,
    term,
    onPageChange,
    onSearch,
    onSortColumn,
});

defineExpose({
    positionRepository,
    positions,
    isLoading,
    columns,
    canView,
    canCreate,
    canEdit,
    canDelete,
    isDeleting,
    positionToDelete,
    additionalContextButtons,
    createTooltip,
    getCriteria,
    getList,
    getPositionName,
    onAddPosition,
    onChangeLanguage,
    onLimitChange,
    onSort,
    onItemDelete,
    onContextSelect,
    deletePosition,
    page,
    limit,
    total,
    sortBy,
    sortDirection,
    term,
    onPageChange,
    onSearch,
    onSortColumn,
});
</script>

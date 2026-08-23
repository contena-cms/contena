<template>
    <ct-block name="sw_channel_list">
        <ct-page class="ct-channel-list">
            <template #search-bar>
                <ct-block name="sw_channel_list_search">
                    <mt-search :model-value="term" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="sw_channel_list_header">
                    <h2>
                        {{ t('ct-channel.list.title') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #smart-bar-actions>
                <ct-block name="sw_channel_list_actions">
                    <mt-button
                        v-tooltip.bottom="createTooltip"
                        variant="primary"
                        :disabled="!canCreate || undefined"
                        @click="onAddChannel"
                    >
                        {{ t('ct-channel.list.buttonAddChannel') }}
                    </mt-button>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="sw_channel_list_content">
                    <mt-data-table
                        layout="full"
                        :caption="t('ct-channel.list.caption')"
                        :data-source="channels"
                        :columns="columns"
                        :is-loading="isLoading"
                        :pagination-total-items="total"
                        :current-page="page"
                        :pagination-limit="limit"
                        :search-value="term"
                        :sort-by="sortBy"
                        :sort-direction="sortDirection"
                        :number-of-results="total"
                        disable-search
                        :disable-delete="!canDelete"
                        :disable-edit="true"
                        :additional-context-buttons="additionalContextButtons"
                        @reload="loadChannels"
                        @pagination-current-page-change="onPageChange"
                        @pagination-limit-change="onLimitChange"
                        @search-value-change="onSearch"
                        @sort-change="onSort"
                        @context-select="onContextSelect"
                    >
                        <template #column-name="{ data }">
                            <router-link :to="{ name: 'ct.channel.detail', params: { id: data.id } }">
                                {{ data.translated?.name || data.name }}
                            </router-link>
                        </template>
                        <template #column-type="{ data }">
                            <span class="ct-channel-list__type">
                                <mt-icon :name="data.type?.iconName || 'regular-server'" size="16px" />
                                {{ data.type?.translated?.name || data.type?.name }}
                            </span>
                        </template>
                        <template #column-status="{ data }">
                            <mt-badge :variant="statusVariant(data)">
                                {{ statusLabel(data) }}
                            </mt-badge>
                        </template>
                        <template #column-favorite="{ data }">
                            <mt-switch
                                v-tooltip.right="{ message: t('ct-channel.detail.favouriteLabel') }"
                                class="favorite-switch"
                                :disabled="!canManageFavorites || undefined"
                                :model-value="isFavorite(data.id)"
                                @update:model-value="(favorite) => channelFavoritesService.update(favorite, data.id)"
                            />
                        </template>
                    </mt-data-table>
                </ct-block>
            </template>
        </ct-page>

        <ct-channel-modal v-if="showChannelModal" @modal-close="showChannelModal = false" />

        <mt-modal-root v-if="channelToDelete" :is-open="true" @change="onDeleteModalChange">
            <mt-modal :title="t('ct-channel.list.deleteTitle')" width="s">
                {{ t('ct-channel.list.deleteText', { name: channelToDelete.translated?.name || channelToDelete.name }) }}
                <template #footer>
                    <mt-button variant="secondary" @click="channelToDelete = null">
                        {{ t('global.default.cancel') }}
                    </mt-button>
                    <mt-button variant="critical" :is-loading="isDeleting" @click="deleteChannel">
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

import { useNotification } from 'src/app/composables/use-notification';
import { getDomainLink } from '../../service/domain-link.service';
import './ct-channel-list.scss';

type SortDirection = 'ASC' | 'DESC';
type BadgeVariant = 'neutral' | 'attention' | 'positive';
type Column = {
    property: string;
    label: string;
    position: number;
    renderer: 'text';
    sortable?: boolean;
    width?: number;
};

defineProps({});
const router = useRouter();
const { t } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) {
    throw new Error('The repository factory and ACL service are unavailable.');
}

const channelRepository = repositoryFactory.create('channel');
const channels = ref<Entity<'channel'>[]>([]);
const isLoading = ref(false);
const isDeleting = ref(false);
const page = ref(1);
const limit = ref(25);
const total = ref(0);
const term = ref('');
const sortBy = ref('name');
const sortDirection = ref<SortDirection>('ASC');
const showChannelModal = ref(false);
const channelToDelete = ref<Entity<'channel'> | null>(null);
const canCreate = computed(() => acl.can('channel.creator'));
const canDelete = computed(() => acl.can('channel.deleter'));
const canManageFavorites = computed(() => acl.can('user_config:create') && acl.can('user_config:update'));
const channelFavoritesService = computed(() => Contena.Service('channelFavorites'));
const createTooltip = computed(() => ({
    message: t('ct-privileges.tooltip.warning'),
    disabled: canCreate.value,
    showOnDisabledElements: true,
}));
const columns: Column[] = [
    { property: 'name', label: t('ct-channel.list.columnName'), position: 100, renderer: 'text', width: 260 },
    { property: 'type', label: t('ct-channel.list.columnType'), position: 200, renderer: 'text', width: 200 },
    {
        property: 'status',
        label: t('ct-channel.list.columnStatus'),
        position: 300,
        renderer: 'text',
        sortable: false,
        width: 140,
    },
    {
        property: 'createdAt',
        label: t('ct-channel.list.columnCreatedAt'),
        position: 400,
        renderer: 'text',
        width: 180,
    },
    {
        property: 'favorite',
        label: t('ct-channel.list.columnFavourite'),
        position: 500,
        renderer: 'text',
        sortable: false,
        width: 140,
    },
];
const additionalContextButtons = computed(() => [
    { key: 'openFrontend', label: t('ct-channel.general.tooltipOpenFrontend') },
    { key: 'edit', label: t('global.default.edit') },
]);

const loadChannels = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const criteria = new Contena.Data.Criteria(page.value, limit.value);
        if (term.value) criteria.setTerm(term.value);
        criteria.addAssociation('type');
        criteria.addAssociation('domains');
        criteria.addSorting(Contena.Data.Criteria.sort(sortBy.value, sortDirection.value));
        const result = await channelRepository.search(criteria, Contena.Context.api);
        channels.value = Array.from(result);
        total.value = result.total;
    } catch {
        createNotificationError({ message: t('ct-channel.list.loadError') });
    } finally {
        isLoading.value = false;
    }
};
const onPageChange = (value: number): void => {
    page.value = value;
    void loadChannels();
};
const onLimitChange = (value: number): void => {
    limit.value = value;
    page.value = 1;
    void loadChannels();
};
const onSearch = (value: string): void => {
    term.value = value;
    page.value = 1;
    void loadChannels();
};
const onSort = (value: { sortBy: string; sortDirection: SortDirection }): void => {
    sortBy.value = value.sortBy;
    sortDirection.value = value.sortDirection;
    void loadChannels();
};
const onAddChannel = (): void => {
    showChannelModal.value = true;
};
const openDetail = (id: string): void => {
    void router.push({ name: 'ct.channel.detail', params: { id } });
};
const onContextSelect = ({ key, data }: { key: string; data: Entity<'channel'> }): void => {
    if (key === 'openFrontend' && data.active) openFrontend(data);
    if (key === 'edit') openDetail(data.id);
};
const domainLink = (channel: Entity<'channel'>): string | null => getDomainLink(channel);
const openFrontend = (channel: Entity<'channel'>): void => {
    const url = domainLink(channel);
    if (url) window.open(url, '_blank', 'noopener');
};
const statusLabel = (channel: Entity<'channel'>): string => {
    if (channel.maintenance) return t('ct-channel.list.status.maintenance');
    return channel.active ? t('ct-channel.list.status.online') : t('ct-channel.list.status.offline');
};
const statusVariant = (channel: Entity<'channel'>): BadgeVariant => {
    if (channel.maintenance) return 'attention';
    return channel.active ? 'positive' : 'neutral';
};
const isFavorite = (channelId: string): boolean => channelFavoritesService.value.isFavorite(channelId);
const onDeleteModalChange = (open: boolean): void => {
    if (!open) channelToDelete.value = null;
};
const deleteChannel = async (): Promise<void> => {
    if (!channelToDelete.value || !canDelete.value) return;
    isDeleting.value = true;
    try {
        await channelRepository.delete(channelToDelete.value.id, Contena.Context.api);
        channelToDelete.value = null;
        channelFavoritesService.value.refresh();
        Contena.Utils.EventBus.emit('ct-channel-detail-channel-change');
        createNotificationSuccess({ message: t('ct-channel.list.deleteSuccess') });
        await loadChannels();
    } catch {
        createNotificationError({ message: t('ct-channel.list.deleteError') });
    } finally {
        isDeleting.value = false;
    }
};

void loadChannels();

swDefinePublic({
    channels,
    columns,
    additionalContextButtons,
    isLoading,
    isDeleting,
    page,
    limit,
    total,
    term,
    sortBy,
    sortDirection,
    showChannelModal,
    channelToDelete,
    canCreate,
    canDelete,
    canManageFavorites,
    channelFavoritesService,
    createTooltip,
    loadChannels,
    onPageChange,
    onLimitChange,
    onSearch,
    onSort,
    onAddChannel,
    openDetail,
    onContextSelect,
    domainLink,
    openFrontend,
    statusLabel,
    statusVariant,
    isFavorite,
    onDeleteModalChange,
    deleteChannel,
});

defineExpose({ loadChannels, onAddChannel, statusLabel, isFavorite, deleteChannel, additionalContextButtons });
</script>

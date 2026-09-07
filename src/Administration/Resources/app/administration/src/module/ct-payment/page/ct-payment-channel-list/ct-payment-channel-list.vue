<template>
    <ct-block name="ct_payment_channel_list">
        <ct-page class="ct-payment-channel-list">
            <template #search-bar>
                <ct-block name="ct_payment_channel_list_search">
                    <mt-search :model-value="term" :placeholder="t('ct-payment.list.search')" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_payment_channel_list_header">
                    <h2>
                        {{ t('ct-payment.settings.channelsTitle') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_payment_channel_list_content">
                    <mt-data-table
                        layout="full"
                        :caption="t('ct-payment.settings.channelsTitle')"
                        :data-source="channels"
                        :columns="columns"
                        :is-loading="isLoading"
                        :pagination-total-items="total"
                        :current-page="page"
                        :pagination-limit="limit"
                        :sort-by="sortBy"
                        :sort-direction="sortDirection"
                        disable-search
                        enable-reload
                        disable-edit
                        disable-delete
                        @reload="getList"
                        @pagination-current-page-change="onTablePageChange"
                        @pagination-limit-change="onTableLimitChange"
                        @sort-change="onTableSortChange"
                    >
                        <template #column-channel="{ data }">
                            <div class="ct-payment-channel-list__stack">
                                <strong>{{ data.channel?.translated?.name || data.channel?.name || data.channelId }}</strong>
                                <small v-if="data.channel?.code">{{ data.channel.code }}</small>
                            </div>
                        </template>

                        <template #column-app="{ data }">
                            <div v-if="data.app" class="ct-payment-channel-list__stack">
                                <strong>{{ data.app.translated?.name || data.app.name || data.app.appCode }}</strong>
                                <small>{{ data.app.appCode }}</small>
                            </div>
                            <span v-else>{{ t('ct-payment.channel.shared') }}</span>
                        </template>

                        <template #column-rule="{ data }">
                            {{ data.rule?.name || '-' }}
                        </template>

                        <template #column-status="{ data }">
                            <mt-badge :variant="data.status ? 'positive' : 'neutral'">
                                {{ t(data.status ? 'ct-payment.status.active' : 'ct-payment.status.inactive') }}
                            </mt-badge>
                        </template>

                        <template #column-createdAt="{ data }">
                            {{ formatDate(data.createdAt) }}
                        </template>

                        <template #empty-state>
                            <mt-empty-state icon="regular-plug" :headline="t('ct-payment.list.empty')" />
                        </template>
                    </mt-data-table>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */

import type RepositoryFactory from 'src/core/data/repository-factory.data';

import { computed, inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useListing } from 'src/app/composables/use-listing';
import { useNotification } from 'src/app/composables/use-notification';
import { usePageTitle } from 'src/app/composables/use-page-title';

type SortDirection = 'ASC' | 'DESC';
type Column = {
    property: string;
    label: string;
    position: number;
    renderer: 'text';
    sortable?: boolean;
    width?: number;
};

defineProps({});

const { t } = useI18n();
const { createNotificationError } = useNotification();
const { page, limit, total, term, onPageChange, onSearch, onSort, initializeListing } = useListing();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
if (!repositoryFactory) throw new Error('The Payment channel list services are unavailable.');

const channels = ref<Entity<'payment_channel_config'>[]>([]);
const isLoading = ref(false);
const sortBy = ref('createdAt');
const sortDirection = ref<SortDirection>('DESC');
const channelRepository = computed(() => repositoryFactory.create('payment_channel_config'));
const columns = computed<Column[]>(() => [
    { property: 'channel', label: t('ct-payment.channel.code'), position: 100, renderer: 'text', width: 260 },
    { property: 'app', label: t('ct-payment.channel.app'), position: 200, renderer: 'text', width: 240 },
    { property: 'rule', label: t('ct-payment.channel.rule'), position: 300, renderer: 'text', width: 220 },
    { property: 'status', label: t('ct-payment.channel.status'), position: 400, renderer: 'text', width: 140 },
    {
        property: 'createdAt',
        label: t('ct-payment.channel.createdAt'),
        position: 500,
        renderer: 'text',
        sortable: true,
        width: 190,
    },
]);
const criteria = computed(() => {
    const query = new Contena.Data.Criteria(page.value, limit.value);
    query.addAssociation('channel');
    query.addAssociation('app');
    query.addAssociation('rule');
    query.addSorting(Contena.Data.Criteria.sort(sortBy.value, sortDirection.value));
    if (term.value) query.setTerm(term.value);

    return query;
});

const getList = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const result = await channelRepository.value.search(criteria.value, Contena.Context.api);
        channels.value = Array.from(result);
        total.value = result.total ?? 0;
    } catch {
        createNotificationError({ message: t('ct-payment.list.loadError') });
    } finally {
        isLoading.value = false;
    }
};
const onTablePageChange = (nextPage: number): void => onPageChange({ page: nextPage, limit: limit.value });
const onTableLimitChange = (nextLimit: number): void => onPageChange({ page: 1, limit: nextLimit });
const onTableSortChange = (value: { sortBy: string; sortDirection: SortDirection }): void => onSort(value);
const formatDate = (value?: string): string =>
    value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-';

initializeListing({ getList, sortBy, sortDirection });
void getList();
usePageTitle();

ctDefinePublic({
    channels,
    isLoading,
    sortBy,
    sortDirection,
    channelRepository,
    columns,
    criteria,
    getList,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
    formatDate,
});
</script>

<style scoped lang="scss">
.ct-payment-channel-list__stack {
    display: flex;
    flex-direction: column;
    gap: 3px;

    small {
        color: var(--color-text-secondary-default);
        font-size: 12px;
    }
}
</style>

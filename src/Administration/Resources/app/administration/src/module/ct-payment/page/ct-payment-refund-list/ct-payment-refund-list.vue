<template>
    <ct-block name="ct_payment_refund_list">
        <ct-page class="ct-payment-refund-list">
            <template #search-bar>
                <ct-block name="ct_payment_refund_list_search">
                    <mt-search :model-value="term" :placeholder="t('ct-payment.list.search')" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_payment_refund_list_header">
                    <h2>
                        {{ t('ct-payment.navigation.refunds') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_payment_refund_list_content">
                    <mt-data-table
                        layout="full"
                        :caption="t('ct-payment.navigation.refunds')"
                        :data-source="refunds"
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
                        <template #column-refundNo="{ data }">
                            <div class="ct-payment-refund-list__stack">
                                <strong>{{ data.externalRefundNo }}</strong>
                                <small>{{ data.refundNo }}</small>
                            </div>
                        </template>

                        <template #column-order="{ data }">
                            <div class="ct-payment-refund-list__stack">
                                <strong>{{ data.order?.externalOrderNo || '-' }}</strong>
                                <small>{{ data.order?.orderNo || data.orderId }}</small>
                            </div>
                        </template>

                        <template #column-refundAmount="{ data }">
                            <strong>{{ formatAmount(data.refundAmount, data.order?.currencyCode) }}</strong>
                        </template>

                        <template #column-channelCode="{ data }">
                            <span>{{ data.channelCode }}</span>
                        </template>

                        <template #column-status="{ data }">
                            <mt-badge :variant="statusVariant(data.status)">{{ statusLabel(data.status) }}</mt-badge>
                        </template>

                        <template #column-createdAt="{ data }">
                            <div class="ct-payment-refund-list__stack">
                                <span>{{ formatDate(data.createdAt) }}</span>
                                <small v-if="data.successTime">{{ formatDate(data.successTime) }}</small>
                            </div>
                        </template>

                        <template #empty-state>
                            <mt-empty-state
                                icon="regular-arrow-180-left"
                                :headline="t('ct-payment.list.empty')"
                                :description="term ? t('ct-empty-state.messageNoResultSubline') : undefined"
                            />
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
type BadgeVariant = 'neutral' | 'attention' | 'positive' | 'critical';
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
if (!repositoryFactory) throw new Error('The Payment refund list services are unavailable.');

const refunds = ref<Entity<'payment_refund'>[]>([]);
const isLoading = ref(false);
const sortBy = ref('createdAt');
const sortDirection = ref<SortDirection>('DESC');
const refundRepository = computed(() => repositoryFactory.create('payment_refund'));
const columns = computed<Column[]>(() => [
    { property: 'refundNo', label: t('ct-payment.list.refundNo'), position: 100, renderer: 'text', width: 220 },
    { property: 'order', label: t('ct-payment.list.orderNo'), position: 200, renderer: 'text', width: 220 },
    {
        property: 'refundAmount',
        label: t('ct-payment.list.amount'),
        position: 300,
        renderer: 'text',
        sortable: true,
        width: 150,
    },
    { property: 'channelCode', label: t('ct-payment.list.channel'), position: 400, renderer: 'text', width: 150 },
    { property: 'status', label: t('ct-payment.list.status'), position: 500, renderer: 'text', width: 140 },
    {
        property: 'createdAt',
        label: t('ct-payment.list.createdAt'),
        position: 600,
        renderer: 'text',
        sortable: true,
        width: 190,
    },
]);
const criteria = computed(() => {
    const query = new Contena.Data.Criteria(page.value, limit.value);
    query.addAssociation('order.app');
    query.addSorting(Contena.Data.Criteria.sort(sortBy.value, sortDirection.value));
    if (term.value) query.setTerm(term.value);

    return query;
});

const getList = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const result = await refundRepository.value.search(criteria.value, Contena.Context.api);
        refunds.value = Array.from(result);
        total.value = result.total ?? 0;
    } catch {
        createNotificationError({ message: t('ct-payment.list.loadError') });
    } finally {
        isLoading.value = false;
    }
};
const onTablePageChange = (nextPage: number): void => {
    onPageChange({ page: nextPage, limit: limit.value });
};
const onTableLimitChange = (nextLimit: number): void => {
    onPageChange({ page: 1, limit: nextLimit });
};
const onTableSortChange = ({
    sortBy: property,
    sortDirection: direction,
}: {
    sortBy: string;
    sortDirection: SortDirection;
}): void => {
    onSort({ sortBy: property, sortDirection: direction });
};
const formatAmount = (amount: number, currencyCode?: string): string =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency: currencyCode ?? 'CNY' }).format(amount / 100);
const formatDate = (value?: string): string =>
    value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-';
const statusLabel = (status: number): string => {
    const key =
        [
            'created',
            'processing',
            'succeeded',
            'failed',
        ][status] ?? 'unknown';
    return t(`ct-payment.status.${key}`);
};
const statusVariant = (status: number): BadgeVariant => {
    if (status === 2) return 'positive';
    if (status === 3) return 'critical';
    if (status === 1) return 'attention';

    return 'neutral';
};

initializeListing({ getList, sortBy, sortDirection });
void getList();
usePageTitle();

ctDefinePublic({
    refunds,
    isLoading,
    sortBy,
    sortDirection,
    refundRepository,
    columns,
    criteria,
    getList,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
    formatAmount,
    formatDate,
    statusLabel,
    statusVariant,
});

defineExpose({
    refunds,
    isLoading,
    sortBy,
    sortDirection,
    refundRepository,
    columns,
    criteria,
    getList,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
    formatAmount,
    formatDate,
    statusLabel,
    statusVariant,
});
</script>

<style scoped lang="scss">
.ct-payment-refund-list {
    &__stack {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    &__stack small {
        color: var(--color-text-secondary-default);
        font-size: 12px;
    }
}
</style>

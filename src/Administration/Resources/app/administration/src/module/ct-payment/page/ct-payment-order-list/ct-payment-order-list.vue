<template>
    <ct-block name="ct_payment_order_list">
        <ct-page class="ct-payment-order-list">
            <template #search-bar>
                <ct-block name="ct_payment_order_list_search">
                    <mt-search :model-value="term" :placeholder="t('ct-payment.list.search')" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_payment_order_list_header">
                    <h2>
                        {{ t('ct-payment.navigation.orders') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_payment_order_list_content">
                    <mt-data-table
                        layout="full"
                        :caption="t('ct-payment.navigation.orders')"
                        :data-source="orders"
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
                        <template #column-orderNo="{ data }">
                            <div class="ct-payment-order-list__stack">
                                <strong>{{ data.externalOrderNo }}</strong>
                                <small>{{ data.orderNo }}</small>
                            </div>
                        </template>

                        <template #column-subject="{ data }">
                            <div class="ct-payment-order-list__stack ct-payment-order-list__subject">
                                <strong>{{ data.subject }}</strong>
                                <small v-if="data.app?.name">{{ data.app.name }}</small>
                            </div>
                        </template>

                        <template #column-amount="{ data }">
                            <strong>{{ formatAmount(data.amount, data.currencyCode) }}</strong>
                        </template>

                        <template #column-channel="{ data }">
                            <div class="ct-payment-order-list__stack">
                                <strong>{{ data.channelCode }}</strong>
                                <small>{{ data.methodCode }}</small>
                            </div>
                        </template>

                        <template #column-status="{ data }">
                            <mt-badge :variant="statusVariant(data)">{{ stateLabel(data) }}</mt-badge>
                        </template>

                        <template #column-createdAt="{ data }">
                            <div class="ct-payment-order-list__stack">
                                <span>{{ formatDate(data.createdAt) }}</span>
                                <small v-if="data.expireTime">
                                    {{ t('ct-payment.list.expireTime') }}: {{ formatDate(data.expireTime) }}
                                </small>
                            </div>
                        </template>

                        <template #empty-state>
                            <mt-empty-state
                                icon="regular-credit-card"
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
import useCreateTitle from 'src/app/composables/use-create-title';
import useMetaInfo from 'src/app/composables/use-meta-info';

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
if (!repositoryFactory) throw new Error('The Payment order list services are unavailable.');

const orders = ref<Entity<'payment_order'>[]>([]);
const isLoading = ref(false);
const sortBy = ref('createdAt');
const sortDirection = ref<SortDirection>('DESC');
const orderRepository = computed(() => repositoryFactory.create('payment_order'));
const columns = computed<Column[]>(() => [
    { property: 'orderNo', label: t('ct-payment.list.orderNo'), position: 100, renderer: 'text', width: 220 },
    { property: 'subject', label: t('ct-payment.list.subject'), position: 200, renderer: 'text', width: 240 },
    {
        property: 'amount',
        label: t('ct-payment.list.amount'),
        position: 300,
        renderer: 'text',
        sortable: true,
        width: 150,
    },
    { property: 'channel', label: t('ct-payment.list.channel'), position: 400, renderer: 'text', width: 170 },
    { property: 'status', label: t('ct-payment.list.status'), position: 500, renderer: 'text', width: 140 },
    {
        property: 'createdAt',
        label: t('ct-payment.list.createdAt'),
        position: 600,
        renderer: 'text',
        sortable: true,
        width: 210,
    },
]);
const criteria = computed(() => {
    const query = new Contena.Data.Criteria(page.value, limit.value);
    query.addAssociation('state');
    query.addAssociation('app');
    query.addSorting(Contena.Data.Criteria.sort(sortBy.value, sortDirection.value));
    if (term.value) query.setTerm(term.value);

    return query;
});

const getList = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const result = await orderRepository.value.search(criteria.value, Contena.Context.api);
        orders.value = Array.from(result);
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
const formatAmount = (amount: number, currencyCode: string): string =>
    new Intl.NumberFormat(undefined, { style: 'currency', currency: currencyCode }).format(amount / 100);
const formatDate = (value?: string): string =>
    value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-';
const stateLabel = (order: Entity<'payment_order'>): string => {
    const state = order.state;
    if (state?.translated?.name || state?.name) return state.translated?.name ?? state.name;

    const technicalName = state?.technicalName;
    return technicalName ? t(`ct-payment.status.${technicalName}`) : t('ct-payment.status.unknown');
};
const statusVariant = (order: Entity<'payment_order'>): BadgeVariant => {
    switch (order.state?.technicalName) {
        case 'succeeded':
            return 'positive';
        case 'failed':
        case 'closed':
            return 'critical';
        case 'pending':
        case 'processing':
            return 'attention';
        default:
            return 'neutral';
    }
};

initializeListing({ getList, sortBy, sortDirection });
void getList();
const createTitle = useCreateTitle();
useMetaInfo(() => ({ title: createTitle() }));

ctDefinePublic({
    orders,
    isLoading,
    sortBy,
    sortDirection,
    orderRepository,
    columns,
    criteria,
    getList,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
    formatAmount,
    formatDate,
    stateLabel,
    statusVariant,
});
</script>

<style scoped lang="scss">
.ct-payment-order-list {
    &__stack {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    &__stack small {
        color: var(--color-text-secondary-default);
        font-size: 12px;
    }

    &__subject strong {
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
}
</style>

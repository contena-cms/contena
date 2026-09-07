<template>
    <ct-block name="ct_payment_method_list">
        <ct-page class="ct-payment-method-list">
            <template #search-bar>
                <ct-block name="ct_payment_method_list_search">
                    <mt-search :model-value="term" :placeholder="t('ct-payment.list.search')" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_payment_method_list_header">
                    <h2>
                        {{ t('ct-payment.settings.methodsTitle') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_payment_method_list_content">
                    <mt-data-table
                        layout="full"
                        :caption="t('ct-payment.settings.methodsTitle')"
                        :data-source="methods"
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
                        <template #column-app="{ data }">
                            <div class="ct-payment-method-list__stack">
                                <strong>{{ data.app?.translated?.name || data.app?.name || data.paymentAppId }}</strong>
                                <small v-if="data.app?.appCode">{{ data.app.appCode }}</small>
                            </div>
                        </template>

                        <template #column-method="{ data }">
                            <div class="ct-payment-method-list__stack">
                                <strong>{{
                                    data.channelMethod?.translated?.name ||
                                    data.channelMethod?.name ||
                                    data.channelMethod?.methodCode ||
                                    data.channelMethodId
                                }}</strong>
                                <small v-if="data.channelMethod?.methodCode">{{ data.channelMethod.methodCode }}</small>
                            </div>
                        </template>

                        <template #column-channel="{ data }">
                            {{ data.channelMethod?.channel?.translated?.name || data.channelMethod?.channel?.name || '-' }}
                        </template>

                        <template #column-rule="{ data }">
                            {{ data.rule?.name || '-' }}
                        </template>

                        <template #column-status="{ data }">
                            <mt-badge :variant="data.status ? 'positive' : 'neutral'">
                                {{ t(data.status ? 'ct-payment.status.active' : 'ct-payment.status.inactive') }}
                            </mt-badge>
                        </template>

                        <template #column-sort="{ data }">
                            {{ data.sort }}
                        </template>

                        <template #empty-state>
                            <mt-empty-state icon="regular-credit-card" :headline="t('ct-payment.list.empty')" />
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
if (!repositoryFactory) throw new Error('The Payment method list services are unavailable.');

const methods = ref<Entity<'payment_app_channel_method'>[]>([]);
const isLoading = ref(false);
const sortBy = ref('sort');
const sortDirection = ref<SortDirection>('ASC');
const methodRepository = computed(() => repositoryFactory.create('payment_app_channel_method'));
const columns = computed<Column[]>(() => [
    { property: 'app', label: t('ct-payment.method.app'), position: 100, renderer: 'text', width: 240 },
    { property: 'method', label: t('ct-payment.method.methodCode'), position: 200, renderer: 'text', width: 240 },
    { property: 'channel', label: t('ct-payment.method.channel'), position: 300, renderer: 'text', width: 200 },
    { property: 'rule', label: t('ct-payment.method.rule'), position: 400, renderer: 'text', width: 200 },
    { property: 'status', label: t('ct-payment.method.status'), position: 500, renderer: 'text', width: 130 },
    {
        property: 'sort',
        label: t('ct-payment.method.sort'),
        position: 600,
        renderer: 'text',
        sortable: true,
        width: 120,
    },
]);
const criteria = computed(() => {
    const query = new Contena.Data.Criteria(page.value, limit.value);
    query.addAssociation('app');
    query.addAssociation('channelMethod.channel');
    query.addAssociation('rule');
    query.addSorting(Contena.Data.Criteria.sort(sortBy.value, sortDirection.value));
    if (term.value) query.setTerm(term.value);

    return query;
});

const getList = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const result = await methodRepository.value.search(criteria.value, Contena.Context.api);
        methods.value = Array.from(result);
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

initializeListing({ getList, sortBy, sortDirection });
void getList();
usePageTitle();

ctDefinePublic({
    methods,
    isLoading,
    sortBy,
    sortDirection,
    methodRepository,
    columns,
    criteria,
    getList,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
});
</script>

<style scoped lang="scss">
.ct-payment-method-list__stack {
    display: flex;
    flex-direction: column;
    gap: 3px;

    small {
        color: var(--color-text-secondary-default);
        font-size: 12px;
    }
}
</style>

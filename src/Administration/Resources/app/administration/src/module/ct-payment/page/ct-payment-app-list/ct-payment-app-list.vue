<template>
    <ct-block name="ct_payment_app_list">
        <ct-page class="ct-payment-app-list">
            <template #search-bar>
                <ct-block name="ct_payment_app_list_search">
                    <mt-search :model-value="term" :placeholder="t('ct-payment.list.search')" @change="onSearch" />
                </ct-block>
            </template>

            <template #smart-bar-header>
                <ct-block name="ct_payment_app_list_header">
                    <h2>
                        {{ t('ct-payment.settings.appsTitle') }}
                        <span v-if="!isLoading" class="ct-page__smart-bar-amount">({{ total }})</span>
                    </h2>
                </ct-block>
            </template>

            <template #content>
                <ct-block name="ct_payment_app_list_content">
                    <mt-data-table
                        layout="full"
                        :caption="t('ct-payment.settings.appsTitle')"
                        :data-source="apps"
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
                        <template #column-appCode="{ data }">
                            <strong>{{ data.appCode }}</strong>
                        </template>

                        <template #column-name="{ data }">
                            {{ data.translated?.name || data.name }}
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
                            <mt-empty-state icon="regular-storefront" :headline="t('ct-payment.list.empty')" />
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
if (!repositoryFactory) throw new Error('The Payment application list services are unavailable.');

const apps = ref<Entity<'payment_app'>[]>([]);
const isLoading = ref(false);
const sortBy = ref('appCode');
const sortDirection = ref<SortDirection>('ASC');
const appRepository = computed(() => repositoryFactory.create('payment_app'));
const columns = computed<Column[]>(() => [
    {
        property: 'appCode',
        label: t('ct-payment.app.code'),
        position: 100,
        renderer: 'text',
        sortable: true,
        width: 260,
    },
    { property: 'name', label: t('ct-payment.app.name'), position: 200, renderer: 'text', width: 280 },
    { property: 'status', label: t('ct-payment.app.status'), position: 300, renderer: 'text', width: 140 },
    {
        property: 'createdAt',
        label: t('ct-payment.app.createdAt'),
        position: 400,
        renderer: 'text',
        sortable: true,
        width: 190,
    },
]);
const criteria = computed(() => {
    const query = new Contena.Data.Criteria(page.value, limit.value);
    query.addSorting(Contena.Data.Criteria.sort(sortBy.value, sortDirection.value));
    if (term.value) query.setTerm(term.value);

    return query;
});

const getList = async (): Promise<void> => {
    isLoading.value = true;
    try {
        const result = await appRepository.value.search(criteria.value, Contena.Context.api);
        apps.value = Array.from(result);
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
    apps,
    isLoading,
    sortBy,
    sortDirection,
    appRepository,
    columns,
    criteria,
    getList,
    onTablePageChange,
    onTableLimitChange,
    onTableSortChange,
    formatDate,
});
</script>

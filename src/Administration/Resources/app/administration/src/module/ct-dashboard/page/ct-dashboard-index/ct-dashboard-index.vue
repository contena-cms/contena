<template>
    <ct-block name="sw_dashboard_index">
        <ct-page class="ct-dashboard-index" :show-smart-bar="false">
            <template #content>
                <ct-block name="sw_dashboard_index_content">
                    <main class="ct-dashboard-index__content">
                        <header class="ct-dashboard-index__header">
                            <div>
                                <h1 class="ct-dashboard-index__title">{{ t('ct-dashboard.overview.title') }}</h1>
                                <p class="ct-dashboard-index__updated">{{ lastUpdatedLabel }}</p>
                            </div>
                            <div class="ct-dashboard-index__filters">
                                <MtSegmentedControl disable-context :actions="rangeActions" /><mt-tooltip
                                    :content="t('ct-dashboard.actions.refresh')"
                                    ><template #default="tooltipProps"
                                        ><mt-button
                                            v-bind="tooltipProps"
                                            square
                                            variant="secondary"
                                            :is-loading="isLoading"
                                            :aria-label="t('ct-dashboard.actions.refresh')"
                                            @click="loadDashboard"
                                            ><mt-icon name="regular-sync" size="16px" /></mt-button></template
                                ></mt-tooltip>
                            </div>
                        </header>
                        <mt-empty-state
                            v-if="!canReadPayments && !canReadMembers"
                            icon="regular-chart-bar"
                            :headline="t('ct-dashboard.states.noAccessTitle')"
                            :description="t('ct-dashboard.states.noAccessDescription')"
                        />
                        <template v-else>
                            <section
                                class="ct-dashboard-index__metrics"
                                :aria-label="t('ct-dashboard.metrics.sectionLabel')"
                            >
                                <article v-for="metric in metrics" :key="metric.label" class="ct-dashboard-index__metric">
                                    <div class="ct-dashboard-index__metric-heading">
                                        <span class="ct-dashboard-index__metric-label">{{ metric.label }}</span
                                        ><span class="ct-dashboard-index__metric-icon" :class="`is-${metric.tone}`"
                                            ><mt-icon :name="metric.icon" size="18px"
                                        /></span>
                                    </div>
                                    <strong class="ct-dashboard-index__metric-value">{{ metric.value }}</strong
                                    ><span class="ct-dashboard-index__metric-note">{{ metric.note }}</span>
                                </article>
                            </section>
                            <section class="ct-dashboard-index__primary-grid">
                                <ct-block name="sw_dashboard_index_order_trend">
                                    <mt-card
                                        class="ct-dashboard-index__panel ct-dashboard-index__trend-card"
                                        position-identifier="ct-dashboard-index-order-trend"
                                    >
                                        <template #title>
                                            <div class="ct-dashboard-index__panel-header">
                                                <div>
                                                    <h2>{{ t('ct-dashboard.trend.paymentTitle') }}</h2>
                                                    <p>{{ selectedRangeLabel }}</p>
                                                </div>
                                            </div>
                                        </template>
                                        <div v-if="error" class="ct-dashboard-index__panel-state">
                                            <mt-icon name="regular-exclamation-circle" size="24px" />
                                            <span>{{ t('ct-dashboard.states.loadError') }}</span>
                                        </div>
                                        <apexchart
                                            v-else
                                            type="area"
                                            height="300"
                                            :options="orderTrendChartOptions"
                                            :series="orderTrendSeries"
                                        />
                                    </mt-card>
                                </ct-block>
                            </section>
                            <section class="ct-dashboard-index__orders-section">
                                <ct-block name="sw_dashboard_index_orders"
                                    ><mt-card
                                        class="ct-dashboard-index__panel ct-dashboard-index__orders-card"
                                        position-identifier="ct-dashboard-index-orders"
                                        ><template #title
                                            ><div class="ct-dashboard-index__panel-header">
                                                <h2>{{ t('ct-dashboard.recentOrders.title') }}</h2>
                                                <a class="ct-dashboard-index__view-all" href="#/sw/payment/order/index"
                                                    >{{ t('ct-dashboard.actions.viewAll') }}
                                                    <mt-icon name="regular-long-arrow-right" size="14px"
                                                /></a></div
                                        ></template>
                                        <div v-if="error" class="ct-dashboard-index__panel-state">
                                            <mt-icon name="regular-exclamation-circle" size="24px" /><span>{{
                                                t('ct-dashboard.states.loadError')
                                            }}</span>
                                        </div>
                                        <div v-else-if="!recentOrders.length" class="ct-dashboard-index__panel-state">
                                            <mt-icon name="regular-receipt" size="24px" /><span>{{
                                                t('ct-dashboard.states.noOrders')
                                            }}</span>
                                        </div>
                                        <div v-else class="ct-dashboard-index__table-wrap">
                                            <table class="ct-dashboard-index__orders-table">
                                                <thead>
                                                    <tr>
                                                        <th>{{ t('ct-dashboard.recentOrders.order') }}</th>
                                                        <th>{{ t('ct-dashboard.recentOrders.amount') }}</th>
                                                        <th>{{ t('ct-dashboard.recentOrders.status') }}</th>
                                                        <th>{{ t('ct-dashboard.recentOrders.createdAt') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="order in recentOrders" :key="order.id">
                                                        <td>
                                                            <strong>{{ order.orderNo || '-' }}</strong
                                                            ><span>{{ order.subject || '-' }}</span>
                                                        </td>
                                                        <td class="ct-dashboard-index__amount">
                                                            {{ formatAmount(order.amount || 0, order.currencyCode) }}
                                                        </td>
                                                        <td>
                                                            <mt-badge :variant="statusVariant(order)">{{
                                                                stateLabel(order)
                                                            }}</mt-badge>
                                                        </td>
                                                        <td>{{ formatDate(order.createdAt) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div></mt-card
                                    ></ct-block
                                >
                            </section>
                        </template>
                    </main>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import type { ApexOptions } from 'apexcharts';
import { computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import VueApexCharts from 'vue3-apexcharts';
import MtSegmentedControl from '@contena/meteor-component-library/dist/esm/MtSegmentedControl';
import { useDashboardStatistics, type RangeDays, type RecentOrder } from './dashboard-statistics';
import './ct-dashboard-index.scss';
defineOptions({
    components: { apexchart: VueApexCharts, MtSegmentedControl },
    metaInfo() {
        return { title: this.$createTitle() };
    },
});
defineProps({});
const { locale, t } = useI18n();
const repositoryFactory = inject<RepositoryFactory>('repositoryFactory');
const acl = inject<AclService>('acl');
if (!repositoryFactory || !acl) throw new Error('Dashboard dependencies are unavailable.');
const statistics = useDashboardStatistics(repositoryFactory, acl);
const resolveLocale = (): string => locale.value?.replace('_', '-') || document.documentElement.lang || 'zh-CN';
const formatNumber = (value: number): string => new Intl.NumberFormat(resolveLocale()).format(value);
const formatAmount = (value: number, currency = statistics.currencyCode.value): string =>
    new Intl.NumberFormat(resolveLocale(), { style: 'currency', currency }).format(value / 100);
const formatDate = (value?: string): string =>
    value
        ? new Intl.DateTimeFormat(resolveLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
        : '-';
const comparison = (current: number, previous: number): string =>
    previous === 0
        ? current === 0
            ? t('ct-dashboard.comparison.noChange')
            : t('ct-dashboard.comparison.new')
        : t('ct-dashboard.comparison.value', { value: Math.abs(((current - previous) / previous) * 100).toFixed(1) });
const metrics = computed(() => [
    {
        label: t('ct-dashboard.metrics.paymentAmount', { currency: statistics.currencyCode.value }),
        value: formatAmount(statistics.paymentAmount.value),
        note: comparison(statistics.paymentAmount.value, statistics.previousPaymentAmount.value),
        icon: 'regular-wallet',
        tone: 'blue',
    },
    {
        label: t('ct-dashboard.metrics.successfulOrders'),
        value: formatNumber(statistics.paidOrders.value),
        note: comparison(statistics.paidOrders.value, statistics.previousPaidOrders.value),
        icon: 'regular-receipt',
        tone: 'green',
    },
    {
        label: t('ct-dashboard.metrics.memberTotal'),
        value: formatNumber(statistics.totalMembers.value),
        note: t('ct-dashboard.business.memberTotalNote'),
        icon: 'regular-users',
        tone: 'orange',
    },
    {
        label: t('ct-dashboard.metrics.refundAmount'),
        value: formatAmount(statistics.refundAmount.value),
        note: t('ct-dashboard.business.periodAverage'),
        icon: 'regular-undo',
        tone: 'teal',
    },
    {
        label: t('ct-dashboard.metrics.refundOrders'),
        value: formatNumber(statistics.refundOrders.value),
        note: t('ct-dashboard.business.periodCount'),
        icon: 'regular-receipt',
        tone: 'orange',
    },
    {
        label: t('ct-dashboard.metrics.pendingOrders'),
        value: formatNumber(statistics.pendingOrders.value),
        note: t('ct-dashboard.business.pendingNote'),
        icon: 'regular-clock',
        tone: 'blue',
    },
]);
const rangeActions = computed(() =>
    (
        [
            1,
            7,
            30,
            'all',
        ] as RangeDays[]
    ).map((days) => ({
        id: `range-${days}`,
        label:
            days === 1
                ? t('ct-dashboard.filters.today')
                : days === 'all'
                  ? t('ct-dashboard.filters.all')
                  : t('ct-dashboard.filters.days', { days }),
        isPressed: statistics.selectedRange.value === days,
        onClick: () => statistics.setRange(days),
    })),
);
const selectedRangeLabel = computed(() =>
    statistics.selectedRange.value === 1
        ? t('ct-dashboard.filters.today')
        : statistics.selectedRange.value === 'all'
          ? t('ct-dashboard.filters.all')
          : t('ct-dashboard.filters.days', { days: statistics.selectedRange.value }),
);
const lastUpdatedLabel = computed(() =>
    statistics.lastUpdated.value
        ? t('ct-dashboard.overview.updatedAt', {
              time: new Intl.DateTimeFormat(resolveLocale(), { hour: '2-digit', minute: '2-digit' }).format(
                  statistics.lastUpdated.value,
              ),
          })
        : t('ct-dashboard.overview.loading'),
);
const chartOptions = (color: string, formatter: (value: number) => string): ApexOptions => ({
    chart: { toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: false } },
    colors: [color],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.28, opacityTo: 0.02 } },
    grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
    markers: { size: 0, hover: { size: 5 } },
    xaxis: {
        categories: statistics.orderTrend.value.map((point) => point.date.slice(5)),
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { rotate: 0, hideOverlappingLabels: true },
    },
    yaxis: { min: 0, forceNiceScale: true, labels: { formatter } },
    tooltip: { y: { formatter } },
    noData: { text: t('ct-dashboard.states.noData') },
});
const orderTrendSeries = computed(() => [
    { name: t('ct-dashboard.trend.pendingOrders'), data: statistics.pendingTrend.value.map((point) => point.value) },
    { name: t('ct-dashboard.trend.paidOrders'), data: statistics.orderTrend.value.map((point) => point.value) },
]);
const orderTrendChartOptions = computed(() => ({
    ...chartOptions('#246bfd', (value) => formatNumber(Math.round(value))),
    colors: [
        '#246bfd',
        '#159c8c',
    ],
    tooltip: { shared: true, intersect: false, y: { formatter: (value: number) => formatNumber(value) } },
    legend: { show: true, position: 'top', horizontalAlign: 'right', markers: { size: 7 }, itemMargin: { horizontal: 10 } },
}));
const stateLabel = (order: RecentOrder): string =>
    order.state?.translated?.name || order.state?.technicalName || t('ct-dashboard.recentOrders.unknownStatus');
const statusVariant = (order: RecentOrder): 'neutral' | 'positive' | 'attention' | 'critical' =>
    order.state?.technicalName === 'succeeded'
        ? 'positive'
        : order.state?.technicalName === 'failed'
          ? 'critical'
          : 'attention';
void statistics.loadDashboard();
const {
    selectedRange,
    paymentAmount,
    previousPaymentAmount,
    paidOrders,
    previousPaidOrders,
    averageOrderValue,
    pendingOrders,
    refundAmount,
    refundOrders,
    totalMembers,
    newMembers,
    orderTrend,
    pendingTrend,
    recentOrders,
    isLoading,
    error,
    lastUpdated,
    canReadPayments,
    canReadMembers,
    loadDashboard,
    setRange,
} = statistics;
swDefinePublic({
    metrics,
    rangeActions,
    selectedRangeLabel,
    lastUpdatedLabel,
    orderTrendSeries,
    orderTrendChartOptions,
    formatNumber,
    formatAmount,
    formatDate,
    selectedRange,
    paymentAmount,
    previousPaymentAmount,
    paidOrders,
    previousPaidOrders,
    averageOrderValue,
    pendingOrders,
    refundAmount,
    refundOrders,
    totalMembers,
    newMembers,
    orderTrend,
    pendingTrend,
    recentOrders,
    isLoading,
    error,
    lastUpdated,
    canReadPayments,
    canReadMembers,
    loadDashboard,
    setRange,
});
defineExpose({
    metrics,
    rangeActions,
    selectedRangeLabel,
    lastUpdatedLabel,
    orderTrendSeries,
    orderTrendChartOptions,
    formatNumber,
    formatAmount,
    formatDate,
    selectedRange,
    paymentAmount,
    previousPaymentAmount,
    paidOrders,
    previousPaidOrders,
    averageOrderValue,
    refundAmount,
    refundOrders,
    totalMembers,
    newMembers,
    orderTrend,
    pendingOrders,
    pendingTrend,
    recentOrders,
    isLoading,
    error,
    lastUpdated,
    canReadPayments,
    canReadMembers,
    loadDashboard,
    setRange,
});
</script>

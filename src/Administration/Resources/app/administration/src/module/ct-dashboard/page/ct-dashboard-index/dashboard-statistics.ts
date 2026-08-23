import type RepositoryFactory from 'src/core/data/repository-factory.data';
import type AclService from 'src/app/service/acl.service';
import { computed, ref } from 'vue';

/** @private */
export type RangeDays = 1 | 7 | 30 | 'all';
/** @private */
export type TrendPoint = { date: string; value: number };
/** @private */
export type RecentOrder = {
    id: string;
    orderNo?: string;
    subject?: string;
    amount?: number;
    currencyCode?: string;
    createdAt?: string;
    state?: { technicalName?: string; translated?: { name?: string } };
};
type Aggregation = { count?: number; sum?: number; buckets?: Array<{ key: string; count: number }> };
type SearchResult = {
    aggregations?: Record<string, Aggregation>;
    total?: number;
    [Symbol.iterator]?: () => Iterator<RecentOrder>;
};
type Period = { start: Date; end: Date; previousStart: Date; previousEnd: Date };
const { Criteria } = Contena.Data;
function startOfDay(date: Date): Date {
    const value = new Date(date);
    value.setHours(0, 0, 0, 0);
    return value;
}
function endOfDay(date: Date): Date {
    const value = new Date(date);
    value.setHours(23, 59, 59, 999);
    return value;
}
function addDays(date: Date, amount: number): Date {
    const value = new Date(date);
    value.setDate(value.getDate() + amount);
    return value;
}
function dateKey(date: Date): string {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}
function createPeriod(days: RangeDays): Period {
    const end = endOfDay(new Date());
    if (days === 'all') {
        const start = new Date(0);
        return { start, end, previousStart: start, previousEnd: end };
    }
    const start = startOfDay(addDays(end, -(days - 1)));
    const previousEnd = new Date(start.getTime() - 1);
    const previousStart = startOfDay(addDays(start, -days));
    return { start, end, previousStart, previousEnd };
}
function rangeFilter(field: string, start: Date, end: Date) {
    return Criteria.range(field, { gte: start.toISOString(), lte: end.toISOString() });
}
function count(result: SearchResult, name: string): number {
    return result.aggregations?.[name]?.count ?? 0;
}
function sum(result: SearchResult, name: string): number {
    return result.aggregations?.[name]?.sum ?? 0;
}
function fillTrend(days: RangeDays, buckets: Array<{ key: string; count: number }> = []): TrendPoint[] {
    if (days === 'all') days = 30;
    const map = new Map(
        buckets.map((bucket) => [
            bucket.key.slice(0, 10),
            bucket.count,
        ]),
    );
    const period = createPeriod(days);
    return Array.from({ length: days }, (_, index) => {
        const date = addDays(period.start, index);
        return { date: dateKey(date), value: map.get(dateKey(date)) ?? 0 };
    });
}

/** @private */
export function useDashboardStatistics(repositoryFactory: RepositoryFactory, acl: AclService) {
    const paymentRepository = repositoryFactory.create('payment_order');
    const refundRepository = repositoryFactory.create('payment_refund');
    const memberRepository = repositoryFactory.create('member');
    const selectedRange = ref<RangeDays>(1);
    const currencyCode = ref('CNY');
    const paymentAmount = ref(0);
    const previousPaymentAmount = ref(0);
    const paidOrders = ref(0);
    const pendingOrders = ref(0);
    const previousPaidOrders = ref(0);
    const averageOrderValue = computed(() => (paidOrders.value > 0 ? paymentAmount.value / paidOrders.value : 0));
    const refundAmount = ref(0);
    const refundOrders = ref(0);
    const totalMembers = ref(0);
    const newMembers = ref(0);
    const previousNewMembers = ref(0);
    const orderTrend = ref<TrendPoint[]>([]);
    const pendingTrend = ref<TrendPoint[]>([]);
    const recentOrders = ref<RecentOrder[]>([]);
    const isLoading = ref(true);
    const error = ref(false);
    const lastUpdated = ref<Date | null>(null);
    const canReadPayments = computed(() => acl.can('payment.viewer'));
    const canReadMembers = computed(() => acl.can('member.viewer'));

    async function loadPaymentStatistics(period: Period): Promise<void> {
        if (!canReadPayments.value) return;
        const currentFilters = [
            ...(selectedRange.value === 'all' ? [] : [rangeFilter('createdAt', period.start, period.end)]),
            Criteria.equals('currencyCode', currencyCode.value),
            Criteria.equals('state.technicalName', 'succeeded'),
        ];
        const pendingFilters = [
            ...(selectedRange.value === 'all' ? [] : [rangeFilter('createdAt', period.start, period.end)]),
            Criteria.equals('currencyCode', currencyCode.value),
            Criteria.equals('state.technicalName', 'pending'),
        ];
        const previousFilters = [
            ...(selectedRange.value === 'all' ? [] : [rangeFilter('createdAt', period.previousStart, period.previousEnd)]),
            Criteria.equals('currencyCode', currencyCode.value),
            Criteria.equals('state.technicalName', 'succeeded'),
        ];
        const criteria = new Criteria(1, 1);
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        criteria.addAggregation(Criteria.filter('paidAmountFilter', currentFilters, Criteria.sum('paidAmount', 'amount')));
        criteria.addAggregation(Criteria.filter('paidOrdersFilter', currentFilters, Criteria.count('paidOrders', 'id')));
        criteria.addAggregation(
            Criteria.filter('pendingOrdersFilter', pendingFilters, Criteria.count('pendingOrders', 'id')),
        );
        criteria.addAggregation(
            Criteria.filter('previousAmountFilter', previousFilters, Criteria.sum('previousAmount', 'amount')),
        );
        criteria.addAggregation(
            Criteria.filter('previousOrdersFilter', previousFilters, Criteria.count('previousOrders', 'id')),
        );
        criteria.addAggregation(
            Criteria.filter(
                'orderTrendFilter',
                currentFilters,
                Criteria.histogram('orderTrend', 'createdAt', 'day', null, null, timezone),
            ),
        );
        criteria.addAggregation(
            Criteria.filter(
                'pendingTrendFilter',
                pendingFilters,
                Criteria.histogram('pendingTrend', 'createdAt', 'day', null, null, timezone),
            ),
        );
        const refundCriteria = new Criteria(1, 1);
        if (selectedRange.value !== 'all') refundCriteria.addFilter(rangeFilter('successTime', period.start, period.end));
        refundCriteria.addFilter(Criteria.equals('status', 2));
        refundCriteria.addAggregation(Criteria.sum('refundAmount', 'refundAmount'));
        refundCriteria.addAggregation(Criteria.count('refundOrders', 'id'));
        const [
            result,
            refunds,
        ] = (await Promise.all([
            paymentRepository.search(criteria, Contena.Context.api),
            refundRepository.search(refundCriteria, Contena.Context.api),
        ])) as [SearchResult, SearchResult];
        paymentAmount.value = sum(result, 'paidAmount');
        previousPaymentAmount.value = sum(result, 'previousAmount');
        paidOrders.value = count(result, 'paidOrders');
        pendingOrders.value = count(result, 'pendingOrders');
        previousPaidOrders.value = count(result, 'previousOrders');
        refundAmount.value = sum(refunds, 'refundAmount');
        refundOrders.value = count(refunds, 'refundOrders');
        orderTrend.value = fillTrend(selectedRange.value, result.aggregations?.orderTrend?.buckets);
        pendingTrend.value = fillTrend(selectedRange.value, result.aggregations?.pendingTrend?.buckets);
    }
    async function loadMemberStatistics(period: Period): Promise<void> {
        if (!canReadMembers.value) return;
        const criteria = new Criteria(1, 1);
        criteria.addAggregation(Criteria.count('totalMembers', 'id'));
        const currentFilters = selectedRange.value === 'all' ? [] : [rangeFilter('createdAt', period.start, period.end)];
        const previousFilters =
            selectedRange.value === 'all' ? [] : [rangeFilter('createdAt', period.previousStart, period.previousEnd)];
        criteria.addAggregation(Criteria.filter('newMembersFilter', currentFilters, Criteria.count('newMembers', 'id')));
        criteria.addAggregation(
            Criteria.filter('previousMembersFilter', previousFilters, Criteria.count('previousMembers', 'id')),
        );
        const result = (await memberRepository.search(criteria, Contena.Context.api)) as SearchResult;
        totalMembers.value = count(result, 'totalMembers');
        newMembers.value = count(result, 'newMembers');
        previousNewMembers.value = count(result, 'previousMembers');
    }
    async function loadRecentOrders(): Promise<void> {
        if (!canReadPayments.value) return;
        const criteria = new Criteria(1, 6);
        criteria.addAssociation('state');
        criteria.addSorting(Criteria.sort('createdAt', 'DESC'));
        const result = (await paymentRepository.search(criteria, Contena.Context.api)) as SearchResult;
        recentOrders.value = result[Symbol.iterator] ? Array.from(result as Iterable<RecentOrder>) : [];
    }
    async function loadDashboard(): Promise<void> {
        isLoading.value = true;
        error.value = false;
        const period = createPeriod(selectedRange.value);
        try {
            await Promise.all([
                loadPaymentStatistics(period),
                loadMemberStatistics(period),
                loadRecentOrders(),
            ]);
        } catch {
            error.value = true;
        }
        lastUpdated.value = new Date();
        isLoading.value = false;
    }
    async function setRange(days: RangeDays): Promise<void> {
        if (selectedRange.value === days) return;
        selectedRange.value = days;
        await loadDashboard();
    }
    return {
        selectedRange,
        currencyCode,
        paymentAmount,
        previousPaymentAmount,
        paidOrders,
        pendingOrders,
        previousPaidOrders,
        averageOrderValue,
        refundAmount,
        refundOrders,
        totalMembers,
        newMembers,
        previousNewMembers,
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
    };
}

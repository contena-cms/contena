<template>
    <ct-block name="sw_dashboard_index">
        <ct-page class="ct-dashboard-index" :show-smart-bar="false" :show-search-bar="false">
            <template #content>
                <ct-block name="sw_dashboard_index_content">
                    <div class="ct-dashboard-index__shell">
                        <header class="ct-dashboard-index__topbar">
                            <div class="ct-dashboard-index__topbar-left">
                                <a-button
                                    class="ct-dashboard-index__mobile-menu"
                                    type="text"
                                    shape="circle"
                                    :aria-label="t('ct-dashboard.preview.openMenu')"
                                    @click="toggleMobileMenu"
                                >
                                    <ct-icon name="MenuOutlined" :size="18" />
                                </a-button>
                                <a-breadcrumb :items="breadcrumbs" />
                            </div>

                            <div class="ct-dashboard-index__global-actions">
                                <a-input
                                    v-model:value="globalSearchTerm"
                                    class="ct-dashboard-index__global-search"
                                    :placeholder="t('ct-dashboard.preview.globalSearch')"
                                    allow-clear
                                >
                                    <template #prefix><ct-icon name="SearchOutlined" /></template>
                                    <template #suffix><span class="ct-dashboard-index__search-key">⌘ K</span></template>
                                </a-input>
                                <a-tooltip :title="t('ct-dashboard.preview.help')">
                                    <a-button type="text" shape="circle"><ct-icon name="QuestionCircleOutlined" /></a-button>
                                </a-tooltip>
                                <a-badge :count="5" size="small">
                                    <a-button type="text" shape="circle"><ct-icon name="BellOutlined" /></a-button>
                                </a-badge>
                            </div>
                        </header>

                        <main class="ct-dashboard-index__workspace">
                            <header class="ct-dashboard-index__page-header">
                                <div>
                                    <p class="ct-dashboard-index__date">{{ currentDate }}</p>
                                    <h1>{{ t('ct-dashboard.home.welcome', { name: currentUserName }) }}</h1>
                                    <p>{{ t('ct-dashboard.home.summary') }}</p>
                                </div>

                                <a-space wrap>
                                    <a-button>
                                        <template #icon><ct-icon name="FolderOpenOutlined" /></template>
                                        {{ t('ct-dashboard.home.actions.media') }}
                                    </a-button>
                                    <a-button>
                                        <template #icon><ct-icon name="AuditOutlined" /></template>
                                        {{ t('ct-dashboard.home.actions.workflow') }}
                                    </a-button>
                                    <a-button type="primary">
                                        <template #icon><ct-icon name="PlusOutlined" /></template>
                                        {{ t('ct-dashboard.home.actions.createContent') }}
                                    </a-button>
                                </a-space>
                            </header>

                            <ct-block name="sw_dashboard_index_summary">
                                <section
                                    class="ct-dashboard-index__summary"
                                    :aria-label="t('ct-dashboard.home.metrics.label')"
                                >
                                    <article
                                        v-for="metric in metrics"
                                        :key="metric.label"
                                        class="ct-dashboard-index__metric"
                                    >
                                        <div class="ct-dashboard-index__metric-icon" :class="`is--${metric.tone}`">
                                            <ct-icon :name="metric.icon" :size="20" />
                                        </div>
                                        <div>
                                            <div class="ct-dashboard-index__metric-label">{{ t(metric.label) }}</div>
                                            <div class="ct-dashboard-index__metric-value">{{ metric.value }}</div>
                                            <div
                                                class="ct-dashboard-index__metric-change"
                                                :class="`is--${metric.state}`"
                                            >
                                                {{ metric.change }}
                                            </div>
                                        </div>
                                    </article>
                                </section>
                            </ct-block>

                            <div class="ct-dashboard-index__primary-grid">
                                <section class="ct-dashboard-index__panel ct-dashboard-index__trend-panel">
                                    <div class="ct-dashboard-index__panel-header">
                                        <div>
                                            <h2>{{ t('ct-dashboard.preview.trend.title') }}</h2>
                                            <p>{{ t('ct-dashboard.preview.trend.description') }}</p>
                                        </div>
                                        <a-segmented v-model:value="trendPeriod" :options="trendPeriodOptions" size="small" />
                                    </div>

                                    <div class="ct-dashboard-index__trend-summary">
                                        <div>
                                            <span>{{ t('ct-dashboard.preview.trend.visits') }}</span>
                                            <strong>248,619</strong>
                                            <em>+16.8%</em>
                                        </div>
                                        <div>
                                            <span>{{ t('ct-dashboard.preview.trend.conversion') }}</span>
                                            <strong>8.72%</strong>
                                            <em>+1.4%</em>
                                        </div>
                                        <div>
                                            <span>{{ t('ct-dashboard.preview.trend.averageTime') }}</span>
                                            <strong>04:26</strong>
                                            <em>+32s</em>
                                        </div>
                                    </div>

                                    <div class="ct-dashboard-index__trend-chart" aria-hidden="true">
                                        <span
                                            v-for="(point, index) in trendPoints"
                                            :key="index"
                                            :style="{ height: `${point}%` }"
                                        ></span>
                                    </div>
                                    <div class="ct-dashboard-index__trend-axis" aria-hidden="true">
                                        <span v-for="label in trendLabels" :key="label">{{ label }}</span>
                                    </div>
                                </section>

                                <section class="ct-dashboard-index__panel">
                                    <div class="ct-dashboard-index__panel-header">
                                        <div>
                                            <h2>{{ t('ct-dashboard.home.tasks.title') }}</h2>
                                            <p>{{ t('ct-dashboard.preview.tasksDescription') }}</p>
                                        </div>
                                        <a-button type="link" size="small">{{ t('ct-dashboard.home.viewAll') }}</a-button>
                                    </div>

                                    <a-list :data-source="tasks" item-layout="horizontal">
                                        <template #renderItem="{ item }">
                                            <a-list-item>
                                                <a-list-item-meta :description="t(item.description)">
                                                    <template #avatar>
                                                        <a-avatar :class="`is--${item.tone}`">
                                                            <ct-icon :name="item.icon" />
                                                        </a-avatar>
                                                    </template>
                                                    <template #title>{{ t(item.title) }}</template>
                                                </a-list-item-meta>
                                                <a-tag :color="item.color">{{ item.count }}</a-tag>
                                            </a-list-item>
                                        </template>
                                    </a-list>
                                </section>
                            </div>

                            <section class="ct-dashboard-index__panel ct-dashboard-index__recent-panel">
                                <div class="ct-dashboard-index__panel-header">
                                    <div>
                                        <h2>{{ t('ct-dashboard.home.recent.title') }}</h2>
                                        <p>{{ t('ct-dashboard.preview.recentDescription') }}</p>
                                    </div>
                                    <a-input-search
                                        v-model:value="dashboardSearchTerm"
                                        class="ct-dashboard-index__panel-search"
                                        :placeholder="t('ct-dashboard.home.recent.searchPlaceholder')"
                                        allow-clear
                                    />
                                </div>

                                <a-table
                                    :columns="dashboardColumns"
                                    :data-source="filteredDashboardRecords"
                                    :pagination="false"
                                    row-key="id"
                                    size="middle"
                                    :scroll="{ x: 760 }"
                                >
                                    <template #bodyCell="{ column, record }">
                                        <template v-if="column.key === 'title'">
                                            <div class="ct-dashboard-index__content-title">
                                                <a-avatar shape="square"><ct-icon name="FileTextOutlined" /></a-avatar>
                                                <div>
                                                    <strong>{{ record.title }}</strong>
                                                    <span>{{ record.slug }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else-if="column.key === 'status'">
                                            <a-badge
                                                :status="statusDisplay[record.status].badge"
                                                :text="t(statusDisplay[record.status].label)"
                                            />
                                        </template>
                                        <template v-else-if="column.key === 'updatedAt'">
                                            <span class="ct-dashboard-index__muted">{{ record.updatedAt }}</span>
                                        </template>
                                    </template>
                                </a-table>
                            </section>
                        </main>
                    </div>
                </ct-block>
            </template>
        </ct-page>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineOptions({
    metaInfo() {
        return { title: this.$createTitle() };
    },
});

defineProps({});

type PreviewStatus = 'published' | 'review' | 'draft';

interface DashboardRecord {
    id: string;
    title: string;
    slug: string;
    owner: string;
    status: PreviewStatus;
    updatedAt: string;
}

const { locale, t } = useI18n();
const activeLocale = computed(() => locale.value || 'zh-CN');
const globalSearchTerm = ref('');
const dashboardSearchTerm = ref('');
const trendPeriod = ref('30d');
const breadcrumbs = computed(() => [{ title: t('ct-dashboard.general.mainMenuItemGeneral') }]);
const currentUserName = computed(() => {
    const user = Contena.Store.get('session').currentUser;

    return user?.firstName || user?.name || user?.username || t('ct-dashboard.home.defaultUser');
});
const currentDate = computed(() =>
    new Intl.DateTimeFormat(activeLocale.value, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(new Date()),
);
const metrics = computed(() => [
    {
        label: 'ct-dashboard.home.metrics.published',
        value: '1,286',
        change: '+12.5%',
        state: 'positive',
        tone: 'blue',
        icon: 'FileTextOutlined',
    },
    {
        label: 'ct-dashboard.home.metrics.pending',
        value: '24',
        change: t('ct-dashboard.home.metrics.pendingHint'),
        state: 'warning',
        tone: 'orange',
        icon: 'ClockCircleOutlined',
    },
    {
        label: 'ct-dashboard.home.metrics.media',
        value: '8,642',
        change: '+184',
        state: 'positive',
        tone: 'green',
        icon: 'FolderOpenOutlined',
    },
    {
        label: 'ct-dashboard.home.metrics.members',
        value: '36,920',
        change: '+8.2%',
        state: 'positive',
        tone: 'cyan',
        icon: 'TeamOutlined',
    },
]);
const tasks = [
    {
        title: 'ct-dashboard.home.tasks.reviewTitle',
        description: 'ct-dashboard.home.tasks.reviewDescription',
        count: 12,
        color: 'orange',
        tone: 'orange',
        icon: 'AuditOutlined',
    },
    {
        title: 'ct-dashboard.home.tasks.publishTitle',
        description: 'ct-dashboard.home.tasks.publishDescription',
        count: 8,
        color: 'blue',
        tone: 'blue',
        icon: 'CloudUploadOutlined',
    },
    {
        title: 'ct-dashboard.home.tasks.completedTitle',
        description: 'ct-dashboard.home.tasks.completedDescription',
        count: 31,
        color: 'green',
        tone: 'green',
        icon: 'CheckCircleOutlined',
    },
];
const trendPoints = [42, 58, 48, 66, 62, 78, 72, 84, 68, 88, 82, 96];
const trendLabels = ['08/01', '08/05', '08/09', '08/13', '08/17', '08/21'];
const trendPeriodOptions = computed(() => [
    { label: t('ct-dashboard.preview.trend.sevenDays'), value: '7d' },
    { label: t('ct-dashboard.preview.trend.thirtyDays'), value: '30d' },
    { label: t('ct-dashboard.preview.trend.quarter'), value: 'quarter' },
]);
const statusDisplay: Record<PreviewStatus, { label: string; badge: 'success' | 'warning' | 'default' }> = {
    published: { label: 'ct-dashboard.home.status.published', badge: 'success' },
    review: { label: 'ct-dashboard.home.status.review', badge: 'warning' },
    draft: { label: 'ct-dashboard.home.status.draft', badge: 'default' },
};
const dashboardRecords: DashboardRecord[] = [
    {
        id: 'content-1',
        title: t('ct-dashboard.home.recent.items.one'),
        slug: '/insights/product-capabilities-2026',
        owner: '陈晓宁',
        status: 'published',
        updatedAt: t('ct-dashboard.home.recent.times.minutes'),
    },
    {
        id: 'content-2',
        title: t('ct-dashboard.home.recent.items.two'),
        slug: '/solutions/content-operations',
        owner: '王若琳',
        status: 'review',
        updatedAt: t('ct-dashboard.home.recent.times.hours'),
    },
    {
        id: 'content-3',
        title: t('ct-dashboard.home.recent.items.three'),
        slug: '/guides/platform',
        owner: '李明远',
        status: 'draft',
        updatedAt: t('ct-dashboard.home.recent.times.yesterday'),
    },
];
const dashboardColumns = computed(() => [
    { title: t('ct-dashboard.home.recent.columns.title'), key: 'title', dataIndex: 'title' },
    { title: t('ct-dashboard.home.recent.columns.owner'), key: 'owner', dataIndex: 'owner', width: 120 },
    { title: t('ct-dashboard.home.recent.columns.status'), key: 'status', dataIndex: 'status', width: 120 },
    { title: t('ct-dashboard.home.recent.columns.updatedAt'), key: 'updatedAt', dataIndex: 'updatedAt', width: 140 },
]);
const filteredDashboardRecords = computed(() => {
    const term = dashboardSearchTerm.value.trim().toLocaleLowerCase(activeLocale.value);

    return term
        ? dashboardRecords.filter((record) => record.title.toLocaleLowerCase(activeLocale.value).includes(term))
        : dashboardRecords;
});
const toggleMobileMenu = (): void => {
    Contena.Utils.EventBus.emit('ct-admin-menu/toggle-offcanvas', true);
};

swDefinePublic({
    globalSearchTerm,
    dashboardSearchTerm,
    trendPeriod,
    breadcrumbs,
    currentUserName,
    currentDate,
    metrics,
    tasks,
    trendPoints,
    trendLabels,
    trendPeriodOptions,
    statusDisplay,
    dashboardColumns,
    filteredDashboardRecords,
    toggleMobileMenu,
});
</script>

<style lang="scss">
.ct-dashboard-index {
    .ct-page__main-content-inner {
        background: var(--ct-color-bg-layout);
    }

    &__shell {
        display: grid;
        grid-template-rows: var(--ct-layout-topbar-height) minmax(0, 1fr);
        width: 100%;
        min-height: 100%;
    }

    &__topbar {
        position: sticky;
        top: 0;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 0;
        gap: 24px;
        padding-inline: var(--ct-spacing-lg);
        background: var(--ct-color-bg-container);
        border-bottom: 1px solid var(--ct-color-border-secondary);
    }

    &__topbar-left,
    &__global-actions {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    &__topbar-left {
        gap: 10px;
    }

    &__global-actions {
        gap: 6px;
    }

    &__global-search {
        width: 300px;
    }

    &__search-key {
        padding: 1px 5px;
        color: var(--ct-color-text-tertiary);
        background: var(--ct-color-bg-layout);
        border: 1px solid var(--ct-color-border);
        border-radius: var(--ct-border-radius);
        font-size: 10px;
        line-height: 16px;
    }

    &__mobile-menu {
        display: none;
    }

    &__workspace {
        width: 100%;
        max-width: var(--ct-layout-content-max-width);
        margin-inline: auto;
        padding: var(--ct-spacing-lg) var(--ct-spacing-lg) var(--ct-spacing-xl);
    }

    &__page-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        margin-bottom: 20px;

        h1 {
            margin: 2px 0 5px;
            color: var(--ct-color-text);
            font-size: var(--ct-font-size-heading-3);
            font-weight: 600;
            line-height: 34px;
            letter-spacing: 0;
        }

        p {
            margin: 0;
            color: var(--ct-color-text-secondary);
            font-size: var(--ct-font-size);
            line-height: 22px;
        }

        .ct-dashboard-index__date {
            color: var(--ct-color-text-tertiary);
            font-size: var(--ct-font-size-sm);
            line-height: 18px;
        }
    }

    &__summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 18px;
        background: var(--ct-color-bg-container);
        border: 1px solid var(--ct-color-border-secondary);
        border-radius: var(--ct-border-radius);
    }

    &__metric {
        position: relative;
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 14px;
        padding: 20px;

        & + &::before {
            position: absolute;
            inset: 20px auto 20px 0;
            width: 1px;
            background: var(--ct-color-border-secondary);
            content: '';
        }
    }

    &__metric-icon {
        display: grid;
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: var(--ct-border-radius);

        &.is--blue {
            color: var(--ct-color-primary);
            background: var(--ct-color-primary-bg);
        }

        &.is--orange {
            color: var(--ct-color-warning);
            background: var(--ct-color-warning-bg);
        }

        &.is--green {
            color: var(--ct-color-success);
            background: var(--ct-color-success-bg);
        }

        &.is--cyan {
            color: var(--ct-color-primary);
            background: var(--ct-color-primary-bg);
        }
    }

    &__metric-label {
        color: var(--ct-color-text-secondary);
        font-size: 13px;
        line-height: 20px;
    }

    &__metric-value {
        margin-top: 2px;
        color: var(--ct-color-text);
        font-size: 25px;
        font-weight: 600;
        line-height: 32px;
    }

    &__metric-change {
        margin-top: 2px;
        color: var(--ct-color-success);
        font-size: 12px;
        line-height: 18px;

        &.is--warning {
            color: var(--ct-color-warning);
        }
    }

    &__primary-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(320px, 0.85fr);
        gap: 18px;
        margin-bottom: 18px;
    }

    &__panel {
        min-width: 0;
        padding: 18px 20px;
        background: var(--ct-color-bg-container);
        border: 1px solid var(--ct-color-border-secondary);
        border-radius: var(--ct-border-radius);
    }

    &__panel-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        min-width: 0;
        gap: 16px;
        margin-bottom: 14px;

        h2 {
            margin: 0;
            color: var(--ct-color-text);
            font-size: 16px;
            font-weight: 600;
            line-height: 24px;
            letter-spacing: 0;
        }

        p {
            margin: 2px 0 0;
            color: var(--ct-color-text-tertiary);
            font-size: 12px;
            line-height: 18px;
        }
    }

    &__trend-summary {
        display: flex;
        gap: 36px;
        margin: 4px 0 20px;

        div {
            display: grid;
            grid-template-columns: auto auto;
            align-items: baseline;
            column-gap: 8px;
        }

        span {
            grid-column: 1 / -1;
            color: var(--ct-color-text-tertiary);
            font-size: 12px;
        }

        strong {
            color: var(--ct-color-text);
            font-size: 20px;
            font-weight: 600;
        }

        em {
            color: var(--ct-color-success);
            font-size: 11px;
            font-style: normal;
        }
    }

    &__trend-chart {
        display: flex;
        align-items: flex-end;
        justify-content: space-around;
        height: 132px;
        gap: 8px;
        padding: 10px 4px 0;
        background-image: linear-gradient(to bottom, var(--ct-color-border-secondary) 1px, transparent 1px);
        background-size: 100% 33%;
        border-bottom: 1px solid var(--ct-color-border-secondary);

        span {
            flex: 0 1 36px;
            width: 4vw;
            min-width: 5px;
            max-width: 36px;
            background: var(--ct-color-primary);
            border-radius: 3px 3px 0 0;
            opacity: 0.9;
        }

        span:nth-child(3n) {
            background: var(--ct-color-success);
        }
    }

    &__trend-axis {
        display: flex;
        justify-content: space-between;
        margin-top: 7px;
        color: var(--ct-color-text-tertiary);
        font-size: 10px;
    }

    &__panel .ant-list-item {
        padding: 12px 0;
    }

    &__panel .ant-list-item-meta-title {
        margin-bottom: 2px;
        color: var(--ct-color-text);
        font-size: 13px;
        line-height: 20px;
    }

    &__panel .ant-list-item-meta-description {
        color: var(--ct-color-text-tertiary);
        font-size: 12px;
    }

    &__panel .ant-list-item-meta-avatar .ant-avatar {
        display: grid;
        color: var(--ct-color-primary);
        background: var(--ct-color-primary-bg);

        &.is--orange {
            color: var(--ct-color-warning);
            background: var(--ct-color-warning-bg);
        }

        &.is--green {
            color: var(--ct-color-success);
            background: var(--ct-color-success-bg);
        }
    }

    &__recent-panel {
        padding-bottom: 6px;
    }

    &__panel-search {
        width: 220px;
    }

    &__content-title {
        display: flex;
        align-items: center;
        min-width: 0;
        gap: 10px;

        .ant-avatar {
            flex: 0 0 auto;
            color: var(--ct-color-primary);
            background: var(--ct-color-primary-bg);
        }

        div {
            display: flex;
            min-width: 0;
            flex-direction: column;
        }

        strong,
        span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        strong {
            color: var(--ct-color-text);
            font-size: 13px;
            font-weight: 500;
            line-height: 20px;
        }

        span {
            color: var(--ct-color-text-tertiary);
            font-size: 11px;
            line-height: 18px;
        }
    }

    &__muted {
        color: var(--ct-color-text-tertiary);
        font-size: 12px;
    }

    @media screen and (max-width: 1280px) {
        &__mobile-menu {
            display: inline-flex;
        }

        &__workspace {
            padding-inline: 22px;
        }

        &__summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        &__metric:nth-child(3)::before {
            display: none;
        }

        &__metric:nth-child(n + 3) {
            border-top: 1px solid var(--ct-color-border-secondary);
        }
    }

    @media screen and (max-width: 900px) {
        &__global-search {
            width: 220px;
        }

        &__primary-grid {
            grid-template-columns: 1fr;
        }
    }

    @media screen and (max-width: 640px) {
        &__topbar {
            padding-inline: var(--ct-spacing-sm);
        }

        &__topbar .ant-breadcrumb,
        &__global-search {
            display: none;
        }

        &__workspace {
            padding: var(--ct-spacing-md) var(--ct-spacing-sm) var(--ct-spacing-xl);
        }

        &__page-header,
        &__panel-header {
            align-items: flex-start;
            flex-direction: column;
        }

        &__page-header h1 {
            font-size: 21px;
        }

        &__summary {
            grid-template-columns: 1fr;
        }

        &__metric + &__metric::before {
            display: none;
        }

        &__metric:nth-child(n + 2) {
            border-top: 1px solid var(--ct-color-border-secondary);
        }

        &__trend-summary {
            flex-wrap: wrap;
            gap: 14px 26px;
        }

        &__panel-search {
            width: 100%;
        }
    }
}
</style>

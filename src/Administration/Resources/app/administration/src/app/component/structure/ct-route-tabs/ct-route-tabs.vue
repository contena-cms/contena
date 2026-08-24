<template>
    <ct-block name="sw_route_tabs">
        <div class="ct-route-tabs">
            <a-tabs
                :active-key="activeKey"
                type="editable-card"
                hide-add
                size="small"
                @change="onTabChange"
                @edit="onTabEdit"
            >
                <a-tab-pane v-for="tab in routeTabs.tabs" :key="tab.key" :closable="!tab.pinned">
                    <template #tab>
                        <span class="ct-route-tabs__label" :title="tab.title">{{ tab.title }}</span>
                    </template>
                </a-tab-pane>

                <template #rightExtra>
                    <a-dropdown :trigger="['click']" placement="bottomRight">
                        <a-button
                            class="ct-route-tabs__actions"
                            type="text"
                            :aria-label="$t('global.ct-route-tabs.actions')"
                        >
                            <template #icon><ct-icon name="MoreOutlined" /></template>
                        </a-button>
                        <template #overlay>
                            <a-menu @click="onMenuClick($event.key)">
                                <a-menu-item key="refresh">
                                    <ct-icon name="ReloadOutlined" />
                                    {{ $t('global.ct-route-tabs.refresh') }}
                                </a-menu-item>
                                <a-menu-divider />
                                <a-menu-item key="current" :disabled="isActiveTabPinned">
                                    {{ $t('global.ct-route-tabs.closeCurrent') }}
                                </a-menu-item>
                                <a-menu-item key="left" :disabled="!hasClosableTabOnLeft">
                                    {{ $t('global.ct-route-tabs.closeLeft') }}
                                </a-menu-item>
                                <a-menu-item key="right" :disabled="!hasClosableTabOnRight">
                                    {{ $t('global.ct-route-tabs.closeRight') }}
                                </a-menu-item>
                                <a-menu-item key="others" :disabled="routeTabs.tabs.length <= 1">
                                    {{ $t('global.ct-route-tabs.closeOthers') }}
                                </a-menu-item>
                                <a-menu-item key="all" :disabled="!hasClosableTabs">
                                    {{ $t('global.ct-route-tabs.closeAll') }}
                                </a-menu-item>
                            </a-menu>
                        </template>
                    </a-dropdown>
                </template>
            </a-tabs>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, type RouteLocationNormalizedLoaded } from 'vue-router';
import { DASHBOARD_ROUTE_NAME, type RouteTabCloseMode } from 'src/app/store/route-tabs.store';

interface RouteModuleMeta {
    title?: string;
}

interface RouteNavigationMeta {
    label?: string;
}

const route = useRoute();
const router = useRouter();
const { t, te, locale } = useI18n();
const routeTabs = Contena.Store.get('routeTabs');

const activeKey = computed(() => route.fullPath);
const activeIndex = computed(() => routeTabs.tabs.findIndex((tab) => tab.key === activeKey.value));
const activeTab = computed(() => routeTabs.tabs[activeIndex.value]);
const isActiveTabPinned = computed(() => activeTab.value?.pinned ?? false);
const hasClosableTabs = computed(() => routeTabs.tabs.some((tab) => !tab.pinned));
const hasClosableTabOnLeft = computed(() => routeTabs.tabs.some((tab, index) => index < activeIndex.value && !tab.pinned));
const hasClosableTabOnRight = computed(() => routeTabs.tabs.some((tab, index) => index > activeIndex.value && !tab.pinned));

const translateRouteLabel = (label: unknown): string | undefined => {
    if (typeof label !== 'string' || label.length === 0) {
        return undefined;
    }

    return te(label) ? t(label) : label;
};
const resolveRouteTitle = (currentRoute: RouteLocationNormalizedLoaded): string => {
    const navigation = currentRoute.meta.$current as RouteNavigationMeta | undefined;
    const module = currentRoute.meta.$module as RouteModuleMeta | undefined;
    const baseTitle =
        translateRouteLabel(navigation?.label) ??
        translateRouteLabel(module?.title) ??
        String(currentRoute.name ?? currentRoute.path);
    const routeName = String(currentRoute.name ?? '');

    if (currentRoute.meta.parentPath && routeName.includes('create')) {
        return `${baseTitle} - ${t('global.default.add')}`;
    }
    if (currentRoute.meta.parentPath && (routeName.includes('detail') || routeName.includes('edit'))) {
        return `${baseTitle} - ${t('global.default.edit')}`;
    }

    return baseTitle;
};
const ensureDashboardTab = (): void => {
    if (!router.hasRoute(DASHBOARD_ROUTE_NAME)) {
        return;
    }

    const dashboardRoute = router.resolve({ name: DASHBOARD_ROUTE_NAME });
    routeTabs.addTab({
        key: dashboardRoute.fullPath,
        routeName: DASHBOARD_ROUTE_NAME,
        title: t('global.ct-admin-menu.navigation.mainMenuItemHome'),
        pinned: true,
    });
};
const addCurrentRoute = (): void => {
    ensureDashboardTab();

    if (!route.name || route.meta.noTabs === true) {
        return;
    }

    routeTabs.addTab({
        key: route.fullPath,
        routeName: String(route.name),
        title: resolveRouteTitle(route),
        pinned: route.name === DASHBOARD_ROUTE_NAME,
    });
};
const navigateTo = (key: string | undefined): void => {
    if (key && key !== route.fullPath) {
        void router.push(key);
    }
};
const onTabChange = (key: string | number): void => {
    navigateTo(String(key));
};
const onTabEdit = (targetKey: string | MouseEvent, action: 'add' | 'remove'): void => {
    if (action !== 'remove' || typeof targetKey !== 'string') {
        return;
    }

    const wasActive = targetKey === activeKey.value;
    const nextKey = routeTabs.closeTab(targetKey);
    if (wasActive) {
        navigateTo(nextKey);
    }
};
const onMenuClick = (key: string | number): void => {
    if (key === 'refresh') {
        routeTabs.refreshTab(activeKey.value);
        return;
    }

    const nextKey = routeTabs.closeTabs(String(key) as RouteTabCloseMode, activeKey.value);
    navigateTo(nextKey);
};

watch(
    [
        () => route.fullPath,
        locale,
    ],
    addCurrentRoute,
    { immediate: true },
);

swDefinePublic({
    routeTabs,
    activeKey,
    activeIndex,
    activeTab,
    isActiveTabPinned,
    hasClosableTabs,
    hasClosableTabOnLeft,
    hasClosableTabOnRight,
    translateRouteLabel,
    resolveRouteTitle,
    ensureDashboardTab,
    addCurrentRoute,
    navigateTo,
    onTabChange,
    onTabEdit,
    onMenuClick,
});

defineExpose({
    routeTabs,
    activeKey,
    activeIndex,
    activeTab,
    onTabChange,
    onTabEdit,
    onMenuClick,
});
</script>

<style lang="scss">
.ct-route-tabs {
    min-width: 0;
    height: calc(var(--ct-control-height) + var(--ct-spacing-sm));
    padding-inline: var(--ct-spacing-sm);
    background: var(--ct-color-bg-container);
    border-bottom: 1px solid var(--ct-color-border-secondary);

    .ant-tabs {
        height: calc(var(--ct-control-height) + var(--ct-spacing-sm));
    }

    .ant-tabs-nav {
        height: calc(var(--ct-control-height) + var(--ct-spacing-sm));
        margin: 0;
    }

    .ant-tabs-tab {
        max-width: 200px;
        padding-block: 7px;
        background: transparent;
        border-top: 0;
        border-radius: 0;
    }

    .ant-tabs-tab-active {
        background: var(--ct-color-bg-layout);
    }

    .ant-tabs-content-holder {
        display: none;
    }

    &__label {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    &__actions {
        width: var(--ct-control-height);
        height: var(--ct-control-height);
    }
}
</style>

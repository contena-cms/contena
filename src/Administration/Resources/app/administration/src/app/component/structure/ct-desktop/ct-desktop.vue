<template>
    <ct-block name="sw_desktop">
        <div class="ct-desktop" :class="desktopClasses">
            <div v-if="isStaging" class="ct-staging-bar">
                {{ $t('global.ct-desktop.stagingBarText') }}
            </div>

            <ct-block name="sw_desktop_sidebar">
                <ct-admin-menu v-if="!noNavigation" />
            </ct-block>

            <ct-block name="sw_desktop_content">
                <div class="ct-desktop__content">
                    <ct-route-tabs v-if="!noNavigation" />
                    <ct-block name="sw_desktop_content_view">
                        <ct-error-boundary>
                            <router-view v-slot="{ Component, route: viewRoute }">
                                <keep-alive>
                                    <component
                                        :is="Component"
                                        v-if="viewRoute.meta.keepAlive === true"
                                        :key="`${viewRoute.fullPath}:${currentRouteRefreshKey}`"
                                    />
                                </keep-alive>
                                <component
                                    :is="Component"
                                    v-if="viewRoute.meta.keepAlive !== true"
                                    :key="`${viewRoute.fullPath}:${currentRouteRefreshKey}`"
                                />
                            </router-view>
                        </ct-error-boundary>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
const { hasOwnProperty } = Contena.Utils.object;
import 'src/app/store/route-tabs.store';

defineProps({});

import { ref, computed, inject, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import CtRouteTabs from '../ct-route-tabs/ct-route-tabs.vue';

const $route = useRoute();
// vue-i18n exposes methods bound to its composer; the template and computed state use them as callbacks.
// eslint-disable-next-line @typescript-eslint/unbound-method
const { t, te } = useI18n();

const userActivityApiService = inject('userActivityApiService');

const noNavigation = ref(false);
const routeTabs = Contena.Store.get('routeTabs');
const currentRouteRefreshKey = computed(() => routeTabs.tabs.find((tab) => tab.key === $route.fullPath)?.refreshKey ?? 0);

const desktopClasses = computed(() => {
    return {
        'ct-desktop--no-nav': noNavigation.value,
        'ct-desktop--staging': isStaging.value,
    };
});
const currentUser = computed(() => {
    return Contena.Store.get('session').currentUser;
});
const isStaging = computed(() => {
    return Contena.Store.get('context').app.config.settings?.enableStagingMode === true;
});

const createdComponent = () => {
    checkRouteSettings();
};
function checkRouteSettings() {
    if ($route.meta && hasOwnProperty($route.meta, 'noNav')) {
        noNavigation.value = $route.meta.noNav;
    } else {
        noNavigation.value = false;
    }
}
const onUpdateSearchFrequently = () => {
    const metadata = getModuleMetadata();

    if (!metadata || !metadata?.route?.name) {
        return false;
    }

    const data = {
        key: `${metadata.name}@${metadata.route.name}`,
        cluster: currentUser.value.id,
    };

    return userActivityApiService.increment(data);
};
function getModuleMetadata() {
    const { $module } = $route.meta;
    const routeName = $route?.name;
    if (!$module) {
        return false;
    }
    const { name, icon, color, entity, routes, title } = $module;
    if (!te(title) || !routes?.index) {
        return false;
    }

    // special cases with searchMatcher function at the current module
    const searchMatcher = getModuleMetadataWithSearchMatcher($module, routeName);
    if (searchMatcher) {
        const { ...route } = searchMatcher.route;
        return {
            ...searchMatcher,
            route,
        };
    }
    if (routes?.index?.name === routeName || routes.index?.children?.some((child) => child.name === routeName)) {
        const { meta, ...route } = routes.index;
        return {
            name,
            icon,
            color,
            title,
            entity,
            privilege: meta?.privilege,
            route,
        };
    }
    if (routes?.create?.name === routeName || routes.create?.children?.some((child) => child.name === routeName)) {
        const { meta, ...route } = routes.create;
        return {
            name,
            icon,
            color,
            entity,
            privilege: meta?.privilege,
            route,
            action: true,
        };
    }
    return false;
}
function getModuleMetadataWithSearchMatcher(module, routeName) {
    if (typeof module.searchMatcher !== 'function') {
        return false;
    }
    const { title } = module;

    // get metadata in searchMatcher
    const metadata = module.searchMatcher(new RegExp(`^${t(title).toLowerCase()}(.*)`), t(title, 2), module);
    return metadata.find(
        (item) => item.route.name === routeName || item.route?.children?.some((child) => child.name === routeName),
    );
}

watch(
    () => ({ ...$route, params: { ...$route.params }, query: { ...$route.query } }),
    () => {
        checkRouteSettings();
    },
);
watch(
    () => $route.name,
    (to, from) => {
        if (from === undefined || to === from) {
            return;
        }

        onUpdateSearchFrequently();
    },
    { immediate: true },
);

createdComponent();

swDefinePublic({
    userActivityApiService,
    noNavigation,
    routeTabs,
    currentRouteRefreshKey,
    desktopClasses,
    currentUser,
    isStaging,
    createdComponent,
    checkRouteSettings,
    onUpdateSearchFrequently,
    getModuleMetadata,
    getModuleMetadataWithSearchMatcher,
});

defineExpose({
    userActivityApiService,
    noNavigation,
    currentRouteRefreshKey,
    desktopClasses,
    currentUser,
    isStaging,
    createdComponent,
    checkRouteSettings,
    onUpdateSearchFrequently,
    getModuleMetadata,
    getModuleMetadataWithSearchMatcher,
});
</script>

<style lang="scss">
.ct-desktop {
    display: grid;
    grid-template-columns: auto 1fr auto;
    position: relative;
    height: 100%;
    width: 100%;
    overflow: hidden;
    background: var(--ct-color-bg-layout);

    &.ct-desktop--no-nav {
        grid-template-columns: 1fr;
    }

    &.ct-desktop--staging {
        grid-template-rows: 50px auto;
    }

    .ct-desktop__content {
        display: flex;
        flex-direction: column;
        min-width: 0;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: var(--ct-color-bg-layout);
        overflow: hidden;
    }

    [data-block-name='sw_desktop_content_view'] {
        display: flex;
        min-height: 0;
        flex: 1;
        flex-direction: column;
    }

    @media screen and (max-width: 1280px) {
        grid-template-columns: 1fr auto;

        .ct-desktop__content {
            height: 100%;
            margin: 0;
            border: 0;
            border-radius: 0;
        }
    }

    @media screen and (max-width: 500px) {
        display: block;

        .ct-desktop__content {
            height: 100%;
            margin: 0;
            border: 0;
            border-radius: 0;
        }
    }

    .ct-staging-bar {
        grid-column: 1 / -1;
        display: flex;
        justify-content: center;
        background: rgb(61, 68, 77);
        color: white;
        font-weight: bold;
        padding: 1rem;
        position: sticky;
    }
}
</style>

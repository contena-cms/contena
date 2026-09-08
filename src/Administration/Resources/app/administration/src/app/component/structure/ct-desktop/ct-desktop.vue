<template>
    <ct-block name="ct_desktop">
        <div class="ct-desktop" :class="desktopClasses">
            <div v-if="isStaging" class="ct-staging-bar">
                {{ $t('global.ct-desktop.stagingBarText') }}
            </div>

            <ct-block name="ct_desktop_sidebar">
                <ct-admin-menu v-if="!noNavigation" />
            </ct-block>

            <ct-block name="ct_desktop_content">
                <div class="ct-desktop__content">
                    <ct-block name="ct_desktop_content_view">
                        <ct-error-boundary>
                            <router-view />
                        </ct-error-boundary>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-desktop.scss';
const { hasOwnProperty } = Contena.Utils.object;

defineProps({});

import { ref, computed, inject, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import useShortcut from 'src/app/composables/use-shortcut';
import useTheme from 'src/app/composables/use-theme';
import { useNotification } from 'src/app/composables/use-notification';

const THEME_CYCLE = [
    'system',
    'light',
    'dark',
];
const THEME_LABELS = {
    system: 'global.ct-desktop.theme.names.system',
    light: 'global.ct-desktop.theme.names.light',
    dark: 'global.ct-desktop.theme.names.dark',
};

const $route = useRoute();
// vue-i18n exposes methods bound to its composer; the template and computed state use them as callbacks.
// eslint-disable-next-line @typescript-eslint/unbound-method
const { t, te } = useI18n();
const { createNotificationError, createNotificationSuccess } = useNotification();

const userActivityApiService = inject('userActivityApiService');

const noNavigation = ref(false);

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
const onCycleTheme = async () => {
    const theme = useTheme();
    const currentTheme = theme.theme.value;
    const nextTheme = THEME_CYCLE[(THEME_CYCLE.indexOf(currentTheme) + 1) % THEME_CYCLE.length];

    try {
        await theme.saveUserTheme(nextTheme);
    } catch {
        theme.setTheme(currentTheme);
        createNotificationError({ message: t('global.ct-desktop.theme.saveError') });

        return;
    }

    createNotificationSuccess({
        message: t('global.ct-desktop.theme.changed', { theme: t(THEME_LABELS[nextTheme]) }),
    });
};
useShortcut('CT', () => void onCycleTheme());
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

ctDefinePublic({
    userActivityApiService,
    noNavigation,
    desktopClasses,
    currentUser,
    isStaging,
    createdComponent,
    onCycleTheme,
    checkRouteSettings,
    onUpdateSearchFrequently,
    getModuleMetadata,
    getModuleMetadataWithSearchMatcher,
});
</script>

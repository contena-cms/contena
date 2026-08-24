<template>
    <ct-block name="sw_admin_menu">
        <ct-block name="sw_admin_menu_off_canvas_backdrop">
            <transition name="ct-admin-menu__backdrop">
                <div
                    v-if="isMobileViewport && isOffCanvasShown"
                    class="ct-admin-menu__backdrop"
                    @click="closeOffCanvas"
                ></div>
            </transition>
        </ct-block>

        <aside
            class="ct-admin-menu"
            :class="adminMenuClasses"
            :aria-expanded="isExpanded ? 'true' : 'false'"
            :inert="isMobileViewport && !isOffCanvasShown"
        >
            <ct-block name="sw_admin_menu_header">
                <div class="ct-admin-menu__header">
                    <ct-block name="sw_admin_menu_header_logo">
                        <button
                            class="ct-admin-menu__header-logo-wrapper"
                            type="button"
                            :aria-label="isExpanded ? undefined : t('global.ct-admin-menu.linkExpandMenu')"
                            :tabindex="isExpanded ? -1 : 0"
                            @click="expandSidebar"
                        >
                            <img
                                class="ct-admin-menu__header-logo"
                                :src="assetFilter('/administration/administration/static/img/contena-logo-v4.svg')"
                                alt="Contena"
                            />
                            <ct-icon class="ct-admin-menu__header-logo-expand" name="MenuUnfoldOutlined" :size="20" />
                        </button>
                    </ct-block>

                    <ct-block name="sw_admin_menu_header_identity">
                        <div class="collapsible-text hide-on-collapse ct-admin-menu__version">
                            <div class="ct-admin-menu__shop-name" :title="shopName">{{ shopName }}</div>
                            <div class="ct-admin-menu__title">
                                {{ t('global.ct-admin-menu.textProjectName') }}
                            </div>
                        </div>
                    </ct-block>

                    <ct-block name="sw_admin_menu_header_toggle_sidebar">
                        <a-button
                            v-if="isMobileViewport"
                            class="ct-admin-menu__off-canvas-close"
                            type="text"
                            shape="circle"
                            :aria-label="t('global.ct-admin-menu.linkCloseMenu')"
                            @click.stop="closeOffCanvas"
                        >
                            <ct-icon name="CloseOutlined" />
                        </a-button>
                        <a-button
                            v-else
                            class="ct-admin-menu__collapse-button"
                            type="text"
                            shape="circle"
                            :aria-label="t('global.ct-admin-menu.linkMinimizeMenu')"
                            @click.stop="toggleSidebar"
                        >
                            <ct-icon name="MenuFoldOutlined" />
                        </a-button>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_admin_menu_body_container">
                <div class="ct-admin-menu__body-container">
                    <ct-block name="sw_admin_menu_body">
                        <div class="ct-admin-menu__body">
                            <nav class="ct-admin-menu__navigation" :aria-label="t('global.ct-admin-menu.navigation.label')">
                                <ct-block name="sw_admin_menu_navigation_main_list">
                                    <a-menu
                                        class="ct-admin-menu__navigation-list"
                                        mode="inline"
                                        :inline-collapsed="!isExpanded"
                                        :items="menuItems"
                                        :open-keys="openMenuKeys"
                                        :selected-keys="selectedMenuKeys"
                                        @click="onMenuClick"
                                        @open-change="onOpenChange"
                                    />
                                </ct-block>
                            </nav>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_admin_menu_footer">
                <div class="ct-admin-menu__footer">
                    <ct-block name="sw_admin_menu_user_actions_toggle">
                        <a-dropdown
                            :open="isUserActionsActive"
                            placement="topRight"
                            :trigger="['click']"
                            @update:open="onUserActionsOpenChange"
                        >
                            <button
                                class="ct-admin-menu__user-actions-toggle"
                                :class="{ 'is--active': isUserActionsActive }"
                                type="button"
                                :aria-label="userActionsAriaLabel"
                            >
                                <a-avatar class="ct-admin-menu__avatar" :src="avatarUrl">
                                    {{ userAvatarText }}
                                </a-avatar>

                                <div class="ct-admin-menu__user-custom-fields collapsible-text hide-on-collapse">
                                    <div class="ct-admin-menu__user-name">{{ userName }}</div>
                                    <div class="ct-admin-menu__user-type">{{ userTitle }}</div>
                                </div>

                                <ct-icon
                                    class="ct-admin-menu__user-actions-toggle-icon hide-on-collapse"
                                    name="DownOutlined"
                                />
                            </button>

                            <template #overlay>
                                <a-menu :items="userMenuItems" @click="onUserMenuClick" />
                            </template>
                        </a-dropdown>
                    </ct-block>
                </div>
            </ct-block>
        </aside>
    </ct-block>
</template>

<script setup lang="ts">

defineProps({});

import { computed, getCurrentInstance, h, inject, nextTick, onBeforeUnmount, onMounted, ref, resolveComponent, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type AclService from 'src/app/service/acl.service';
import type { LoginService } from 'src/core/service/login.service';
import useTheme from 'src/app/composables/use-theme';

interface NavigationEntry {
    id?: string;
    label?: string | { label: string; translated?: boolean };
    path?: string;
    icon?: string;
    parent?: string;
    position?: number;
    privilege?: string;
    children?: NavigationEntry[];
}

interface MenuService {
    getNavigationFromAdminModules(): NavigationEntry[];
}

interface UserService {
    getUser(): Promise<{ data: Record<string, any> }>;
}

interface SystemConfigApiService {
    getValues(domain: string): Promise<Record<string, string>>;
}

const MOBILE_BREAKPOINT = 1280;

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const instance = getCurrentInstance();
const device = instance?.proxy?.$device;
const menuService = inject<MenuService>('menuService');
const loginService = inject<LoginService>('loginService');
const userService = inject<UserService>('userService');
const systemConfigApiService = inject<SystemConfigApiService>('systemConfigApiService');
const acl = inject<AclService>('acl');
const iconComponent = resolveComponent('ct-icon');
const { resolvedTheme, saveUserTheme } = useTheme();

if (!menuService || !loginService || !userService || !systemConfigApiService || !acl) {
    throw new Error('The Administration menu services are unavailable.');
}

const viewportWidth = ref(device?.getViewportWidth() ?? window.innerWidth);
const isOffCanvasShown = ref(false);
const isUserActionsActive = ref(false);
const shopName = ref('Contena');
const isUserLoading = ref(true);
const openMenuKeys = ref<string[]>([]);

const adminMenuStore = computed(() => Contena.Store.get('adminMenu'));
const currentUser = computed<Record<string, any> | null>(() => Contena.Store.get('session').currentUser);
const assetFilter = computed(() => Contena.Filter.getByName('asset'));
const isMobileViewport = computed(() => viewportWidth.value <= MOBILE_BREAKPOINT);
const isExpanded = computed(() => adminMenuStore.value.isExpanded || isMobileViewport.value);
const adminMenuClasses = computed(() => ({
    'is--expanded': isExpanded.value,
    'is--collapsed': !isExpanded.value,
    'is--off-canvas-shown': isOffCanvasShown.value,
}));
const navigationEntries = computed<NavigationEntry[]>(() => adminMenuStore.value.adminModuleNavigation);
const mainMenuEntries = computed<NavigationEntry[]>(() => {
    const tree = new Contena.Helper.FlatTreeHelper(
        (first: NavigationEntry, second: NavigationEntry) => (first.position ?? 0) - (second.position ?? 0),
    );

    navigationEntries.value.forEach((entry) => tree.add(entry));

    return tree.convertToTree();
});
const availableMenuEntries = computed(() => filterNavigationEntries(mainMenuEntries.value));
const menuItems = computed(() => availableMenuEntries.value.map((entry) => createMenuItem(entry, 1)));
const activeRouteNames = computed(() => new Set([route.name, ...route.matched.map((record) => record.name)].filter(Boolean)));
const selectedMenuKeys = computed(() => {
    const activeEntry = navigationEntries.value.find((entry) => entry.path && activeRouteNames.value.has(entry.path));

    return activeEntry ? [getEntryKey(activeEntry)] : [];
});
const userName = computed(() => {
    if (!currentUser.value) {
        return '';
    }

    const fullName = [currentUser.value.firstName, currentUser.value.lastName].filter(Boolean).join(' ');

    return fullName || currentUser.value.name || currentUser.value.username || '';
});
const userTitle = computed(() => {
    if (currentUser.value?.admin) {
        return t('global.ct-admin-menu.administrator');
    }

    return currentUser.value?.title || currentUser.value?.aclRoles?.[0]?.name || '';
});
const userActionsAriaLabel = computed(() => [userName.value, userTitle.value].filter(Boolean).join(', '));
const avatarUrl = computed(() => currentUser.value?.avatarMedia?.url ?? null);
const userAvatarText = computed(() => userName.value.trim().charAt(0).toUpperCase());
const userMenuItems = computed(() => [
    {
        key: 'theme',
        label:
            resolvedTheme.value === 'dark'
                ? t('global.ct-admin-menu.themeToggle.switchToLight')
                : t('global.ct-admin-menu.themeToggle.switchToDark'),
        icon: h(iconComponent, { name: resolvedTheme.value === 'dark' ? 'SunOutlined' : 'MoonOutlined' }),
    },
    { type: 'divider' },
    {
        key: 'logout',
        label: t('global.ct-admin-menu.linkLogout'),
        icon: h(iconComponent, { name: 'LogoutOutlined' }),
        danger: true,
    },
]);

function getEntryKey(entry: NavigationEntry): string {
    return entry.id ?? entry.path ?? '';
}
function getEntryLabel(entry: NavigationEntry): string {
    if (!entry.label) {
        return '';
    }

    if (typeof entry.label === 'object') {
        return entry.label.translated ? entry.label.label : t(entry.label.label);
    }

    return t(entry.label);
}
function filterNavigationEntries(entries: NavigationEntry[]): NavigationEntry[] {
    return entries.flatMap((entry) => {
        if (entry.privilege && !acl.can(entry.privilege)) {
            return [];
        }

        const children = filterNavigationEntries(entry.children ?? []);

        if (!entry.path && children.length === 0) {
            return [];
        }

        return [{ ...entry, children }];
    });
}
function createMenuItem(entry: NavigationEntry, level: number) {
    const children = entry.children?.map((child) => createMenuItem(child, level + 1));

    return {
        key: getEntryKey(entry),
        label: getEntryLabel(entry),
        icon: level === 1 && entry.icon ? h(iconComponent, { name: entry.icon }) : undefined,
        children: children?.length ? children : undefined,
    };
}
function findEntryByKey(entries: NavigationEntry[], key: string): NavigationEntry | undefined {
    for (const entry of entries) {
        if (getEntryKey(entry) === key) {
            return entry;
        }

        const child = findEntryByKey(entry.children ?? [], key);
        if (child) {
            return child;
        }
    }

    return undefined;
}
function findAncestorKeys(entries: NavigationEntry[], targetKey: string, ancestors: string[] = []): string[] {
    for (const entry of entries) {
        const entryKey = getEntryKey(entry);
        if (entryKey === targetKey) {
            return ancestors;
        }

        const result = findAncestorKeys(entry.children ?? [], targetKey, [...ancestors, entryKey]);
        if (result.length > 0) {
            return result;
        }
    }

    return [];
}
const loadShopName = async (): Promise<void> => {
    try {
        const values = await systemConfigApiService.getValues('core.basicInformation');
        shopName.value = values['core.basicInformation.siteName'] || 'Contena';
    } catch {
        shopName.value = 'Contena';
    }
};
const getUser = async (): Promise<void> => {
    isUserLoading.value = true;
    try {
        const response = await userService.getUser();
        delete response.data.password;
        Contena.Store.get('session').setCurrentUser(response.data);
    } finally {
        isUserLoading.value = false;
    }
};
const initNavigation = (): void => {
    adminMenuStore.value.adminModuleNavigation = menuService.getNavigationFromAdminModules();
};
const syncOpenMenuKeys = (): void => {
    const selectedKey = selectedMenuKeys.value[0];
    if (!selectedKey || !isExpanded.value) {
        return;
    }

    openMenuKeys.value = findAncestorKeys(availableMenuEntries.value, selectedKey);
};
const onViewportResize = (): void => {
    viewportWidth.value = device?.getViewportWidth() ?? window.innerWidth;
};
const toggleSidebar = (): void => {
    if (isExpanded.value) {
        adminMenuStore.value.collapseSidebar();
        openMenuKeys.value = [];
    } else {
        adminMenuStore.value.expandSidebar();
        syncOpenMenuKeys();
    }
    isUserActionsActive.value = false;
};
const expandSidebar = (): void => {
    if (!isExpanded.value) {
        adminMenuStore.value.expandSidebar();
        void nextTick(syncOpenMenuKeys);
    }
};
const closeOffCanvas = (): void => {
    isOffCanvasShown.value = false;
    Contena.Utils.EventBus.emit('ct-admin-menu/toggle-offcanvas', false);
};
const onToggleCanvas = (state: boolean): void => {
    isOffCanvasShown.value = state;
};
const onOpenChange = (keys: string[]): void => {
    openMenuKeys.value = keys;
};
const onUserActionsOpenChange = (open: boolean): void => {
    isUserActionsActive.value = open;
};
const onMenuClick = ({ key }: { key: string }): void => {
    const entry = findEntryByKey(availableMenuEntries.value, key);
    if (entry?.path) {
        void router.push({ name: entry.path });
    }
    closeOffCanvas();
};
const onLogoutUser = async (): Promise<void> => {
    try {
        await fetch(`${Contena.Context.api.apiPath}/_action/user/logout`, {
            method: 'POST',
            headers: { Authorization: `Bearer ${loginService.getToken()}` },
        });
    } catch {
        // Token revocation is best-effort; local logout must always continue.
    }

    loginService.logout();
    adminMenuStore.value.clearExpandedMenuEntries();
    Contena.Store.get('session').removeCurrentUser();
    Contena.Store.get('notification').clearGrowlNotificationsForCurrentUser();
    Contena.Store.get('notification').clearNotificationsForCurrentUser();
};
const onUserMenuClick = async ({ key }: { key: string }): Promise<void> => {
    isUserActionsActive.value = false;

    if (key === 'theme') {
        await saveUserTheme(resolvedTheme.value === 'dark' ? 'light' : 'dark');
    } else if (key === 'logout') {
        await onLogoutUser();
    }
};

loginService.notifyOnLoginListener();
initNavigation();
void getUser();
void loadShopName();

watch(() => route.name, () => void nextTick(syncOpenMenuKeys), { immediate: true });
watch(isMobileViewport, (isMobile) => {
    if (!isMobile && isOffCanvasShown.value) {
        closeOffCanvas();
    }
});

onMounted(() => {
    window.addEventListener('resize', onViewportResize);
    Contena.Utils.EventBus.on('ct-admin-menu/toggle-offcanvas', onToggleCanvas);
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', onViewportResize);
    Contena.Utils.EventBus.off('ct-admin-menu/toggle-offcanvas', onToggleCanvas);
});

swDefinePublic({
    viewportWidth,
    isOffCanvasShown,
    isUserActionsActive,
    shopName,
    isUserLoading,
    openMenuKeys,
    adminMenuStore,
    currentUser,
    isMobileViewport,
    isExpanded,
    adminMenuClasses,
    navigationEntries,
    mainMenuEntries,
    availableMenuEntries,
    menuItems,
    selectedMenuKeys,
    userName,
    userTitle,
    userActionsAriaLabel,
    avatarUrl,
    userAvatarText,
    userMenuItems,
    getEntryLabel,
    loadShopName,
    getUser,
    initNavigation,
    syncOpenMenuKeys,
    onViewportResize,
    toggleSidebar,
    expandSidebar,
    closeOffCanvas,
    onToggleCanvas,
    onOpenChange,
    onUserActionsOpenChange,
    onMenuClick,
    onLogoutUser,
    onUserMenuClick,
});
</script>

<style lang="scss">
@import '~scss/variables';
@import '~scss/mixins';

.ct-admin-menu__backdrop {
    position: fixed;
    inset: 0;
    z-index: $z-index-off-canvas - 1;
    background: rgba(15, 23, 42, 45%);
    cursor: pointer;
}

.ct-admin-menu__backdrop-enter-active,
.ct-admin-menu__backdrop-leave-active {
    transition: opacity 0.2s ease;
}

.ct-admin-menu__backdrop-enter-from,
.ct-admin-menu__backdrop-leave-to {
    opacity: 0;
}

.ct-admin-menu {
    display: flex;
    flex-direction: column;
    width: var(--ct-layout-sidebar-width);
    height: 100%;
    overflow: hidden;
    background: var(--ct-color-bg-container);
    border-right: 1px solid var(--ct-color-border-secondary);
    transition: width 0.2s ease;

    &.is--collapsed {
        width: var(--ct-layout-sidebar-collapsed-width);

        .ct-admin-menu__header {
            justify-content: center;
            padding-inline: 12px;
        }

        .ct-admin-menu__header-logo-wrapper {
            width: 36px;
        }

        .ct-admin-menu__collapse-button {
            display: none;
        }

        .ct-admin-menu__footer {
            padding-inline: 12px;
        }

        .ct-admin-menu__user-actions-toggle {
            justify-content: center;
            padding-inline: 0;
        }
    }

    .hide-on-collapse {
        opacity: 1;
        transition: opacity 0.15s ease;
    }

    &.is--collapsed .hide-on-collapse {
        display: none;
        opacity: 0;
    }

    .ct-admin-menu__header {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        height: var(--ct-layout-topbar-height);
        min-height: var(--ct-layout-topbar-height);
        padding: 10px 12px 10px 16px;
        border-bottom: 1px solid var(--ct-color-border-secondary);
    }

    .ct-admin-menu__header-logo-wrapper,
    .ct-admin-menu__header-logo-box,
    .ct-admin-menu__header-logo {
        display: block;
        flex: 0 0 auto;
        width: 36px;
        height: 36px;
        border-radius: var(--ct-border-radius);
    }

    .ct-admin-menu__header-logo-wrapper {
        padding: 0;
        background: transparent;
        border: 0;
        cursor: default;
    }

    .ct-admin-menu__header-logo-expand {
        display: none;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        color: var(--ct-color-text-secondary);
        background: var(--ct-color-fill-tertiary);
        border-radius: var(--ct-border-radius);
    }

    &.is--collapsed .ct-admin-menu__header-logo-wrapper {
        cursor: pointer;
    }

    &.is--collapsed:hover,
    &.is--collapsed:focus-within {
        .ct-admin-menu__header-logo {
            display: none;
        }

        .ct-admin-menu__header-logo-expand {
            display: inline-flex;
        }
    }

    .ct-admin-menu__version {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-width: 0;
    }

    .ct-admin-menu__shop-name,
    .ct-admin-menu__title,
    .ct-admin-menu__user-name,
    .ct-admin-menu__user-type {
        @include truncate;
    }

    .ct-admin-menu__shop-name {
        color: var(--ct-color-text);
        font-size: var(--ct-font-size);
        font-weight: 600;
        line-height: 20px;
    }

    .ct-admin-menu__title {
        color: var(--ct-color-text-tertiary);
        font-size: var(--ct-font-size-sm);
        line-height: 18px;
    }

    .ct-admin-menu__collapse-button,
    .ct-admin-menu__off-canvas-close {
        flex: 0 0 auto;
        color: var(--ct-color-text-secondary);
    }

    .ct-admin-menu__body-container,
    .ct-admin-menu__body,
    .ct-admin-menu__navigation {
        min-height: 0;
    }

    .ct-admin-menu__body-container {
        flex: 1 1 auto;
        overflow: hidden;
    }

    .ct-admin-menu__body {
        height: 100%;
        padding: 12px 8px;
        overflow-x: hidden;
        overflow-y: auto;
    }

    .ct-admin-menu__navigation-list.ant-menu {
        width: 100%;
        color: var(--ct-color-text-secondary);
        background: transparent;
        border-inline-end: 0;
        font-size: var(--ct-font-size);

        .ant-menu-item,
        .ant-menu-submenu-title {
            width: 100%;
            margin: 2px 0;
        }

        .ant-menu-item-selected {
            color: var(--ct-color-primary);
            font-weight: 500;
            background: var(--ct-color-primary-bg);
        }

        .ant-menu-item-icon,
        .ant-menu-submenu-title .anticon {
            color: var(--ct-color-text-secondary);
            font-size: var(--ct-font-size-lg);
        }

        .ant-menu-item-selected .ant-menu-item-icon {
            color: var(--ct-color-primary);
        }
    }

    .ct-admin-menu__footer {
        flex: 0 0 auto;
        padding: 10px 12px 12px;
        border-top: 1px solid var(--ct-color-border-secondary);
    }

    .ct-admin-menu__user-actions-toggle {
        display: flex;
        align-items: center;
        width: 100%;
        min-width: 0;
        height: 44px;
        gap: 10px;
        padding: 4px 8px;
        color: var(--ct-color-text);
        background: transparent;
        border: 0;
        border-radius: var(--ct-border-radius);
        cursor: pointer;

        &:hover,
        &.is--active {
            background: var(--ct-color-fill-tertiary);
        }
    }

    .ct-admin-menu__avatar {
        flex: 0 0 auto;
        background: var(--ct-color-primary);
    }

    .ct-admin-menu__user-custom-fields {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-width: 0;
        text-align: left;
    }

    .ct-admin-menu__user-name {
        font-size: 13px;
        font-weight: 500;
        line-height: 19px;
    }

    .ct-admin-menu__user-type {
        color: var(--ct-color-text-tertiary);
        font-size: var(--ct-font-size-sm);
        line-height: 17px;
    }

    .ct-admin-menu__user-actions-toggle-icon {
        flex: 0 0 auto;
        color: var(--ct-color-text-tertiary);
        font-size: 11px;
    }

    @media screen and (max-width: 1280px) {
        position: absolute;
        inset: 8px auto 8px 8px;
        z-index: $z-index-off-canvas;
        width: var(--ct-layout-sidebar-width);
        height: auto;
        border: 1px solid var(--ct-color-border);
        border-radius: var(--ct-border-radius-lg);
        box-shadow: 0 18px 48px rgba(15, 23, 42, 18%);
        transform: translateX(calc(-100% - 16px));
        transition: transform 0.2s ease;

        &.is--collapsed {
            width: var(--ct-layout-sidebar-width);
        }

        &.is--off-canvas-shown {
            transform: translateX(0);
        }
    }
}

.ct-admin-menu__user-actions-menu {
    min-width: 220px;
}

.ct-admin-menu__version-footer {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 8px;
    color: var(--ct-color-text-tertiary);
    white-space: nowrap;
}
</style>

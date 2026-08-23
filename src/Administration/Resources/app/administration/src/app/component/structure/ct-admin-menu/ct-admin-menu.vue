<template>
    <ct-block name="sw_admin_menu">
        <ct-block name="sw_admin_menu_off_canvas_backdrop">
            <transition name="ct-admin-menu__backdrop">
                <div
                    v-if="isMobileViewport && isOffCanvasShown"
                    class="ct-admin-menu__backdrop"
                    @click="dismissOffCanvas"
                ></div>
            </transition>
        </ct-block>

        <!-- eslint-disable-next-line vuejs-accessibility/mouse-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
        <aside
            ref="swAdminMenu"
            class="ct-admin-menu"
            :class="adminMenuClasses"
            :aria-expanded="isExpanded ? 'true' : 'false'"
            :inert="isMobileViewport && !isOffCanvasShown"
        >
            <ct-block name="sw_admin_menu_header">
                <div class="ct-admin-menu__header">
                    <ct-block name="sw_admin_menu_header_logo">
                        <div class="ct-admin-menu__header-logo-wrapper">
                            <div class="ct-admin-menu__header-logo-box">
                                <img
                                    class="ct-admin-menu__header-logo"
                                    :src="assetFilter('/administration/administration/static/img/contena-logo-v4.svg')"
                                    alt="Contena"
                                />
                            </div>

                            <button
                                v-if="!isExpanded"
                                type="button"
                                class="ct-admin-menu__header-logo-expand-button"
                                :aria-label="translate('global.ct-admin-menu.linkExpandMenu')"
                                @click.stop="onToggleSidebar"
                            >
                                <mt-icon :name="sidebarCollapseIcon" size="16px" />
                            </button>
                        </div>
                    </ct-block>

                    <ct-block name="sw_admin_menu_header_identity">
                        <div class="collapsible-text hide-on-collapse ct-admin-menu__version">
                            <ct-block name="sw_admin_menu_header_shop_name">
                                <mt-text
                                    as="div"
                                    class="ct-admin-menu__shop-name"
                                    size="s"
                                    weight="semibold"
                                    :title="shopName"
                                >
                                    {{ shopName }}
                                </mt-text>
                            </ct-block>
                            <ct-block name="sw_admin_menu_header_title">
                                <mt-text
                                    as="div"
                                    class="ct-admin-menu__title"
                                    size="2xs"
                                    color="color-text-secondary-default"
                                >
                                    {{ translate('global.ct-admin-menu.textProjectName') }}
                                    <ct-block name="sw_admin_menu_header_title_status"></ct-block>
                                </mt-text>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="sw_admin_menu_header_toggle_sidebar">
                        <mt-button
                            v-if="isMobileViewport"
                            class="ct-admin-menu__off-canvas-close"
                            variant="tertiary"
                            size="large"
                            square
                            :aria-label="translate('global.ct-admin-menu.linkCloseMenu')"
                            @click.stop="dismissOffCanvas"
                        >
                            <mt-icon name="solid-times" size="12px" />
                        </mt-button>
                        <mt-button
                            v-else
                            class="ct-admin-menu__collapse-button"
                            variant="tertiary"
                            size="large"
                            square
                            :aria-label="translate('global.ct-admin-menu.linkMinimizeMenu')"
                            @click.stop="onToggleSidebar"
                        >
                            <mt-icon name="regular-panel-left" size="16px" />
                        </mt-button>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_admin_menu_body_container">
                <div class="ct-admin-menu__body-container">
                    <ct-block name="sw_admin_menu_body">
                        <div
                            ref="swAdminMenuBody"
                            class="ct-admin-menu__body"
                            :style="scrollbarOffsetStyle"
                            @keydown="onNavigationKeydown"
                        >
                            <ct-block name="sw_admin_menu_navigation_main">
                                <nav class="ct-admin-menu__navigation" aria-labelledby="mainmenulabel">
                                    <h2 id="mainmenulabel" class="visually-hidden">
                                        {{ translate('global.ct-admin-menu.navigation.label') }}
                                    </h2>

                                    <ct-block name="sw_admin_menu_navigation_main_list">
                                        <!-- eslint-disable-next-line vuejs-accessibility/no-static-element-interactions -->
                                        <ul
                                            class="ct-admin-menu__navigation-list"
                                            @mouseenter="cancelFlyoutClose"
                                            @focusin="cancelFlyoutClose"
                                            @mouseleave="onNavigationListMouseLeave"
                                            @focusout="onNavigationListMouseLeave"
                                        >
                                            <!-- eslint-disable ct-deprecation-rules/no-twigjs-blocks -->
                                            <ct-block name="sw_admin_menu_navigation_main_items">
                                                <ct-admin-menu-item
                                                    v-for="entry in mainMenuEntries"
                                                    :key="entry.id || entry.path"
                                                    :sidebar-expanded="isExpanded"
                                                    :is-expanded="isNavigationEntryExpanded(entry)"
                                                    :flyout-active="isFlyoutEntryActive(entry)"
                                                    :entry="entry"
                                                    @menu-item-hover="onMenuItemHover"
                                                    @branch-toggle="onMenuBranchToggle"
                                                    @flyout-focus-request="onFlyoutFocusRequest"
                                                    @flyout-close-request="onFlyoutLeave"
                                                    @flyout-navigate="onFlyoutNavigate"
                                                    @navigation-link-click="onNavigationLinkClicked"
                                                />
                                            </ct-block>
                                        </ul>
                                    </ct-block>
                                </nav>
                            </ct-block>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_admin_menu_footer">
                <div class="ct-admin-menu__footer">
                    <ct-block name="sw_admin_menu_user_actions_toggle">
                        <mt-dropdown-menu-root
                            :open="isUserActionsActive"
                            class="ct-admin-menu__user-actions-menu"
                            @update:open="isUserActionsActive = $event"
                        >
                            <mt-dropdown-menu-trigger as-child>
                                <button
                                    class="ct-admin-menu__user-actions-toggle"
                                    :class="{ 'is--active': isUserActionsActive }"
                                    type="button"
                                    :aria-label="userActionsAriaLabel"
                                >
                                    <mt-loader v-if="isUserLoading" size="32px" />

                                    <ct-block name="sw_admin_menu_user_actions_avatar">
                                        <mt-avatar class="ct-admin-menu__avatar" :image-url="avatarUrl" :name="userName" />
                                    </ct-block>

                                    <ct-block name="sw_admin_menu_user_actions_custom_fields">
                                        <div class="ct-admin-menu__user-custom-fields collapsible-text hide-on-collapse">
                                            <mt-text as="div" class="ct-admin-menu__user-name" size="xs" weight="semibold">
                                                {{ userName }}
                                            </mt-text>
                                            <mt-text
                                                as="div"
                                                class="ct-admin-menu__user-type"
                                                size="2xs"
                                                color="color-text-secondary-default"
                                            >
                                                {{ userTitle }}
                                            </mt-text>
                                        </div>
                                    </ct-block>

                                    <ct-block name="sw_admin_menu_user_actions_toggle_icon">
                                        <div class="ct-admin-menu__user-actions-toggle-icon-wrapper">
                                            <mt-icon class="hide-on-collapse" name="regular-chevron-up-xs" size="8" />
                                            <mt-icon class="hide-on-collapse" name="regular-chevron-down-xs" size="8" />
                                        </div>
                                    </ct-block>
                                </button>
                            </mt-dropdown-menu-trigger>

                            <mt-dropdown-menu-portal>
                                <mt-action-menu
                                    class="ct-admin-menu__user-actions-menu"
                                    :match-trigger-width="true"
                                    :side-offset="4"
                                    side="top"
                                >
                                    <ct-block name="sw_admin_menu_user_actions_items">
                                        <mt-action-menu-group>
                                            <ct-block name="sw_admin_menu_user_actions_items_logout_user">
                                                <mt-action-menu-item
                                                    icon="regular-sign-out"
                                                    variant="critical"
                                                    @click="onLogoutUser"
                                                >
                                                    {{ translate('global.ct-admin-menu.linkLogout') }}
                                                </mt-action-menu-item>
                                            </ct-block>
                                        </mt-action-menu-group>
                                    </ct-block>
                                    <ct-block name="sw_admin_menu_user_actions_version">
                                        <mt-action-menu-group>
                                            <mt-text
                                                as="div"
                                                class="ct-admin-menu__version-footer"
                                                size="2xs"
                                                color="color-text-secondary-default"
                                            >
                                                {{ translate('global.ct-admin-menu.textVersion') }}
                                                <ct-version />
                                            </mt-text>
                                        </mt-action-menu-group>
                                    </ct-block>
                                </mt-action-menu>
                            </mt-dropdown-menu-portal>
                        </mt-dropdown-menu-root>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_admin_menu_flyout_transition">
                <mt-floating-ui
                    :is-opened="flyoutEnabled && flyoutEntries.length > 0"
                    :anchor-element="flyoutReferenceElement"
                    :floating-ui-options="{ placement: flyoutPlacement }"
                    :offset="flyoutOffset"
                    detached
                    @close="onFlyoutLeave"
                >
                    <!-- eslint-disable-next-line vuejs-accessibility/no-static-element-interactions -->
                    <div
                        id="ct-admin-menu-flyout"
                        ref="swAdminMenuFlyout"
                        class="ct-admin-menu__flyout-content"
                        :class="{ 'is--closing': isFlyoutClosing }"
                        tabindex="-1"
                        @mouseenter="cancelFlyoutClose"
                        @focusin="cancelFlyoutClose"
                        @mouseleave="onFlyoutMouseLeave"
                        @focusout="onFlyoutMouseLeave"
                        @keydown="onFlyoutKeydown"
                    >
                        <mt-text
                            v-if="flyoutTitle"
                            as="span"
                            class="ct-admin-menu__flyout-title"
                            size="xs"
                            color="color-text-secondary-default"
                        >
                            {{ flyoutTitle }}
                        </mt-text>

                        <ul class="ct-admin-menu__flyout-list">
                            <ct-admin-menu-item
                                v-for="entry in flyoutEntries"
                                :key="entry.id || entry.path"
                                :entry="entry"
                                :menu-depth="2"
                                :sidebar-expanded="isExpanded"
                                :display-icon="false"
                                :collapsible-text="false"
                                @flyout-navigate="onFlyoutNavigate"
                                @navigation-link-click="onNavigationLinkClicked"
                            />
                        </ul>
                    </div>
                </mt-floating-ui>
            </ct-block>
        </aside>
    </ct-block>
</template>

<script setup>
import { createFocusTrap } from 'focus-trap';
import './ct-admin-menu.scss';
import { getActiveRouteNames, isEntryOnActiveRoute } from '../ct-admin-menu-item/menu-item-active.helper';
const { dom } = Contena.Utils;

const SIDEBAR_TOGGLE_ANIMATION_DURATION = 500;
const VIEWPORT_RESIZE_SETTLE_DURATION = 200;

defineProps({});

import { ref, computed, inject, watch, nextTick, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const translate = t;
const swAdminMenu = ref(null);
const swAdminMenuBody = ref(null);
const swAdminMenuFlyout = ref(null);

const instance = getCurrentInstance();
const device = instance?.proxy?.$device;
const menuService = inject('menuService');
const loginService = inject('loginService');
const userService = inject('userService');
const systemConfigApiService = inject('systemConfigApiService');
const acl = inject('acl');
const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});

const activeEntry = ref(null);
const isOffCanvasShown = ref(false);
const viewportWidth = ref(null);
const isUserActionsActive = ref(false);
const flyoutEntries = ref([]);
const flyoutTitle = ref('');
const flyoutCloseTimeoutId = ref(null);
const isFlyoutClosing = ref(false);
const isFlyoutPinned = ref(false);
const scrollbarOffset = ref('');
const isUserLoading = ref(true);
const flyoutReferenceElement = ref(null);
const activeBranchKey = ref(null);
const shopName = ref('');
const isTogglingSidebar = ref(false);
const toggleSidebarTimeout = ref(null);
const isViewportResizing = ref(false);
const viewportResizeTimeout = ref(null);
const flyoutFocusTrap = ref(null);
const offCanvasFocusTrap = ref(null);
const menuDropdownObserver = ref(null);
const openMenuDropdownTrigger = ref(null);

const currentUser = computed(() => {
    return Contena.Store.get('session').currentUser;
});
const isMobileViewport = computed(() => {
    return viewportWidth.value !== null && viewportWidth.value <= 1280;
});
const isExpanded = computed(() => {
    return adminMenuStore.value.isExpanded || isMobileViewport.value;
});
const userTitle = computed(() => {
    if (currentUser.value && currentUser.value.admin) {
        return t('global.ct-admin-menu.administrator');
    }

    if (currentUser.value && currentUser.value.title && currentUser.value.title.length > 0) {
        return currentUser.value.title;
    }

    if (currentUser.value && currentUser.value.aclRoles && currentUser.value.aclRoles.length > 0) {
        return currentUser.value.aclRoles[0].name;
    }

    if (currentUser.value && currentUser.value.title) {
        return currentUser.value.title;
    }

    return '';
});
const currentLocale = computed(() => {
    return Contena.Store.get('session').currentLocale;
});
const currentExpandedMenuEntries = computed(() => {
    return adminMenuStore.value.expandedEntries;
});
const adminModuleNavigation = computed(() => {
    const adminModuleNavigationEntries = adminMenuStore.value.adminModuleNavigation;

    // Throw an console error if navigation entry is on level 4 or higher. Also remove the navigation entry from menu
    return adminModuleNavigationEntries.filter((entry) => {
        const levelOneParent = adminModuleNavigationEntries.find((e) => entry.parent && e.id === entry.parent);

        const levelTwoParent = adminModuleNavigationEntries.find(
            (e) => levelOneParent?.parent && e.id === levelOneParent?.parent,
        );

        const levelThreeParent = adminModuleNavigationEntries.find(
            (e) => levelTwoParent?.parent && e.id === levelTwoParent?.parent,
        );

        if (levelThreeParent) {
            Contena.Utils.debug.error(
                new Error(
                    `The navigation entry "${entry.id}" is nested on level 4 or higher.\
    The admin menu only supports up to three levels of nesting.`,
                ),
            );

            return false;
        }

        return true;
    });
});
const navigationEntries = computed(() => {
    return adminModuleNavigation.value;
});
const mainMenuEntries = computed(() => {
    const tree = new Contena.Helper.FlatTreeHelper((first, second) => first.position - second.position);

    navigationEntries.value.forEach((module) => tree.add(module));

    return tree.convertToTree();
});
const flyoutEnabled = computed(() => {
    return !isExpanded.value;
});
const flyoutPlacement = 'right-start';
const flyoutOffset = 12;
const sidebarCollapseIcon = computed(() => {
    return isExpanded.value ? 'regular-chevron-circle-left' : 'regular-chevron-circle-right';
});
const scrollbarOffsetStyle = computed(() => {
    return {
        right: scrollbarOffset.value,
        'margin-left': scrollbarOffset.value,
    };
});
const adminMenuClasses = computed(() => {
    return {
        'is--expanded': isExpanded.value,
        'is--collapsed': !isExpanded.value,
        'is--off-canvas-shown': isOffCanvasShown.value,
        'is--toggling': isTogglingSidebar.value,
        'is--viewport-resizing': isViewportResizing.value,
    };
});
const userName = computed(() => {
    if (!currentUser.value) {
        return '';
    }

    const fullName = [
        currentUser.value.firstName,
        currentUser.value.lastName,
    ]
        .filter(Boolean)
        .join(' ');

    return fullName || currentUser.value.name || currentUser.value.username || '';
});
const userActionsAriaLabel = computed(() => {
    return [
        userName.value,
        userTitle.value,
    ]
        .filter(Boolean)
        .join(', ');
});
const avatarUrl = computed(() => {
    if (currentUser.value && currentUser.value.avatarMedia) {
        return currentUser.value.avatarMedia.url;
    }

    return null;
});
const adminMenuStore = computed(() => {
    return Contena.Store.get('adminMenu');
});

const createdComponent = () => {
    loginService.notifyOnLoginListener();
    viewportWidth.value = device?.getViewportWidth() ?? window.innerWidth;
    getUser();
    loadShopName();
    Contena.Utils.EventBus.on('ct-admin-menu/toggle-offcanvas', onToggleCanvas);
    window.addEventListener('resize', onViewportResize);
    initNavigation();
};
const mountedComponent = () => {
    addScrollbarOffset();
};
function loadShopName() {
    systemConfigApiService
        .getValues('core.basicInformation')
        .then((values) => {
            shopName.value = values['core.basicInformation.siteName'] || 'Contena';
        })
        .catch(() => {
            shopName.value = 'Contena';
        });
}
const beforeUnmountedComponent = () => {
    deactivateOffCanvasFocusTrap();
    Contena.Utils.EventBus.off('ct-admin-menu/toggle-offcanvas', onToggleCanvas);
    window.removeEventListener('resize', onViewportResize);

    if (toggleSidebarTimeout.value) {
        clearTimeout(toggleSidebarTimeout.value);
    }

    if (viewportResizeTimeout.value) {
        clearTimeout(viewportResizeTimeout.value);
    }
};
function onViewportResize() {
    viewportWidth.value = device?.getViewportWidth() ?? window.innerWidth;
    isViewportResizing.value = true;
    if (viewportResizeTimeout.value) {
        clearTimeout(viewportResizeTimeout.value);
    }
    viewportResizeTimeout.value = window.setTimeout(() => {
        isViewportResizing.value = false;
    }, VIEWPORT_RESIZE_SETTLE_DURATION);
}
function onToggleCanvas(state) {
    isOffCanvasShown.value = state;
}
const closeOffCanvas = () => {
    isOffCanvasShown.value = false;
    Contena.Utils.EventBus.emit('ct-admin-menu/toggle-offcanvas', false);
};
const closeNavigationOverlays = () => {
    if (!isExpanded.value && flyoutEntries.value.length && !isFlyoutPinned.value) {
        deactivateFlyoutFocusTrap(false);
        onFlyoutLeave();
    }

    if (isMobileViewport.value && isOffCanvasShown.value) {
        closeOffCanvas();
    }
};
const onNavigationLinkClicked = () => {
    closeNavigationOverlays();
};
const dismissOffCanvas = () => {
    if (offCanvasFocusTrap.value) {
        offCanvasFocusTrap.value.deactivate();
        return;
    }

    closeOffCanvas();
};
const activateOffCanvasFocusTrap = () => {
    void nextTick(() => {
        const panelElement = swAdminMenu.value;

        if (!panelElement || !isOffCanvasShown.value || offCanvasFocusTrap.value) {
            return;
        }

        offCanvasFocusTrap.value = createFocusTrap(panelElement, {
            escapeDeactivates: true,
            clickOutsideDeactivates: false,
            allowOutsideClick: true,
            returnFocusOnDeactivate: true,
            delayInitialFocus: false,
            fallbackFocus: panelElement,
            onDeactivate: () => {
                stopMenuDropdownObserver();
                offCanvasFocusTrap.value = null;
                Contena.Utils.EventBus.emit('ct-admin-menu/toggle-offcanvas', false);
            },
        });

        offCanvasFocusTrap.value.activate();
        startMenuDropdownObserver(panelElement);
    });
};
function startMenuDropdownObserver(panelElement) {
    menuDropdownObserver.value = new MutationObserver(syncMenuDropdownFocusOwner);
    menuDropdownObserver.value.observe(panelElement, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['data-state'],
    });
}
function stopMenuDropdownObserver() {
    menuDropdownObserver.value?.disconnect();
    menuDropdownObserver.value = null;
    openMenuDropdownTrigger.value = null;
}
function syncMenuDropdownFocusOwner() {
    if (!offCanvasFocusTrap.value) {
        return;
    }
    const openTrigger = swAdminMenu.value?.querySelector('[aria-haspopup="menu"][data-state="open"]');
    if (openTrigger && !openMenuDropdownTrigger.value) {
        openMenuDropdownTrigger.value = openTrigger;
        offCanvasFocusTrap.value.pause();
        return;
    }
    if (!openTrigger && openMenuDropdownTrigger.value) {
        const previousTrigger = openMenuDropdownTrigger.value;
        openMenuDropdownTrigger.value = null;
        if (previousTrigger.isConnected) {
            previousTrigger.focus();
        }
        offCanvasFocusTrap.value.unpause();
    }
}
function deactivateOffCanvasFocusTrap() {
    if (!offCanvasFocusTrap.value) {
        return;
    }
    const trap = offCanvasFocusTrap.value;
    offCanvasFocusTrap.value = null;
    trap.deactivate({
        returnFocus: false,
    });
}
function initNavigation() {
    adminMenuStore.value.adminModuleNavigation = menuService.getNavigationFromAdminModules();
}
const collapseAdminMenu = () => {
    adminMenuStore.value.collapseSidebar();
};
const expandAdminMenu = () => {
    adminMenuStore.value.expandSidebar();
};
function getUser() {
    isUserLoading.value = true;
    userService.getUser().then((response) => {
        const userData = response.data;
        delete userData.password;
        Contena.Store.get('session').setCurrentUser(userData);
        isUserLoading.value = false;
    });
}
const onToggleSidebar = () => {
    if (isExpanded.value) {
        collapseAdminMenu();
    } else {
        expandAdminMenu();
    }

    toggleSidebar();
};
const startSidebarToggleWindow = () => {
    isTogglingSidebar.value = true;

    if (toggleSidebarTimeout.value) {
        clearTimeout(toggleSidebarTimeout.value);
    }

    toggleSidebarTimeout.value = window.setTimeout(() => {
        isTogglingSidebar.value = false;
        toggleSidebarTimeout.value = null;
    }, SIDEBAR_TOGGLE_ANIMATION_DURATION);
};
function toggleSidebar() {
    if (!isExpanded.value) {
        adminMenuStore.value.clearExpandedMenuEntries();
        onFlyoutLeave();
    }
    isUserActionsActive.value = false;
    flyoutEntries.value = [];
}
const onLogoutUser = async () => {
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
function addScrollbarOffset() {
    if (!(swAdminMenuBody.value instanceof HTMLElement)) {
        return;
    }
    const scrollbarWidthPx = dom.getScrollbarWidth(swAdminMenuBody.value);
    scrollbarOffset.value = `-${scrollbarWidthPx}px`;
}
const onMenuBranchToggle = ({ entry, open }) => {
    if (!isExpanded.value || !entry || entry.level !== 1) {
        return;
    }

    if (!open) {
        adminMenuStore.value.collapseMenuEntry(entry);
        return;
    }

    collapseInactiveBranches(entry);
    adminMenuStore.value.expandMenuEntry(entry);
};
function collapseInactiveBranches(exceptEntry = null) {
    const exceptKey = exceptEntry ? (exceptEntry.id ?? exceptEntry.path) : null;
    const activeNames = getActiveRouteNames(route, router);
    adminMenuStore.value.expandedEntries
        .filter((expanded) => {
            const key = expanded.id ?? expanded.path;
            if (key === exceptKey) {
                return false;
            }
            const menuEntry = mainMenuEntries.value.find((entry) => (entry.id ?? entry.path) === key);
            return !menuEntry || !isEntryOnActiveRoute(menuEntry, route, activeNames);
        })
        .forEach((expanded) => adminMenuStore.value.collapseMenuEntry(expanded));
}
const onMenuItemHover = (entry, eventTarget) => {
    if (isExpanded.value) {
        return;
    }

    cancelFlyoutClose();

    const target = eventTarget.closest('.ct-admin-menu__navigation-list-item');

    if (!target) {
        return;
    }

    const hasChildrenClass = target.classList.contains('navigation-list-item__has-children');
    const children = hasChildrenClass ? getChildren(entry) : [];

    if (!hasChildrenClass || children.length === 0) {
        onFlyoutLeave();
        return;
    }

    const entryKey = entry.id || entry.path;
    const active = activeEntry.value?.entry;
    const activeKey = active ? active.id || active.path : null;

    if (activeKey === entryKey && flyoutEntries.value.length > 0) {
        return;
    }

    flyoutReferenceElement.value = target.querySelector('.ct-admin-menu__navigation-link') ?? target;
    isFlyoutPinned.value = false;
    flyoutEntries.value = children;
    flyoutTitle.value = getEntryLabel(entry);
    activeEntry.value = { entry, target };
};
const onNavigationListMouseLeave = (event) => {
    if (isSuppressedFlyoutFocusOut(event)) {
        return;
    }

    if (event.relatedTarget?.closest('.ct-admin-menu__flyout-content')) {
        return;
    }

    scheduleFlyoutClose();
};
const onFlyoutMouseLeave = (event) => {
    if (isSuppressedFlyoutFocusOut(event)) {
        return;
    }

    if (event.relatedTarget?.closest('.ct-admin-menu__navigation-list')) {
        return;
    }

    scheduleFlyoutClose();
};
function isSuppressedFlyoutFocusOut(event) {
    return event.type === 'focusout' && isFlyoutPinned.value;
}
const onFlyoutNavigate = ({ disclosesChildren }) => {
    isFlyoutPinned.value = disclosesChildren;
};
function scheduleFlyoutClose() {
    if (isExpanded.value || !flyoutEntries.value.length) {
        return;
    }
    cancelFlyoutClose();
    flyoutCloseTimeoutId.value = window.setTimeout(() => {
        startFlyoutCloseAnimation();
    }, 180);
}
function startFlyoutCloseAnimation() {
    if (!flyoutEntries.value.length) {
        return;
    }
    isFlyoutClosing.value = true;
    flyoutCloseTimeoutId.value = window.setTimeout(() => {
        onFlyoutLeave();
    }, 200);
}
function cancelFlyoutClose() {
    if (flyoutCloseTimeoutId.value) {
        clearTimeout(flyoutCloseTimeoutId.value);
        flyoutCloseTimeoutId.value = null;
    }
    isFlyoutClosing.value = false;
}
function getChildren(entry) {
    return entry.children.filter((child) => {
        if (!child.privilege) {
            return true;
        }
        return acl.can(child.privilege);
    });
}
const getSingleChildTooltipConfig = (entry) => {
    const children = getChildren(entry);
    const shouldShowTooltip = !isExpanded.value && children.length === 0;

    return {
        message: shouldShowTooltip ? getEntryLabel(entry) : '',
        disabled: !shouldShowTooltip,
    };
};
const isFlyoutEntryActive = (entry) => {
    if (isExpanded.value || flyoutEntries.value.length === 0) {
        return false;
    }

    const active = activeEntry.value?.entry;

    return !!active && (active.id || active.path) === (entry.id || entry.path);
};
const onFlyoutFocusRequest = () => {
    void nextTick(() => {
        const flyoutElement = swAdminMenuFlyout.value;

        if (!flyoutElement || flyoutEntries.value.length === 0) {
            return;
        }

        deactivateFlyoutFocusTrap(false);
        flyoutFocusTrap.value = createFocusTrap(flyoutElement, {
            escapeDeactivates: true,
            clickOutsideDeactivates: true,
            returnFocusOnDeactivate: true,
            delayInitialFocus: false,
            fallbackFocus: flyoutElement,
            onDeactivate: () => {
                flyoutFocusTrap.value = null;
                onFlyoutLeave();
            },
        });
        flyoutFocusTrap.value.activate();
    });
};
function deactivateFlyoutFocusTrap(returnFocus = true) {
    if (!flyoutFocusTrap.value) {
        return;
    }
    const trap = flyoutFocusTrap.value;
    flyoutFocusTrap.value = null;
    trap.deactivate({
        returnFocus,
        onDeactivate: () => {},
    });
}
const getNavigationLinks = (container) => {
    return Array.from(container.querySelectorAll('.ct-admin-menu__navigation-link')).filter(
        (link) => !link.closest('[hidden]'),
    );
};
const moveListFocus = (links, event) => {
    if (links.length === 0) {
        return;
    }

    const currentIndex = links.indexOf(document.activeElement);
    let nextIndex = null;

    switch (event.key) {
        case 'ArrowDown':
            nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % links.length;
            break;
        case 'ArrowUp':
            nextIndex = currentIndex < 0 ? links.length - 1 : (currentIndex - 1 + links.length) % links.length;
            break;
        case 'Home':
            nextIndex = 0;
            break;
        case 'End':
            nextIndex = links.length - 1;
            break;
        default:
            return;
    }

    event.preventDefault();
    links[nextIndex]?.focus();
};
const onNavigationKeydown = (event) => {
    if (!swAdminMenuBody.value) {
        return;
    }

    moveListFocus(getNavigationLinks(swAdminMenuBody.value), event);
};
const onFlyoutKeydown = (event) => {
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        deactivateFlyoutFocusTrap(true);
        onFlyoutLeave();
        return;
    }

    if (!swAdminMenuFlyout.value) {
        return;
    }

    moveListFocus(getNavigationLinks(swAdminMenuFlyout.value), event);
};
function onFlyoutLeave() {
    deactivateFlyoutFocusTrap();
    cancelFlyoutClose();
    isFlyoutPinned.value = false;
    activeEntry.value = null;
    flyoutReferenceElement.value = null;
    flyoutEntries.value = [];
    flyoutTitle.value = '';
}
function getEntryLabel(entry) {
    if (!entry?.label) {
        return '';
    }
    if (entry.label instanceof Object) {
        return entry.label.translated ? entry.label.label : t(entry.label.label);
    }
    return t(entry.label);
}
const expandAncestorBranchesForCurrentRoute = () => {
    if (!isExpanded.value) {
        return;
    }

    const activeNames = getActiveRouteNames(route, router);
    const owner = mainMenuEntries.value.find(
        (entry) => (entry.children?.length ?? 0) > 0 && isEntryOnActiveRoute(entry, route, activeNames),
    );
    const ownerKey = owner ? (owner.id ?? owner.path) : null;

    if (ownerKey === activeBranchKey.value && (!owner || isNavigationEntryExpanded(owner))) {
        return;
    }

    collapseInactiveBranches(owner);
    activeBranchKey.value = ownerKey;

    if (owner && !isNavigationEntryExpanded(owner)) {
        adminMenuStore.value.expandMenuEntry(owner);
    }
};
function isNavigationEntryExpanded(entry) {
    if (!entry) {
        return false;
    }
    const key = entry.id ?? entry.path;
    return adminMenuStore.value.expandedEntries.some((expanded) => (expanded.id ?? expanded.path) === key);
}

watch(
    () => isExpanded.value,
    () => {
        toggleSidebar();
        startSidebarToggleWindow();
    },
);
watch(isOffCanvasShown, (isShown) => {
    if (isShown) {
        activateOffCanvasFocusTrap();
    } else {
        deactivateOffCanvasFocusTrap();
    }
});
watch(isMobileViewport, (isMobile) => {
    if (!isMobile && isOffCanvasShown.value) {
        closeOffCanvas();
    }

    if (isMobile) {
        isUserActionsActive.value = false;
    }
});
watch(
    () => route.path,
    () => {
        closeNavigationOverlays();
        isUserActionsActive.value = false;
        void nextTick(() => expandAncestorBranchesForCurrentRoute());
    },
    { immediate: true },
);

createdComponent();
onMounted(mountedComponent);

onBeforeUnmount(() => {
    cancelFlyoutClose();
    deactivateFlyoutFocusTrap(false);

    beforeUnmountedComponent();
});

swDefinePublic({
    menuService,
    loginService,
    userService,
    systemConfigApiService,
    acl,
    activeEntry,
    isOffCanvasShown,
    isUserActionsActive,
    flyoutEntries,
    flyoutTitle,
    flyoutCloseTimeoutId,
    isFlyoutClosing,
    isFlyoutPinned,
    scrollbarOffset,
    isUserLoading,
    flyoutReferenceElement,
    activeBranchKey,
    shopName,
    isTogglingSidebar,
    isViewportResizing,
    currentUser,
    isExpanded,
    userTitle,
    currentLocale,
    currentExpandedMenuEntries,
    adminModuleNavigation,
    navigationEntries,
    mainMenuEntries,
    flyoutEnabled,
    flyoutPlacement,
    flyoutOffset,
    sidebarCollapseIcon,
    scrollbarOffsetStyle,
    adminMenuClasses,
    assetFilter,
    userName,
    userActionsAriaLabel,
    avatarUrl,
    adminMenuStore,
    createdComponent,
    mountedComponent,
    beforeUnmountedComponent,
    loadShopName,
    onViewportResize,
    onToggleCanvas,
    closeOffCanvas,
    closeNavigationOverlays,
    onNavigationLinkClicked,
    dismissOffCanvas,
    activateOffCanvasFocusTrap,
    deactivateOffCanvasFocusTrap,
    isMobileViewport,
    viewportWidth,
    initNavigation,
    collapseAdminMenu,
    expandAdminMenu,
    getUser,
    onToggleSidebar,
    startSidebarToggleWindow,
    toggleSidebar,
    onLogoutUser,
    addScrollbarOffset,
    onMenuBranchToggle,
    collapseInactiveBranches,
    onMenuItemHover,
    onNavigationListMouseLeave,
    onFlyoutMouseLeave,
    isSuppressedFlyoutFocusOut,
    onFlyoutNavigate,
    scheduleFlyoutClose,
    startFlyoutCloseAnimation,
    cancelFlyoutClose,
    getChildren,
    getSingleChildTooltipConfig,
    isFlyoutEntryActive,
    onFlyoutFocusRequest,
    deactivateFlyoutFocusTrap,
    getNavigationLinks,
    moveListFocus,
    onNavigationKeydown,
    onFlyoutKeydown,
    onFlyoutLeave,
    getEntryLabel,
    expandAncestorBranchesForCurrentRoute,
    isNavigationEntryExpanded,
});

defineExpose({
    menuService,
    loginService,
    userService,
    systemConfigApiService,
    acl,
    activeEntry,
    isOffCanvasShown,
    isUserActionsActive,
    flyoutEntries,
    flyoutTitle,
    flyoutCloseTimeoutId,
    isFlyoutClosing,
    isFlyoutPinned,
    scrollbarOffset,
    isUserLoading,
    flyoutReferenceElement,
    activeBranchKey,
    shopName,
    isTogglingSidebar,
    isViewportResizing,
    currentUser,
    isExpanded,
    userTitle,
    currentLocale,
    currentExpandedMenuEntries,
    adminModuleNavigation,
    navigationEntries,
    mainMenuEntries,
    flyoutEnabled,
    flyoutPlacement,
    flyoutOffset,
    sidebarCollapseIcon,
    scrollbarOffsetStyle,
    adminMenuClasses,
    userName,
    userActionsAriaLabel,
    avatarUrl,
    adminMenuStore,
    createdComponent,
    mountedComponent,
    beforeUnmountedComponent,
    loadShopName,
    onViewportResize,
    onToggleCanvas,
    closeOffCanvas,
    closeNavigationOverlays,
    onNavigationLinkClicked,
    dismissOffCanvas,
    activateOffCanvasFocusTrap,
    deactivateOffCanvasFocusTrap,
    isMobileViewport,
    viewportWidth,
    initNavigation,
    collapseAdminMenu,
    expandAdminMenu,
    getUser,
    onToggleSidebar,
    startSidebarToggleWindow,
    toggleSidebar,
    onLogoutUser,
    addScrollbarOffset,
    onMenuBranchToggle,
    collapseInactiveBranches,
    onMenuItemHover,
    onNavigationListMouseLeave,
    onFlyoutMouseLeave,
    isSuppressedFlyoutFocusOut,
    onFlyoutNavigate,
    scheduleFlyoutClose,
    startFlyoutCloseAnimation,
    cancelFlyoutClose,
    getChildren,
    getSingleChildTooltipConfig,
    isFlyoutEntryActive,
    onFlyoutFocusRequest,
    deactivateFlyoutFocusTrap,
    getNavigationLinks,
    moveListFocus,
    onNavigationKeydown,
    onFlyoutKeydown,
    onFlyoutLeave,
    getEntryLabel,
    expandAncestorBranchesForCurrentRoute,
    isNavigationEntryExpanded,
});
</script>

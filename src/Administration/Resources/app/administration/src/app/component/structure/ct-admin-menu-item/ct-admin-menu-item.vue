<template>
    <ct-block name="sw_admin_menu_item">
        <mt-collapsible
            v-if="showMenuItem && hasCollapsibleSubtree"
            as="li"
            :class="collapsibleLiClass"
            :aria-current="rowActive ? 'page' : 'false'"
            :open="collapsibleOpen"
            @update:open="onCollapsibleOpenUpdate"
            @mouseenter="$emit('menu-item-hover', entry, $event.currentTarget)"
            @keydown="onCollapsedParentKeydown"
        >
            <div class="ct-admin-menu__navigation-item-row">
                <template v-if="entryPath">
                    <router-link
                        class="ct-admin-menu__navigation-link"
                        :to="getLinkToProp"
                        :class="{ 'router-link-active': rowActive }"
                        :active-class="routerLinkActiveClass"
                        :exact-active-class="routerLinkExactActiveClass"
                        :aria-expanded="collapsibleOpen"
                        :aria-label="collapsedAriaLabel"
                        v-bind="collapsedFlyoutAria"
                        @click="onNavigationLinkClick"
                    >
                        <ct-block name="sw_admin_menu_item_icon">
                            <mt-icon
                                v-if="displayIcon"
                                :size="iconSize"
                                class="ct-admin-menu__navigation-link-icon"
                                :name="navigationIconName"
                            />
                        </ct-block>

                        <ct-block name="sw_admin_menu_item_text">
                            <span
                                class="ct-admin-menu__navigation-link-label"
                                :class="collapsibleText ? 'collapsible-text hide-on-collapse' : ''"
                                :title="getEntryLabel"
                            >
                                {{ getEntryLabel }}
                            </span>
                        </ct-block>

                        <slot name="additional-text"></slot>

                        <ct-block name="sw_admin_menu_item_expand_indicator_linked">
                            <span class="ct-admin-menu__navigation-link-expand-icon-box">
                                <mt-icon
                                    :name="expandIcon"
                                    size="8"
                                    class="ct-admin-menu__navigation-link-expand-icon collapsible-text hide-on-collapse"
                                />
                            </span>
                        </ct-block>
                    </router-link>
                </template>

                <mt-collapsible-trigger
                    v-else
                    type="button"
                    class="ct-admin-menu__navigation-link"
                    :class="{ 'router-link-active': rowActive }"
                    :aria-label="collapsedAriaLabel"
                    v-bind="collapsedFlyoutAria"
                >
                    <ct-block name="sw_admin_menu_item_navigation_icon">
                        <mt-icon
                            v-if="displayIcon"
                            :size="iconSize"
                            class="ct-admin-menu__navigation-link-icon"
                            :name="navigationIconName"
                        />
                    </ct-block>

                    <ct-block name="sw_admin_menu_item_navigation_text">
                        <span
                            class="ct-admin-menu__navigation-link-label"
                            :class="collapsibleText ? 'collapsible-text hide-on-collapse' : ''"
                            :title="getEntryLabel"
                        >
                            {{ getEntryLabel }}
                        </span>
                    </ct-block>

                    <slot name="additional-text"></slot>

                    <ct-block name="sw_admin_menu_item_expand_indicator_fold">
                        <span class="ct-admin-menu__navigation-link-expand-icon-box">
                            <mt-icon
                                :name="expandIcon"
                                size="8"
                                class="ct-admin-menu__navigation-link-expand-icon collapsible-text hide-on-collapse"
                            />
                        </span>
                    </ct-block>
                </mt-collapsible-trigger>
            </div>

            <ct-block name="sw_sidebar_sub_items_list">
                <mt-collapsible-content as="ul" class="ct-admin-menu__sub-navigation-list">
                    <ct-admin-menu-item
                        v-for="(childEntry, subMenuIndex) in children"
                        :key="childEntry.id ?? childEntry.path ?? subMenuIndex"
                        :entry="childEntry"
                        :menu-depth="menuDepth + 1"
                        :display-icon="false"
                        :sidebar-expanded="sidebarExpanded"
                        :collapsible-text="collapsibleText"
                        :icon-size="iconSize"
                        @menu-item-hover="forwardMenuItemHover"
                        @flyout-navigate="forwardFlyoutNavigate"
                        @navigation-link-click="forwardNavigationLinkClick"
                    />
                </mt-collapsible-content>
            </ct-block>
        </mt-collapsible>

        <li
            v-else-if="showMenuItem"
            :class="leafLiClass"
            :aria-current="rowActive ? 'page' : 'false'"
            @mouseenter="$emit('menu-item-hover', entry, $event.currentTarget)"
        >
            <mt-tooltip :content="getEntryLabel" placement="right">
                <template #default="tooltipProps">
                    <div class="ct-admin-menu__navigation-item-row" v-bind="collapsedTooltipTriggerProps(tooltipProps)">
                        <ct-block name="sw_admin_menu_item_router_link">
                            <router-link
                                v-if="entryPath"
                                class="ct-admin-menu__navigation-link"
                                :to="getLinkToProp"
                                :class="{ 'router-link-active': rowActive }"
                                :active-class="routerLinkActiveClass"
                                :exact-active-class="routerLinkExactActiveClass"
                                :aria-label="collapsedAriaLabel"
                                @click="onNavigationLinkClick"
                            >
                                <mt-icon
                                    v-if="displayIcon"
                                    :size="iconSize"
                                    class="ct-admin-menu__navigation-link-icon"
                                    :name="navigationIconName"
                                />

                                <span
                                    class="ct-admin-menu__navigation-link-label"
                                    :class="collapsibleText ? 'collapsible-text hide-on-collapse' : ''"
                                    :title="getEntryLabel"
                                >
                                    {{ getEntryLabel }}
                                </span>

                                <slot name="additional-text"></slot>
                            </router-link>
                        </ct-block>

                        <ct-block name="sw_admin_menu_item_external_link">
                            <a
                                v-if="!entryPath && entry.link"
                                :href="entry.link"
                                :target="entry.target"
                                :title="getEntryLabel"
                                :aria-label="collapsedAriaLabel"
                                class="ct-admin-menu__navigation-link"
                            >
                                <mt-icon
                                    v-if="displayIcon"
                                    :size="iconSize"
                                    class="ct-admin-menu__navigation-link-icon"
                                    :name="navigationIconName"
                                />

                                <span
                                    class="ct-admin-menu__navigation-link-label"
                                    :class="collapsibleText ? 'collapsible-text hide-on-collapse' : ''"
                                    :title="getEntryLabel"
                                >
                                    {{ getEntryLabel }}
                                </span>

                                <slot name="additional-text"></slot>
                            </a>
                        </ct-block>

                        <ct-block name="sw_admin_menu_item_navigation_link">
                            <span
                                v-if="!entryPath && !entry.link"
                                class="ct-admin-menu__navigation-link"
                                :class="{ 'router-link-active': rowActive }"
                            >
                                <mt-icon
                                    v-if="displayIcon"
                                    :size="iconSize"
                                    class="ct-admin-menu__navigation-link-icon"
                                    :name="navigationIconName"
                                />

                                <span
                                    class="ct-admin-menu__navigation-link-label"
                                    :class="collapsibleText ? 'collapsible-text hide-on-collapse' : ''"
                                    :title="getEntryLabel"
                                >
                                    {{ getEntryLabel }}
                                </span>

                                <slot name="additional-text"></slot>
                            </span>
                        </ct-block>
                    </div>
                </template>
            </mt-tooltip>
        </li>
    </ct-block>
</template>

<script setup>
import { getActiveRouteNames, isEntryOnActiveRoute, entryParamsMatchRoute } from './menu-item-active.helper';
import './ct-admin-menu-item.scss';
const TOOLTIP_OPEN_TRIGGER_PROPS = [
    'onMouseover',
    'onFocus',
    'aria-describedby',
];

const props = defineProps({
    entry: {
        type: Object,
        required: true,
    },

    menuDepth: {
        type: Number,
        required: false,
        default: 1,
        validator: (v) =>
            [
                1,
                2,
                3,
            ].includes(v),
    },

    displayIcon: {
        type: Boolean,
        default: true,
        required: false,
    },
    iconSize: {
        type: String,
        default: '16px',
        required: false,
    },
    collapsibleText: {
        type: Boolean,
        default: true,
        required: false,
    },
    sidebarExpanded: {
        type: Boolean,
        default: true,
        required: false,
    },
    isExpanded: {
        type: Boolean,
        default: false,
        required: false,
    },
    showActiveState: {
        type: Boolean,
        default: true,
        required: false,
    },
    flyoutActive: {
        type: Boolean,
        default: false,
        required: false,
    },
});
const emit = defineEmits([
    'menu-item-hover',
    'branch-toggle',
    'flyout-focus-request',
    'flyout-close-request',
    'flyout-navigate',
    'navigation-link-click',
]);

import { ref, computed, inject, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const $route = useRoute();
const { t } = useI18n();

const acl = inject('acl');
const suppressRouteKeepsFolderOpen = ref(false);
const manualNestedOpen = ref(false);

const isLeafDepth = computed(() => {
    // Admin menu supports at most three levels; level-3 rows are leaf items only
    return props.menuDepth >= 3;
});
const activeRouteNames = computed(() => {
    return getActiveRouteNames($route, router);
});
const aclFilteredEntry = computed(() => {
    // The entry with its children reduced to the ones the user may see.
    return { ...props.entry, children: children.value };
});
const hasActiveChild = computed(() => {
    return children.value.some((child) => isEntryOnActiveRoute(child, $route, activeRouteNames.value));
});
const rowActive = computed(() => {
    if (!props.showActiveState) {
        return false;
    }

    if (!isEntryOnActiveRoute(aclFilteredEntry.value, $route, activeRouteNames.value)) {
        return false;
    }

    const selfIsCurrent =
        !hasActiveChild.value &&
        !!props.entry.path &&
        activeRouteNames.value.has(props.entry.path) &&
        entryParamsMatchRoute(props.entry, $route);

    if (!selfIsCurrent && children.value.length > 0 && submenuVisuallyOpen.value) {
        return false;
    }

    return true;
});
const getLinkToProp = computed(() => {
    if (props.entry.params) {
        return { name: props.entry.path, params: props.entry.params };
    }

    return { name: props.entry.path };
});
const getEntryLabel = computed(() => {
    if (props.entry.label instanceof Object) {
        return props.entry.label.translated ? props.entry.label.label : t(props.entry.label.label);
    }
    return t(props.entry.label);
});
const showMenuItem = computed(() => {
    // special case for settings module, children are stored in a global state store
    if (props.entry.path === 'ct.settings.index') {
        return acl.hasActiveSettingModules();
    }

    if (children.value.length > 0) {
        return true;
    }

    if (getLinkToProp.value && getLinkToProp.value.name) {
        const { name } = getLinkToProp.value;

        return hasAccessToRoute(name);
    }

    return false;
});
const entryPath = computed(() => {
    if (props.entry.path && hasAccessToRoute(props.entry.path)) {
        return props.entry.path;
    }

    return undefined;
});
const children = computed(() => {
    return props.entry.children.filter((child) => {
        if (!child.privilege) {
            return true;
        }

        return acl.can(child.privilege);
    });
});
const hasCollapsibleSubtree = computed(() => {
    // Ignores the sidebar state on purpose — switching template branch on collapse makes the icons flash
    return children.value.length > 0 && !isLeafDepth.value;
});
const routeKeepsFolderOpen = computed(() => {
    if (!children.value.length || suppressRouteKeepsFolderOpen.value) {
        return false;
    }

    return hasActiveChild.value;
});
const submenuVisuallyOpen = computed(() => {
    if (props.menuDepth === 1) {
        if (!props.sidebarExpanded) {
            return false;
        }

        const hasExpandedBranches = Contena.Store.get('adminMenu').expandedEntries.length > 0;

        return hasExpandedBranches ? props.isExpanded : props.isExpanded || routeKeepsFolderOpen.value;
    }

    return routeKeepsFolderOpen.value || manualNestedOpen.value;
});
const collapsibleOpen = computed(() => {
    return hasCollapsibleSubtree.value && submenuVisuallyOpen.value;
});
const expandIcon = computed(() => {
    return submenuVisuallyOpen.value ? 'regular-chevron-up-xs' : 'regular-chevron-down-xs';
});
const collapsibleLiClass = computed(() => {
    return [
        'ct-admin-menu__navigation-list-item',
        getElementClasses(props.entry.id || entryPath.value),
        {
            'is--entry-expanded': collapsibleOpen.value,
            'is--child-active': childRouteActive.value,
            'is--flyout-enabled': props.flyoutActive,
        },
    ];
});
const leafLiClass = computed(() => {
    return [
        'ct-admin-menu__navigation-list-item',
        getElementClasses(props.entry.id || entryPath.value),
        { 'is--entry-expanded': submenuVisuallyOpen.value, 'is--child-active': childRouteActive.value },
    ];
});
const navigationIconName = computed(() => {
    const isActive = rowActive.value || childRouteActive.value;

    return getIconName(props.entry.icon, isActive);
});
const childRouteActive = computed(() => {
    return children.value.length > 0 && submenuVisuallyOpen.value && hasActiveChild.value;
});
const collapsedFlyoutAria = computed(() => {
    if (props.sidebarExpanded || props.menuDepth !== 1 || children.value.length === 0) {
        return {};
    }

    // aria-controls only while open
    if (!props.flyoutActive) {
        return { 'aria-expanded': 'false' };
    }

    return {
        'aria-expanded': 'true',
        'aria-controls': 'ct-admin-menu-flyout',
    };
});
const collapsedAriaLabel = computed(() => {
    // Collapsed top-level rows hide their label, so the accessible name needs an aria-label.
    return !props.sidebarExpanded && props.menuDepth === 1 ? getEntryLabel.value : null;
});
const routerLinkActiveClass = computed(() => {
    return props.showActiveState ? 'router-link-active' : '';
});
const routerLinkExactActiveClass = computed(() => {
    return props.showActiveState ? 'router-link-exact-active' : '';
});
const showsCollapsedTooltip = computed(() => {
    // Top-level entries without children have no flyout, label is accessible via a tooltip
    return !props.sidebarExpanded && props.menuDepth === 1 && children.value.length === 0;
});

const collapsedTooltipTriggerProps = (tooltipProps) => {
    if (showsCollapsedTooltip.value) {
        // Focus does not bubble to the non-focusable row, focusin/focusout do
        const { onFocus, onBlur, ...bubblingProps } = tooltipProps;

        return { ...bubblingProps, onFocusin: onFocus, onFocusout: onBlur };
    }

    return Object.fromEntries(Object.entries(tooltipProps).filter(([key]) => !TOOLTIP_OPEN_TRIGGER_PROPS.includes(key)));
};
function hasAccessToRoute(path) {
    const match = router.getRoutes().find((route) => route.name === path);
    if (!match?.meta) {
        return true;
    }
    return acl.can(match.meta.privilege);
}
function getIconName(name, isActive = false) {
    if (isActive && typeof name === 'string') {
        if (name.startsWith('regular-')) {
            return name.replace('regular-', 'solid-');
        }
        if (name.startsWith('icon/regular/')) {
            return name.replace('icon/regular/', 'icon/solid/');
        }
    }
    return `${name}`;
}
function getElementClasses(menuItemName) {
    const name = menuItemName.replace(/\./g, '-');
    const hasChildren = children.value.length > 0;
    const convertName = props.entry.id || props.entry.path;
    const convertedId = convertName.replace(/\./g, '-');
    return [
        convertedId,
        `navigation-list-item__type-${props.entry.moduleType}`,
        `navigation-list-item__${name}`,
        `ct-admin-menu__item--${props.entry.id}`,
        `navigation-list-item__level-${props.entry.level}`,
        {
            'navigation-list-item__has-children': hasChildren,
            'navigation-list-item--nested': props.menuDepth > 1,
        },
    ];
}
const toggleSubmenu = () => {
    if (!hasCollapsibleSubtree.value) {
        return;
    }

    onCollapsibleOpenUpdate(!collapsibleOpen.value);
};
const onNavigationLinkClick = () => {
    if (!props.sidebarExpanded) {
        emit('flyout-navigate', { disclosesChildren: hasCollapsibleSubtree.value });
    }

    // No-op unless this row has a collapsible subtree.
    toggleSubmenu();

    emit('navigation-link-click');
};
const forwardNavigationLinkClick = () => {
    emit('navigation-link-click');
};
const forwardFlyoutNavigate = (payload) => {
    emit('flyout-navigate', payload);
};
function onCollapsibleOpenUpdate(open) {
    suppressRouteKeepsFolderOpen.value = !open;
    if (props.menuDepth >= 2) {
        manualNestedOpen.value = open;
    }
    if (props.menuDepth === 1 && props.sidebarExpanded) {
        emit('branch-toggle', {
            entry: props.entry,
            open,
        });
    }
}
const onCollapsedParentKeydown = (event) => {
    // Keyboard access to the collapsed flyout - disclosure navigation pattern
    if (props.sidebarExpanded || props.menuDepth !== 1 || children.value.length === 0) {
        return;
    }

    if ((event.key === 'Escape' || event.key === 'ArrowLeft') && props.flyoutActive) {
        emit('flyout-close-request');
        return;
    }

    const isActivationKey = event.key === 'Enter' || event.key === ' ';
    // Entries with an own route keep Enter/Space for navigation.
    const opensFlyout = event.key === 'ArrowRight' || (isActivationKey && !entryPath.value);

    if (!opensFlyout) {
        return;
    }

    event.preventDefault();
    emit('menu-item-hover', props.entry, event.currentTarget);
    emit('flyout-focus-request');
};
const forwardMenuItemHover = (entry, target) => {
    emit('menu-item-hover', entry, target);
};

watch(
    () => $route.fullPath,
    () => {
        suppressRouteKeepsFolderOpen.value = false;
    },
);

swDefinePublic({
    acl,
    suppressRouteKeepsFolderOpen,
    manualNestedOpen,
    isLeafDepth,
    activeRouteNames,
    aclFilteredEntry,
    hasActiveChild,
    rowActive,
    getLinkToProp,
    getEntryLabel,
    showMenuItem,
    entryPath,
    children,
    hasCollapsibleSubtree,
    routeKeepsFolderOpen,
    submenuVisuallyOpen,
    collapsibleOpen,
    expandIcon,
    collapsibleLiClass,
    leafLiClass,
    navigationIconName,
    childRouteActive,
    collapsedFlyoutAria,
    collapsedAriaLabel,
    routerLinkActiveClass,
    routerLinkExactActiveClass,
    showsCollapsedTooltip,
    collapsedTooltipTriggerProps,
    hasAccessToRoute,
    getIconName,
    getElementClasses,
    toggleSubmenu,
    onNavigationLinkClick,
    forwardNavigationLinkClick,
    forwardFlyoutNavigate,
    onCollapsibleOpenUpdate,
    onCollapsedParentKeydown,
    forwardMenuItemHover,
});

defineExpose({
    acl,
    suppressRouteKeepsFolderOpen,
    manualNestedOpen,
    isLeafDepth,
    activeRouteNames,
    aclFilteredEntry,
    hasActiveChild,
    rowActive,
    getLinkToProp,
    getEntryLabel,
    showMenuItem,
    entryPath,
    children,
    hasCollapsibleSubtree,
    routeKeepsFolderOpen,
    submenuVisuallyOpen,
    collapsibleOpen,
    expandIcon,
    collapsibleLiClass,
    leafLiClass,
    navigationIconName,
    childRouteActive,
    collapsedFlyoutAria,
    collapsedAriaLabel,
    routerLinkActiveClass,
    routerLinkExactActiveClass,
    showsCollapsedTooltip,
    collapsedTooltipTriggerProps,
    hasAccessToRoute,
    getIconName,
    getElementClasses,
    toggleSubmenu,
    onNavigationLinkClick,
    forwardNavigationLinkClick,
    forwardFlyoutNavigate,
    onCollapsibleOpenUpdate,
    onCollapsedParentKeydown,
    forwardMenuItemHover,
});
</script>

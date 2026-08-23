<template>
    <ct-block name="sw_meteor_page">
        <div class="ct-meteor-page" :class="pageClasses">
            <div ref="pageBody" class="ct-meteor-page__body">
                <header ref="pageHeader" class="ct-meteor-page__head-area">
                    <div class="ct-meteor-page__head-area-top-bar-content">
                        <slot name="search-bar">
                            <ct-block name="sw_meteor_page_search_bar">
                                <ct-search-bar />
                            </ct-block>
                        </slot>
                    </div>

                    <div class="ct-meteor-page__head-area-global-actions">
                        <ct-help-center-v2 />
                        <ct-block name="sw_meteor_page_notification_center">
                            <ct-notification-center />
                        </ct-block>
                    </div>

                    <div v-if="!hideSmartBar" class="ct-meteor-page__smart-bar">
                        <div class="ct-meteor-page__smart-bar-navigation">
                            <slot name="smart-bar-back">
                                <ct-block name="sw_meteor_page_navigation">
                                    <ct-meteor-navigation :from-link="fromLink" />
                                </ct-block>
                            </slot>
                        </div>

                        <div class="ct-meteor-page__smart-bar-content">
                            <div class="ct-meteor-page__smart-bar-module-info">
                                <div v-if="!hideIcon && hasIconOrIconSlot" class="ct-meteor-page__smart-bar-module-icon">
                                    <slot name="smart-bar-icon">
                                        <ct-block name="sw_meteor_page_smart_bar_icon">
                                            <mt-icon v-if="hasIcon" :name="module.icon" :color="pageColor" />
                                        </ct-block>
                                    </slot>
                                </div>

                                <div class="ct-meteor-page__smart-bar-header">
                                    <h2 class="ct-meteor-page__smart-bar-title">
                                        <slot name="smart-bar-header">
                                            <ct-block name="sw_meteor_page_smart_bar_title">
                                                <template v-if="module && module.title">
                                                    {{ $t(module.title) }}
                                                </template>
                                            </ct-block>
                                        </slot>
                                    </h2>

                                    <div class="ct-meteor-page__smart-bar-meta">
                                        <ct-block name="sw_meteor_page_smart_bar_meta">
                                            <slot name="smart-bar-header-meta"></slot>
                                        </ct-block>
                                    </div>
                                </div>

                                <div class="ct-meteor-page__smart-bar-description">
                                    <ct-block name="sw_meteor_page_smart_bar_description">
                                        <slot name="smart-bar-description"></slot>
                                    </ct-block>
                                </div>
                            </div>

                            <div class="ct-meteor-page__smart-bar-actions">
                                <ct-block name="sw_meteor_page_smart_bar_actions">
                                    <slot name="smart-bar-actions"></slot>
                                </ct-block>
                            </div>

                            <div class="ct-meteor-page__smart-bar-context-buttons">
                                <ct-block name="sw_meteor_page_smart_bar_context_buttons">
                                    <slot name="smart-bar-context-buttons"></slot>
                                </ct-block>
                            </div>
                        </div>
                    </div>

                    <div v-if="hasTabs" class="ct-meteor-page__smart-bar-tabs">
                        <ct-block name="sw_meteor_page_smart_bar_tabs">
                            <mt-tabs
                                position-identifier="ct-meteor-page"
                                :default-item="defaultTab"
                                :items="tabItems"
                                :small="true"
                                @new-item-active="emitNewTab"
                            />
                        </ct-block>
                    </div>
                </header>
                <main class="ct-meteor-page__content">
                    <ct-block name="sw_meteor_page_content">
                        <div v-if="fullWidth" class="ct-meteor-page__scrollable-content">
                            <slot></slot>
                        </div>

                        <template v-else>
                            <slot></slot>
                        </template>
                    </ct-block>
                </main>
            </div>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import type { VNode } from 'vue';
import type { RouteLocationNamedRaw, RouteLocationRaw } from 'vue-router';
import type { TabItem } from '@contena/meteor-component-library/dist/esm/MtTabs';
import type { ModuleManifest } from 'src/core/factory/module.factory';
import { getTabItemsFromSlotContent, getTextFromSlotItem, triggerTabItemClick } from '../tab-slot-parser';
import './ct-meteor-page.scss';

type SwTabsItemProps = {
    disabled?: boolean;
    hasError?: boolean;
    hasWarning?: boolean;
    name?: string;
    onClick?: (() => void) | Array<() => void>;
    route?: RouteLocationRaw;
    title?: string;
};

type VNodeTypeWithName = {
    name?: string;
};

type VNodeChildrenWithDefaultSlot = {
    default?: () => VNode[];
};

const props = defineProps({
    fullWidth: {
        type: Boolean,
        required: false,
        default: false,
    },

    hideIcon: {
        type: Boolean,
        required: false,
        default: false,
    },

    hideSmartBar: {
        type: Boolean,
        required: false,
        default: false,
    },

    fromLink: {
        type: Object as PropType<RouteLocationNamedRaw | null>,
        required: false,
        default: null,
    },
});

import { type PropType, ref, computed, useSlots, onBeforeUnmount, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const slots = useSlots();
const emit = defineEmits<{
    'new-item-active': [tabItem: string];
}>();

const module = ref(null);
const parentRoute = ref(null);

const pageClasses = computed(() => {
    return {
        'ct-meteor-page--full-width': props.fullWidth,
        'ct-meteor-page--hide-smart-bar': props.hideSmartBar,
    };
});
const hasIcon = computed(() => {
    return typeof module.value?.icon === 'string';
});
const hasIconOrIconSlot = computed(() => {
    return hasIcon.value || typeof slots['smart-bar-icon'] !== 'undefined';
});
const pageColor = computed(() => {
    return module.value?.color ?? '#d8dde6';
});
const hasTabs = computed(() => {
    return typeof slots['page-tabs'] !== 'undefined';
});
const tabItems = computed<TabItem[]>(() => getTabItemsFromSlot());
const defaultTab = computed(() => {
    const routeName = typeof route.name === 'string' ? route.name : undefined;

    if (routeName && tabItems.value.some((tab) => tab.name === routeName)) {
        return routeName;
    }

    return tabItems.value[0]?.name ?? '';
});

const mountedComponent = () => {
    initPage();
};
const emitNewTab = (tabItem: string): void => {
    emit('new-item-active', tabItem);
};
function getTabItemsFromSlot(): TabItem[] {
    const slotContent = slots['page-tabs']?.();
    if (!slotContent) {
        return [];
    }
    return getTabItemsFromSlotContent(slotContent, {
        isTabItem,
        createTabItem,
    });
}
function createTabItem(item: VNode): TabItem {
    const tabProps = (item.props ?? {}) as SwTabsItemProps;
    const routeName = getRouteName(tabProps.route);
    const slotText = getTabItemDefaultSlotText(item);
    const label = slotText ?? tabProps.title ?? tabProps.name ?? routeName ?? '';
    const tabItem: TabItem = {
        label,
        name: tabProps.name ?? tabProps.title ?? routeName ?? label,
    };
    if (tabProps.hasError !== undefined) {
        tabItem.hasError = tabProps.hasError;
    }
    if (tabProps.disabled !== undefined) {
        tabItem.disabled = tabProps.disabled;
    }
    if (tabProps.hasWarning) {
        tabItem.badge = 'warning';
    }
    if (tabProps.route || tabProps.onClick) {
        tabItem.onClick = () => {
            if (tabProps.route) {
                void router.push(tabProps.route);
            }
            triggerTabItemClick(tabProps.onClick);
        };
    }
    return tabItem;
}
function getTabItemDefaultSlotText(item: VNode): string | undefined {
    const children = item.children as VNodeChildrenWithDefaultSlot | undefined;
    const defaultSlotContent = children?.default?.();
    if (!defaultSlotContent) {
        return undefined;
    }
    return defaultSlotContent
        .map((slotItem) => getTextFromSlotItem(slotItem))
        .join('')
        .trim();
}
function getRouteName(tabRoute: RouteLocationRaw | undefined): string | undefined {
    if (typeof tabRoute !== 'object' || tabRoute === null || !('name' in tabRoute)) {
        return undefined;
    }
    return typeof tabRoute.name === 'string' ? tabRoute.name : undefined;
}
function isTabItem(item: VNode): boolean {
    const tabProps = item.props as SwTabsItemProps | null;
    const children = item.children as VNodeChildrenWithDefaultSlot | undefined;
    return (
        (item.type as VNodeTypeWithName | undefined)?.name === 'ct-tabs-item' ||
        (typeof children?.default === 'function' &&
            tabProps !== null &&
            (tabProps.name !== undefined || tabProps.route !== undefined || tabProps.title !== undefined))
    );
}
function initPage() {
    if (typeof route?.meta?.$module !== 'undefined') {
        module.value = route.meta.$module as ModuleManifest | null;
    }
    if (typeof route?.meta?.parentPath === 'string') {
        parentRoute.value = route.meta.parentPath;
    }
}

onBeforeUnmount(() => {
    void Contena.Store.get('error').resetApiErrors();
});
onMounted(() => {
    mountedComponent();
});

swDefinePublic({
    module,
    parentRoute,
    pageClasses,
    hasIcon,
    hasIconOrIconSlot,
    pageColor,
    hasTabs,
    tabItems,
    defaultTab,
    mountedComponent,
    emitNewTab,
    getTabItemsFromSlot,
    createTabItem,
    getTabItemDefaultSlotText,
    getRouteName,
    isTabItem,
    initPage,
});

defineExpose({
    module,
    parentRoute,
    pageClasses,
    hasIcon,
    hasIconOrIconSlot,
    pageColor,
    hasTabs,
    tabItems,
    defaultTab,
    mountedComponent,
    emitNewTab,
    getTabItemsFromSlot,
    createTabItem,
    getTabItemDefaultSlotText,
    getRouteName,
    isTabItem,
    initPage,
});
</script>

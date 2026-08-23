<template>
    <ct-block name="sw_page">
        <div class="ct-page" :class="pageClasses">
            <ct-block name="sw_page_head_area">
                <div v-if="showHeadArea" class="ct-page__head-area">
                    <ct-block name="sw_page_top_bar">
                        <div class="ct-page__top-bar">
                            <ct-block name="sw_page_search_bar">
                                <div v-if="showSearchBar" class="ct-page__search-bar">
                                    <slot name="search-bar">
                                        <ct-block name="sw_page_slot_search_bar">
                                            <ct-search-bar />
                                        </ct-block>
                                    </slot>
                                </div>
                            </ct-block>

                            <ct-block name="sw_page_top_bar_actions">
                                <div class="ct-page__top-bar-actions">
                                    <div class="ct-page__sidebar-container">
                                        <ct-help-center-v2 />
                                    </div>

                                    <ct-block name="sw_page_notification_center">
                                        <div class="ct-page__sidebar-container">
                                            <ct-notification-center />
                                        </div>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </div>
                    </ct-block>

                    <ct-block name="sw_page_smart_bar">
                        <div v-if="showSmartBar" class="ct-page__smart-bar">
                            <ct-block name="sw_page_smart_bar_divider">
                                <div v-if="showSearchBar" class="ct-page__smart-bar-divider"></div>
                            </ct-block>

                            <ct-block name="sw_page_smart_bar_back_btn">
                                <div class="ct-page__back-btn-container">
                                    <slot name="smart-bar-back">
                                        <ct-block name="sw_page_slot_smart_bar_back">
                                            <router-link
                                                v-if="parentRoute"
                                                v-slot="{ href, navigate }"
                                                :to="routerBack"
                                                custom
                                            >
                                                <mt-button
                                                    is="a"
                                                    class="smart-bar__back-btn"
                                                    variant="secondary"
                                                    size="default"
                                                    square
                                                    :href="href"
                                                    :aria-label="$t('global.ct-page.backButton')"
                                                    :title="$t('global.ct-page.backButton')"
                                                    @click="navigate"
                                                >
                                                    <mt-icon name="solid-long-arrow-left" size="12px" />
                                                </mt-button>
                                            </router-link>
                                        </ct-block>
                                    </slot>
                                </div>
                            </ct-block>

                            <ct-block name="sw_page_smart_bar_content">
                                <div class="smart-bar__content">
                                    <ct-block name="sw_page_smart_bar_content_header">
                                        <div class="smart-bar__header">
                                            <slot name="smart-bar-header">
                                                <ct-block name="sw_page_slot_smart_bar_header">
                                                    <h2 v-if="module && module.title">
                                                        {{ $t(module.title) }}
                                                    </h2>
                                                </ct-block>
                                            </slot>
                                        </div>
                                    </ct-block>

                                    <ct-block name="sw_page_smart_bar_content_language_switch">
                                        <div class="smart-bar__language-switch">
                                            <slot name="language-switch">
                                                <ct-block name="sw_page_slot_language_switch"></ct-block>
                                            </slot>
                                        </div>
                                    </ct-block>

                                    <ct-block name="sw_page_smart_bar_content_actions">
                                        <div class="smart-bar__actions">
                                            <slot name="smart-bar-actions">
                                                <ct-block name="sw_page_slot_smart_bar_actions"></ct-block>
                                            </slot>
                                        </div>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </div>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="sw_page_content">
                <div ref="swPageContent" class="ct-page__content" :class="pageContentClasses">
                    <div v-if="hasSideContentSlot" class="ct-page__side-content">
                        <div class="ct-page__side-content-inner">
                            <slot name="side-content">
                                <ct-block name="sw_page_slot_side_content"></ct-block>
                            </slot>
                        </div>
                    </div>

                    <main id="main" class="ct-page__main-content" tabindex="-1">
                        <div class="ct-page__main-content-inner" v-bind="$attrs">
                            <slot name="content">
                                <ct-block name="sw_page_slot_content"></ct-block>
                            </slot>
                        </div>
                    </main>

                    <ct-block name="sw_page_content_sidebar">
                        <div v-if="hasSidebarSlot" class="ct-page__sidebar">
                            <slot name="sidebar">
                                <ct-block name="sw_page_sidebar_slot"></ct-block>
                            </slot>
                        </div>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-page.scss';
const { dom } = Contena.Utils;

const props = defineProps({
    /**
     * Toggles smart bar
     */
    showSmartBar: {
        type: Boolean,
        default: true,
    },
    /**
     * Toggles search bar
     */
    showSearchBar: {
        type: Boolean,
        default: true,
    },
    /**
     * Overrides the module color as the bottom-border-color of the page's smart bar
     */
    headerBorderColor: {
        type: String,
        required: false,
        default: '',
    },
});

import { ref, computed, provide, useSlots, onMounted, onUpdated, onBeforeUnmount } from 'vue';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();
const slots = useSlots();

const module = ref(null);
const parentRoute = ref(null);
const previousPath = ref(null);
const previousRoute = ref(null);
const sidebarOffset = ref(0);
const scrollbarOffset = ref(0);
const hasFullWidthHeader = ref(false);
const languageId = ref('');

const routerBack = computed(() => {
    if (previousPath.value && previousRoute.value === parentRoute.value) {
        return previousPath.value;
    }

    return {
        name: parentRoute.value,
    };
});
const pageColor = computed(() => {
    if (props.headerBorderColor) {
        return props.headerBorderColor;
    }

    if (module.value?.color) {
        return module.value.color;
    }

    return '#d8dde6';
});
const hasSideContentSlot = computed(() => {
    return !!slots['side-content'];
});
const hasSidebarSlot = computed(() => {
    return !!slots.sidebar;
});
const showHeadArea = computed(() => {
    return props.showSearchBar || props.showSmartBar;
});
const pageClasses = computed(() => {
    return {
        'has--head-area': showHeadArea.value,
        'has--search-bar': props.showSearchBar,
    };
});
const pageContainerClasses = computed(() => {
    return {
        'has--smart-bar': props.showSmartBar,
    };
});
const pageContentClasses = computed(() => {
    return {
        'has--smart-bar': !!props.showSmartBar,
        'has--side-content': !!hasSideContentSlot.value,
        'has--side-bar ': !!hasSidebarSlot.value && !hasSideContentSlot.value,
    };
});
const pageOffset = computed(() => {
    if (hasFullWidthHeader.value) {
        return 0;
    }
    return `${sidebarOffset.value + scrollbarOffset.value}px`;
});
const createdComponent = () => {
    window.addEventListener('resize', readScreenWidth);
};
const mountedComponent = () => {
    initPage();
    readScreenWidth();
    setScrollbarOffset();
};
const updatedComponent = () => {
    setScrollbarOffset();
};
const beforeDestroyComponent = () => {
    window.removeEventListener('resize', readScreenWidth);
};
function readScreenWidth() {
    hasFullWidthHeader.value = document.body.clientWidth <= 500;
}
const setSidebarOffset = (sidebarWidth) => {
    sidebarOffset.value = sidebarWidth;
};
const removeSidebarOffset = () => {
    sidebarOffset.value = 0;
};
function setScrollbarOffset() {
    let contentEl = document.querySelector('.ct-card-view__content');
    if (!contentEl) {
        contentEl = document.querySelector('.ct-page__main-content-inner');
    }
    if (contentEl !== null) {
        scrollbarOffset.value = dom.getScrollbarWidth(contentEl);
    }
}
function initPage() {
    if (route.meta.$module) {
        module.value = route.meta.$module;
    }
    if (route.meta.parentPath) {
        parentRoute.value = route.meta.parentPath;
    }
    previousPath.value = router.options?.history?.state?.back;
    if (previousPath.value) {
        previousRoute.value = router.resolve({
            path: previousPath.value,
        }).name;
    }
}

createdComponent();

onMounted(() => {
    mountedComponent();
});
onUpdated(() => {
    updatedComponent();
});
onBeforeUnmount(() => {
    Contena.Store.get('error').resetApiErrors();
    beforeDestroyComponent();
});

swDefinePublic({
    module,
    parentRoute,
    previousPath,
    previousRoute,
    sidebarOffset,
    scrollbarOffset,
    hasFullWidthHeader,
    languageId,
    routerBack,
    pageColor,
    hasSideContentSlot,
    hasSidebarSlot,
    showHeadArea,
    pageClasses,
    pageContainerClasses,
    pageContentClasses,
    pageOffset,
    createdComponent,
    mountedComponent,
    updatedComponent,
    beforeDestroyComponent,
    readScreenWidth,
    setSidebarOffset,
    removeSidebarOffset,
    setScrollbarOffset,
    initPage,
});

provide('setSwPageSidebarOffset', setSidebarOffset);
provide('removeSwPageSidebarOffset', removeSidebarOffset);

defineExpose({
    module,
    parentRoute,
    previousPath,
    previousRoute,
    sidebarOffset,
    scrollbarOffset,
    hasFullWidthHeader,
    languageId,
    routerBack,
    pageColor,
    hasSideContentSlot,
    hasSidebarSlot,
    showHeadArea,
    pageClasses,
    pageContainerClasses,
    pageContentClasses,
    pageOffset,
    createdComponent,
    mountedComponent,
    updatedComponent,
    beforeDestroyComponent,
    readScreenWidth,
    setSidebarOffset,
    removeSidebarOffset,
    setScrollbarOffset,
    initPage,
});
</script>

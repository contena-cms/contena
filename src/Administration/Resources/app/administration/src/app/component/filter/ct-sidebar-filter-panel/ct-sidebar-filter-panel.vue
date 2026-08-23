<template>
    <ct-block name="sw_sidebar_filter_panel">
        <ct-sidebar-item
            class="ct-sidebar-filter-panel"
            icon="regular-filter"
            :badge="activeFilterNumber"
            :title="$t('ct-filter-panel.titleSidebarItemFilter')"
        >
            <template #headline-content>
                <ct-block name="sw_sidebar_filter_panel_headline">
                    <a v-if="activeFilterNumber" role="button" tabindex="0" @click="resetAll" @keydown.enter="resetAll">{{
                        $t('ct-sidebar-filter-panel.resetButton')
                    }}</a>
                </ct-block>
            </template>

            <ct-block name="sw_sidebar_filter_panel_content">
                <ct-filter-panel ref="filterPanel" v-bind="$attrs" />
            </ct-block>
        </ct-sidebar-item>
    </ct-block>
</template>

<script setup>
import './ct-sidebar-filter-panel.scss';

defineProps({
    activeFilterNumber: {
        type: Number,
        required: true,
    },
});

import { ref, inject, provide, unref } from 'vue';

const filterPanel = ref(null);

const parentRegisterSidebarItem = inject('registerSidebarItem', null);

const filterSidebarItem = ref(null);

const registerSidebarItem = (sidebarItem) => {
    filterSidebarItem.value = sidebarItem;
    parentRegisterSidebarItem?.(sidebarItem);
};
const openFilterPanel = () => {
    if (!filterSidebarItem.value?.openContent) {
        return;
    }

    filterSidebarItem.value.openContent();
};
const resetAll = () => {
    filterPanel.value.resetAll();
};

swDefinePublic({
    parentRegisterSidebarItem,
    filterSidebarItem,
    registerSidebarItem,
    openFilterPanel,
    resetAll,
});

provide('registerSidebarItem', unref(registerSidebarItem));

defineExpose({
    parentRegisterSidebarItem,
    filterSidebarItem,
    registerSidebarItem,
    openFilterPanel,
    resetAll,
});
</script>

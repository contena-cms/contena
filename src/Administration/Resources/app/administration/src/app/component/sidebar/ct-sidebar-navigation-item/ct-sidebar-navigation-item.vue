<template>
    <ct-block name="ct_sidebar_navigation_item">
        <button
            v-tooltip.left="{ message: tooltipContent }"
            class="ct-sidebar-navigation-item"
            :class="sidebarItem.sidebarItemClasses"
            :aria-label="sidebarItem.title"
            :disabled="sidebarItem.disabled"
            @click="emitButtonClicked"
        >
            <ct-block name="ct_sidebar_navigation_item_content">
                <mt-icon :name="sidebarItem.icon" size="20px" />

                <i
                    v-if="!sidebarItem.hasSimpleBadge && sidebarItem.badge > 0"
                    class="sidebar-item-badge notification--badge"
                    :class="badgeTypeClasses"
                >
                    {{ sidebarItem.badge }}
                </i>

                <i
                    v-else-if="sidebarItem.hasSimpleBadge"
                    class="sidebar-item-badge dot--badge"
                    :class="badgeTypeClasses"
                ></i>
            </ct-block>
        </button>
    </ct-block>
</template>

<script setup>
import './ct-sidebar-navigation-item.scss';

const props = defineProps({
    sidebarItem: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(['item-click']);

import { computed } from 'vue';

const badgeTypeClasses = computed(() => {
    return [
        `is--${props.sidebarItem.badgeType}`,
    ];
});
const tooltipContent = computed(() => props.sidebarItem.title);

const emitButtonClicked = () => {
    emit('item-click', props.sidebarItem);
};

ctDefinePublic({
    badgeTypeClasses,
    tooltipContent,
    emitButtonClicked,
});

defineExpose({
    badgeTypeClasses,
    tooltipContent,
    emitButtonClicked,
});
</script>

<template>
    <ct-block name="sw_sidebar">
        <aside ref="sidebar" class="ct-sidebar" :class="sidebarClasses">
            <ct-block name="sw_sidebar_navigation">
                <nav class="ct-sidebar__navigation">
                    <ct-block name="sw_sidebar_navigation_list">
                        <ul
                            v-for="(section, index) in sections"
                            :key="`sidebar-section-${index}`"
                            class="sw_sidebar__navigation-list"
                            :class="`is--${index}`"
                        >
                            <li v-for="item in section" :key="`${item.id ?? item.title}-${resizeNavigationKey}`">
                                <ct-block name="sw_sidebar_navigation_item">
                                    <ct-sidebar-navigation-item :sidebar-item="item" @item-click="setItemActive(item)" />
                                </ct-block>
                            </li>
                        </ul>
                    </ct-block>
                </nav>
            </ct-block>

            <ct-block name="sw_sidebar_content">
                <div class="ct-sidebar__content">
                    <slot> </slot>
                </div>
            </ct-block>
        </aside>
    </ct-block>
</template>

<script setup>
import './ct-sidebar.scss';

const props = defineProps({
    propagateWidth: {
        type: Boolean,
        required: false,
        default: false,
    },
});
const emit = defineEmits([
    'item-click',
    'item-register',
]);

import { ref, computed, inject, getCurrentInstance, provide, unref, onMounted, onBeforeUnmount } from 'vue';

const sidebar = ref(null);

const instance = getCurrentInstance();
const device = instance?.proxy?.$device;
const setSwPageSidebarOffset = inject('setSwPageSidebarOffset');
const removeSwPageSidebarOffset = inject('removeSwPageSidebarOffset');

const items = ref([]);
const isOpened = ref(false);
const resizeNavigationKey = ref(0);

const sections = computed(() => {
    const sections = {};
    items.value.forEach((item) => {
        if (!sections[item.position]) {
            sections[item.position] = [];
        }
        sections[item.position].push(item);
    });

    return sections;
});
const sidebarClasses = computed(() => {
    return {
        'is--opened': isOpened.value,
    };
});

const mountedComponent = () => {
    device?.onResize({
        listener: onResize,
        component: instance?.proxy,
    });

    if (props.propagateWidth) {
        updateSidebarOffset();
    }
};
const destroyedComponent = () => {
    device?.removeResizeListener(instance?.proxy);

    if (props.propagateWidth) {
        removeSwPageSidebarOffset?.();
    }
};
const isItemRegistered = (itemToCheck) => {
    const index = items.value.findIndex((item) => {
        return item === itemToCheck;
    });
    return index > -1;
};
const isAnyItemActive = () => {
    const index = items.value.findIndex((item) => {
        return item.isActive;
    });
    return index > -1;
};
const closeSidebar = () => {
    isOpened.value = false;
};
function onResize() {
    resizeNavigationKey.value += 1;
    if (props.propagateWidth) {
        updateSidebarOffset();
    }
}
function updateSidebarOffset() {
    const sidebarWidth = sidebar.value?.querySelector('.ct-sidebar__navigation')?.offsetWidth;
    if (!sidebarWidth) {
        return;
    }
    setSwPageSidebarOffset?.(sidebarWidth);
}
const registerSidebarItem = (item) => {
    if (isItemRegistered(item)) {
        return;
    }

    items.value.push(item);

    item.registerToggleActiveListener(setItemActive);
    item.registerCloseContentListener(closeSidebar);

    emit('item-register', item);
};
function setItemActive(clickedItem) {
    emit('item-click', clickedItem);
    items.value.forEach((item) => {
        if (item.sidebarButtonClick) {
            item.sidebarButtonClick(clickedItem);
        }
    });
    if (clickedItem.hasDefaultSlot) {
        isOpened.value = isAnyItemActive();
    }
}

provide('registerSidebarItem', unref(registerSidebarItem));

onMounted(mountedComponent);
onBeforeUnmount(destroyedComponent);

swDefinePublic({
    setSwPageSidebarOffset,
    removeSwPageSidebarOffset,
    items,
    isOpened,
    resizeNavigationKey,
    sections,
    sidebarClasses,
    mountedComponent,
    destroyedComponent,
    isItemRegistered,
    isAnyItemActive,
    closeSidebar,
    onResize,
    updateSidebarOffset,
    registerSidebarItem,
    setItemActive,
});

defineExpose({
    setSwPageSidebarOffset,
    removeSwPageSidebarOffset,
    items,
    isOpened,
    resizeNavigationKey,
    sections,
    sidebarClasses,
    mountedComponent,
    destroyedComponent,
    isItemRegistered,
    isAnyItemActive,
    closeSidebar,
    onResize,
    updateSidebarOffset,
    registerSidebarItem,
    setItemActive,
});
</script>

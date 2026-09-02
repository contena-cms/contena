<template>
    <ct-block name="ct_sidebar_item">
        <div v-if="showContent" class="ct-sidebar-item">
            <ct-block name="ct_sidebar_item_headline">
                <div class="ct-sidebar-item__headline">
                    <ct-block name="ct_sidebar_item_title">
                        <h3 class="ct-sidebar-item__title">
                            {{ title }}
                        </h3>
                    </ct-block>

                    <slot name="headline-content">
                        <ct-block name="ct_sidebar_item_headline_slot"></ct-block>
                    </slot>

                    <ct-block name="ct_sidebar_item_close_button">
                        <button
                            class="ct-sidebar-item__close-button"
                            :aria-label="$t('ct-sidebar.ariaLabelButtonClose')"
                            @click="closeContent"
                        >
                            <mt-icon name="regular-times-xs" />
                        </button>
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_sidebar_item_content">
                <div class="ct-sidebar-item__content">
                    <div class="ct-sidebar-item__scrollable-container">
                        <slot>
                            <ct-block name="ct_sidebar_item_default_slot"></ct-block>
                        </slot>
                    </div>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-sidebar-item.scss';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },

    icon: {
        type: String,
        required: true,
    },

    disabled: {
        type: Boolean,
        required: false,
        default: false,
    },

    position: {
        type: String,
        required: false,
        default: 'top',
        validator(value) {
            return [
                'top',
                'bottom',
            ].includes(value);
        },
    },

    badge: {
        type: Number,
        required: false,
        default: 0,
    },

    hasSimpleBadge: {
        type: Boolean,
        required: false,
        default: false,
    },

    badgeType: {
        type: String,
        required: false,
        default: 'info',
        validator(value) {
            return [
                'info',
                'warning',
                'error',
                'success',
            ].includes(value);
        },
    },
});
const emit = defineEmits([
    'toggle-active',
    'close-content',
    'click',
]);

import { ref, computed, inject, markRaw, useSlots, watch } from 'vue';

const slots = useSlots();

const registerSidebarItem = inject('registerSidebarItem', null);

const isActive = ref(false);
const toggleActiveListener = ref([]);
const closeContentListener = ref([]);

const sidebarItemClasses = computed(() => {
    return {
        'is--active': showContent.value,
        'is--disabled': props.disabled,
    };
});
const hasDefaultSlot = computed(() => {
    return !!slots.default;
});
const showContent = computed(() => {
    return hasDefaultSlot.value && isActive.value;
});

const registerToggleActiveListener = (listener) => {
    toggleActiveListener.value.push(listener);
};
const registerCloseContentListener = (listener) => {
    closeContentListener.value.push(listener);
};
const sidebarItem = markRaw({
    get isActive() {
        return isActive.value;
    },
    get hasDefaultSlot() {
        return hasDefaultSlot.value;
    },
    get position() {
        return props.position;
    },
    get title() {
        return props.title;
    },
    get icon() {
        return props.icon;
    },
    get disabled() {
        return props.disabled;
    },
    get badge() {
        return props.badge;
    },
    get hasSimpleBadge() {
        return props.hasSimpleBadge;
    },
    get badgeType() {
        return props.badgeType;
    },
    get sidebarItemClasses() {
        return sidebarItemClasses.value;
    },
    registerToggleActiveListener,
    registerCloseContentListener,
    openContent: () => openContent(),
    closeContent: () => closeContent(),
    sidebarButtonClick: (clickedItem) => sidebarButtonClick(clickedItem),
});
function openContent() {
    if (showContent.value || props.disabled) {
        return;
    }
    emit('toggle-active', sidebarItem);
    toggleActiveListener.value.forEach((listener) => listener(sidebarItem));
}
function closeContent() {
    if (!isActive.value) {
        return;
    }
    isActive.value = false;
    emit('close-content');
    closeContentListener.value.forEach((listener) => listener(sidebarItem));
}
function sidebarButtonClick(clickedItem) {
    if (clickedItem === sidebarItem) {
        isActive.value = !isActive.value;
        emit('click');
        return;
    }
    if (clickedItem.hasDefaultSlot) {
        isActive.value = false;
    }
}

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            closeContent();
        }
    },
);

registerSidebarItem?.(sidebarItem);

ctDefinePublic({
    registerSidebarItem,
    isActive,
    toggleActiveListener,
    closeContentListener,
    sidebarItemClasses,
    hasDefaultSlot,
    showContent,
    openContent,
    closeContent,
    sidebarButtonClick,
    registerToggleActiveListener,
    registerCloseContentListener,
});

defineExpose({
    registerSidebarItem,
    isActive,
    toggleActiveListener,
    closeContentListener,
    sidebarItemClasses,
    hasDefaultSlot,
    showContent,
    openContent,
    closeContent,
    sidebarButtonClick,
    registerToggleActiveListener,
    registerCloseContentListener,
});
</script>

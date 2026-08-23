<template>
    <ct-block name="sw_notification_center">
        <mt-floating-ui class="ct-notification-center__popover" :is-opened="isOpened" :offset="8" @close="onPanelClose">
            <template #trigger>
                <ct-block name="sw_notification_center_context_button_button_slot">
                    <mt-button
                        class="ct-notification-center__context-button"
                        :class="[additionalContextButtonClass, { 'is--active': isOpened }]"
                        variant="tertiary"
                        square
                        size="default"
                        :aria-label="$t('global.notification-center.triggerAriaLabel')"
                        @click="togglePanel"
                    >
                        <mt-icon name="bell" size="var(--scale-size-20)" />
                    </mt-button>
                </ct-block>
            </template>

            <template #default>
                <div class="ct-notification-center__content">
                    <ct-block name="sw_notification_center_content">
                        <ct-block name="sw_notification_center_content_header">
                            <div class="ct-notification-center__header">
                                <h3 class="ct-notification-center__title">
                                    {{ $t('global.notification-center.title') }}
                                </h3>

                                <ct-block name="sw_notification_center_content_context_menu">
                                    <mt-dropdown-menu-root :open="optionsMenuOpen" @update:open="optionsMenuOpen = $event">
                                        <mt-dropdown-menu-trigger as-child>
                                            <mt-button
                                                class="ct-notification-center__options-button"
                                                variant="tertiary"
                                                square
                                                :disabled="!hasNotifications"
                                                :aria-label="$t('global.notification-center.optionsAriaLabel')"
                                            >
                                                <mt-icon name="ellipsis-h" size="var(--scale-size-16)" />
                                            </mt-button>
                                        </mt-dropdown-menu-trigger>

                                        <mt-dropdown-menu-portal>
                                            <mt-action-menu class="ct-notification-center__options-menu">
                                                <mt-action-menu-item
                                                    variant="critical"
                                                    icon="trash"
                                                    @select="openDeleteModal"
                                                >
                                                    {{ $t('global.notification-center.deleteModal.title') }}
                                                </mt-action-menu-item>
                                            </mt-action-menu>
                                        </mt-dropdown-menu-portal>
                                    </mt-dropdown-menu-root>
                                </ct-block>
                            </div>
                        </ct-block>

                        <ct-block name="sw_notification_center_content_notification_container">
                            <div class="ct-notification-center__notification-container">
                                <div v-show="notifications.length > 0">
                                    <ct-notification-center-item
                                        v-for="notification in notifications"
                                        :key="notification.uuid"
                                        :notification="notification"
                                        @center-close="changeVisibility(false)"
                                    />
                                </div>

                                <div
                                    v-show="notifications.length === 0"
                                    class="ct-notification-center__empty-state-wrapper"
                                    @animationend="isBellRinging = false"
                                >
                                    <mt-empty-state
                                        class="ct-notification-center__empty-state"
                                        :class="{ 'ct-notification-center__empty-state--ringing': isBellRinging }"
                                        :headline="$t('global.notification-center.emptyText')"
                                        :description="$t('global.notification-center.emptyDescription')"
                                        icon="regular-bell"
                                        role="status"
                                        centered
                                    />

                                    <button
                                        type="button"
                                        class="ct-notification-center__empty-state-bell-button"
                                        :aria-label="$t('global.notification-center.ringBellLabel')"
                                        @mousedown.prevent
                                        @click.stop="onEmptyStateBellClick"
                                    ></button>
                                </div>
                            </div>
                        </ct-block>
                    </ct-block>
                </div>
            </template>
        </mt-floating-ui>

        <ct-block name="sw_notification_center_delete_modal">
            <ct-modal
                v-if="showDeleteModal"
                :title="$t('global.notification-center.deleteModal.title')"
                variant="small"
                @modal-close="onCloseDeleteModal"
            >
                <ct-block name="sw_notification_center_delete_modal_confirm_delete_text">
                    <p class="sw_notification_center__confirm-delete-text">
                        {{ $t('global.notification-center.deleteModal.textConfirm') }}
                    </p>
                </ct-block>

                <template #modal-footer>
                    <ct-block name="sw_notification_center_delete_modal_footer">
                        <ct-block name="sw_notification_center_delete_modal_cancel">
                            <mt-button size="small" variant="secondary" @click="onCloseDeleteModal">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                        </ct-block>

                        <ct-block name="sw_notification_center_delete_modal_confirm">
                            <mt-button variant="critical" size="small" @click="onConfirmDelete">
                                {{ $t('global.default.delete') }}
                            </mt-button>
                        </ct-block>
                    </ct-block>
                </template>
            </ct-modal>
        </ct-block>
    </ct-block>
</template>

<script setup>
import './ct-notification-center.scss';

defineProps({});

import { ref, computed, inject, watch, onBeforeUnmount } from 'vue';
import { useNotification } from 'src/app/composables/use-notification';

const { createSystemNotificationError } = useNotification();

const feature = inject('feature');

const isOpened = ref(false);
const optionsMenuOpen = ref(false);
const isBellRinging = ref(false);
const showDeleteModal = ref(false);
const unsubscribeFromStore = ref(null);

const notifications = computed(() => {
    return Object.values(Contena.Store.get('notification').notifications).reverse();
});
const hasNotifications = computed(() => {
    return notifications.value.length > 0;
});
const additionalContextButtonClass = computed(() => {
    return {
        'ct-notification-center__context-button--new-available': notifications.value.some((n) => !n.visited),
    };
});

const onVisibilityChange = (isOpened) => {
    const store = Contena.Store.get('notification');

    if (!isOpened) {
        store.setAllNotificationsVisited();
    }
};
const openDeleteModal = () => {
    optionsMenuOpen.value = false;
    isOpened.value = false;
    showDeleteModal.value = true;
};
const onConfirmDelete = () => {
    Contena.Store.get('notification').clearNotificationsForCurrentUser();
    showDeleteModal.value = false;
};
const onCloseDeleteModal = () => {
    showDeleteModal.value = false;
};
const onEmptyStateBellClick = () => {
    isBellRinging.value = true;
};
const togglePanel = () => {
    changeVisibility(!isOpened.value);
};
const onPanelClose = () => {
    if (optionsMenuOpen.value) {
        return;
    }

    changeVisibility(false);
};
function changeVisibility(visible) {
    isOpened.value = visible;
    if (!visible) {
        showDeleteModal.value = false;
        optionsMenuOpen.value = false;
    }
}
const createNotificationFromSystemError = ({ name, args }) => {
    if (name !== 'addSystemError') {
        return;
    }

    createSystemNotificationError({
        id: args.id,
        message: args.error.detail,
    });
};

watch(
    () => isOpened.value,
    (value) => {
        onVisibilityChange(value);
    },
);

unsubscribeFromStore.value = Contena.Store.get('notification').$onAction(createNotificationFromSystemError);
Contena.Utils.EventBus.on('on-change-notification-center-visibility', changeVisibility);

onBeforeUnmount(() => {
    unsubscribeFromStore.value?.();

    Contena.Utils.EventBus.off('on-change-notification-center-visibility', changeVisibility);
});

swDefinePublic({
    feature,
    isOpened,
    optionsMenuOpen,
    isBellRinging,
    showDeleteModal,
    unsubscribeFromStore,
    notifications,
    hasNotifications,
    additionalContextButtonClass,
    onVisibilityChange,
    openDeleteModal,
    onConfirmDelete,
    onCloseDeleteModal,
    onEmptyStateBellClick,
    togglePanel,
    onPanelClose,
    changeVisibility,
    createNotificationFromSystemError,
});

defineExpose({
    feature,
    isOpened,
    optionsMenuOpen,
    isBellRinging,
    showDeleteModal,
    unsubscribeFromStore,
    notifications,
    hasNotifications,
    additionalContextButtonClass,
    onVisibilityChange,
    openDeleteModal,
    onConfirmDelete,
    onCloseDeleteModal,
    onEmptyStateBellClick,
    togglePanel,
    onPanelClose,
    changeVisibility,
    createNotificationFromSystemError,
});
</script>

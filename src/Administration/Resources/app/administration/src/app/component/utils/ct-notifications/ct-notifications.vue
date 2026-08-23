<template>
    <ct-block name="sw_notifications">
        <transition name="ct-notifications-slide-fade">
            <ct-block name="sw_notifications_element">
                <div v-if="notifications.length" class="ct-notifications" :style="notificationsStyle">
                    <ct-block name="sw_notifications_transition_group">
                        <transition-group name="ct-notifications-slide-fade">
                            <ct-block name="sw_notifications_item">
                                <mt-banner
                                    v-for="(notification, index) in notifications"
                                    :key="notification.uuid"
                                    :class="['ct-notifications__notification--' + index, 'ct-notification__alert']"
                                    :title="getTranslatedTitle(notification)"
                                    :variant="getNotificationVariant(notification)"
                                    :notification-index="notification.uuid"
                                    :closable="true"
                                    @close="onClose(notification)"
                                >
                                    <ct-block name="sw_notifications_item_content">
                                        <div
                                            class="ct-notifications__message"
                                            v-html="getTranslatedMessage(notification)"
                                        ></div>
                                    </ct-block>

                                    <!-- TODO: Implement buttons and add tests for action buttons -->
                                    <div v-if="notification.actions.length" class="ct-notifications__actions">
                                        <ct-block name="sw_notifications_item_actions">
                                            <template v-for="action in notification.actions" :key="action.label">
                                                <ct-block name="sw_notifications_item_action_item">
                                                    <mt-button
                                                        :disabled="action.disabled"
                                                        variant="secondary"
                                                        @click="handleAction(action, notification)"
                                                    >
                                                        {{ action.label }}
                                                    </mt-button>
                                                </ct-block>
                                            </template>
                                        </ct-block>
                                    </div>
                                </mt-banner>
                            </ct-block>
                        </transition-group>
                    </ct-block>
                </div>
            </ct-block>
        </transition>
    </ct-block>
</template>

<script setup>
import './ct-notifications.scss';

const props = defineProps({
    position: {
        type: String,
        required: false,
        default: 'topRight',
        validator(value) {
            if (!value.length) {
                return true;
            }
            return [
                'topRight',
                'bottomRight',
            ].includes(value);
        },
    },
    notificationsGap: {
        type: String,
        default: '20px',
    },
    notificationsTopGap: {
        type: String,
        default: '165px',
    },
});

import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationTranslation } from 'src/app/composables/use-notification-translation';

const router = useRouter();
const { getTranslatedTitle, getTranslatedMessage } = useNotificationTranslation();

const notifications = computed(() => {
    return Object.values(Contena.Store.get('notification').growlNotifications);
});
const notificationsStyle = computed(() => {
    let notificationsGap = props.notificationsGap;

    if (`${parseInt(notificationsGap, 10)}` === notificationsGap) {
        notificationsGap = `${notificationsGap}px`;
    }

    if (props.position === 'bottomRight') {
        return {
            top: 'auto',
            right: notificationsGap,
            bottom: notificationsGap,
            left: 'auto',
        };
    }

    return {
        top: props.notificationsTopGap,
        right: notificationsGap,
        bottom: 'auto',
        left: 'auto',
    };
});

const onClose = (notification) => {
    Contena.Store.get('notification').removeGrowlNotification(notification);
};
const handleAction = (action, notification) => {
    // Allow external links for example to the contena account or store
    if (Contena.Utils.string.isUrl(action.route)) {
        window.open(action.route);
        return;
    }

    if (action.route) {
        void router.push(action.route);
    }

    if (action.method && typeof action.method === 'function') {
        action.method.call();
    }

    onClose(notification);
};
const getNotificationVariant = (notification) => {
    // If notification has a correct new variant, return it
    if (
        [
            'info',
            'critical',
            'positive',
            'attention',
            'neutral',
        ].includes(notification.variant)
    ) {
        return notification.variant;
    }

    if (notification.variant === 'info') {
        return 'info';
    }

    if (notification.variant === 'error') {
        return 'critical';
    }

    if (notification.variant === 'success') {
        return 'positive';
    }

    if (notification.variant === 'warning') {
        return 'attention';
    }

    return 'neutral';
};

swDefinePublic({
    notifications,
    notificationsStyle,
    onClose,
    handleAction,
    getNotificationVariant,
});

defineExpose({
    getTranslatedTitle,
    getTranslatedMessage,
    notifications,
    notificationsStyle,
    onClose,
    handleAction,
    getNotificationVariant,
});
</script>

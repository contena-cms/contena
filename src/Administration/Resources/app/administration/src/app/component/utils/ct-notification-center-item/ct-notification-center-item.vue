<template>
    <ct-block name="ct_notification_center_item">
        <div class="ct-notification-center-item">
            <ct-block name="ct_notification_center_item_header">
                <div class="ct-notification-center-item__header" :class="itemHeaderClass">
                    <div class="ct-notification-center-item__header-left">
                        <div v-if="!notification.visited" class="ct-notification-center-item__header-dot"></div>

                        <ct-block name="ct_notification_center_item_header_title">
                            <p class="ct-notification-center-item__title">
                                {{ getTranslatedTitle(notification) }}
                            </p>
                        </ct-block>
                    </div>

                    <div class="ct-notification-center-item__header-right">
                        <ct-block name="ct_notification_center_item_header_timestamp">
                            <p class="ct-notification-center-item__timestamp">
                                <ct-time-ago
                                    :date="notification.timestamp"
                                    :date-time-format="{ month: '2-digit', day: '2-digit' }"
                                />
                            </p>
                        </ct-block>

                        <ct-block name="ct_notification_center_item_header_delete">
                            <mt-icon
                                class="ct-notification-center-item__delete"
                                name="regular-times-s"
                                size="12px"
                                @click.stop="onDelete"
                            />
                        </ct-block>
                    </div>
                </div>
            </ct-block>

            <ct-block name="ct_notification_center_item_content">
                <div class="ct-notification-center-item__content">
                    <ct-block name="ct_notification_center_item_content_message">
                        <p class="ct-notification-center-item__message" v-html="getTranslatedMessage(notification)"></p>
                    </ct-block>

                    <ct-block name="ct_notification_center_item_loader">
                        <!-- TODO Codemod: Converted from ct-loader - please check if everything works correctly -->
                        <mt-loader v-if="notification.isLoading" class="ct-notification-center-item__loader" size="12px" />
                    </ct-block>
                </div>
            </ct-block>

            <ct-block name="ct_notification_center_item_actions">
                <div v-if="notification.actions" class="ct-notification-center-item__actions">
                    <!-- eslint-disable ct-deprecation-rules/no-twigjs-blocks -->
                    <ct-block name="ct_notification_center_item_actions_inner">
                        <template v-for="action in notificationActions" :key="action.label">
                            <ct-block name="ct_notification_center_item_actions_item_container">
                                <div class="ct-notification-center-item__actions-item-container">
                                    <ct-block name="ct_notification_center_item_actions_item">
                                        <mt-button block size="small" variant="secondary" @click="handleAction(action)">
                                            {{ action.label }}
                                        </mt-button>
                                    </ct-block>
                                </div>
                            </ct-block>
                        </template>
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup>
import './ct-notification-center-item.scss';

const props = defineProps({
    notification: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(['center-close']);

import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationTranslation } from 'src/app/composables/use-notification-translation';

const router = useRouter();
const { getTranslatedTitle, getTranslatedMessage } = useNotificationTranslation();

const itemHeaderClass = computed(() => {
    return {
        'ct-notification-center-item__header--is-new': !props.notification.visited,
    };
});
const notificationActions = computed(() => {
    return props.notification.actions.filter((action) => {
        return action.route;
    });
});

const isNotificationFromSameDay = () => {
    const timestamp = props.notification.timestamp;
    const now = new Date();
    return (
        timestamp.getDate() === now.getDate() &&
        timestamp.getMonth() === now.getMonth() &&
        timestamp.getFullYear() === now.getFullYear()
    );
};
const onDelete = () => {
    Contena.Store.get('notification').removeNotification(props.notification);
};
const handleAction = (action) => {
    // Allow external links for example to the contena account or store
    if (Contena.Utils.string.isUrl(action.route)) {
        window.open(action.route);
        return;
    }

    void router.push(action.route);
    emit('center-close');
};

ctDefinePublic({
    itemHeaderClass,
    notificationActions,
    isNotificationFromSameDay,
    onDelete,
    handleAction,
});

defineExpose({
    getTranslatedTitle,
    getTranslatedMessage,
    itemHeaderClass,
    notificationActions,
    isNotificationFromSameDay,
    onDelete,
    handleAction,
});
</script>

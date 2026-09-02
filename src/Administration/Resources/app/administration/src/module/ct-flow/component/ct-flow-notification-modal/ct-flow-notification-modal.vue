<template>
    <ct-block name="ct_flow_notification_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal :title="$t('ct-flow.notification.title')" width="s">
                <ct-block name="ct_flow_notification_modal_content">
                    <div class="ct-flow-notification-modal__content">
                        <mt-select
                            v-model="draft.status"
                            required
                            :label="$t('ct-flow.notification.status')"
                            :options="statusOptions"
                        />
                        <mt-textarea
                            v-model="draft.message"
                            required
                            :label="$t('ct-flow.notification.message')"
                            :placeholder="$t('ct-flow.notification.messagePlaceholder')"
                        />
                        <mt-switch v-model="draft.adminOnly" :label="$t('ct-flow.notification.adminOnly')" />
                        <mt-textarea
                            v-model="requiredPrivileges"
                            :label="$t('ct-flow.notification.requiredPrivileges')"
                            :help-text="$t('ct-flow.notification.requiredPrivilegesHelp')"
                        />
                    </div>
                </ct-block>

                <template #footer>
                    <ct-block name="ct_flow_notification_modal_footer">
                        <div class="ct-flow-modal__footer-actions">
                            <mt-button variant="secondary" @click="onCancel">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                            <mt-button variant="primary" :disabled="!canSave || undefined" @click="onSave">
                                {{ $t('ct-flow.notification.save') }}
                            </mt-button>
                        </div>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';

const { cloneDeep } = Contena.Utils.object;

// eslint-disable-next-line ct-deprecation-rules/private-feature-declarations
export interface NotificationActionConfig extends Record<string, unknown> {
    status: 'info' | 'positive' | 'warning' | 'critical';
    message: string;
    adminOnly: boolean;
    requiredPrivileges: string[];
}

const props = defineProps({
    config: { type: Object as PropType<Record<string, unknown>>, required: true },
});
const emit = defineEmits<{ save: [config: NotificationActionConfig]; cancel: [] }>();
const { t } = useI18n();

const configuredStatus = props.config.status;
const configuredPrivileges = props.config.requiredPrivileges;
const draft = ref<NotificationActionConfig>({
    status:
        configuredStatus === 'positive' || configuredStatus === 'warning' || configuredStatus === 'critical'
            ? configuredStatus
            : 'info',
    message: typeof props.config.message === 'string' ? props.config.message : '',
    adminOnly: props.config.adminOnly === true,
    requiredPrivileges: Array.isArray(configuredPrivileges)
        ? configuredPrivileges.filter((privilege): privilege is string => typeof privilege === 'string')
        : [],
});
const requiredPrivileges = ref(draft.value.requiredPrivileges.join(', '));
const statusOptions = computed(() => [
    { label: t('ct-flow.notification.statusInfo'), value: 'info' },
    { label: t('global.default.success'), value: 'positive' },
    { label: t('global.default.warning'), value: 'warning' },
    { label: t('ct-flow.notification.statusCritical'), value: 'critical' },
]);
const canSave = computed(() => draft.value.message.trim().length > 0);
const onSave = (): void => {
    draft.value.message = draft.value.message.trim();
    draft.value.requiredPrivileges = requiredPrivileges.value
        .split(',')
        .map((privilege) => privilege.trim())
        .filter(Boolean);
    emit('save', cloneDeep(draft.value));
};
const onCancel = (): void => emit('cancel');
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) onCancel();
};

ctDefinePublic({
    draft,
    requiredPrivileges,
    statusOptions,
    canSave,
    onSave,
    onCancel,
    onModalChange,
});

defineExpose({ draft, requiredPrivileges, statusOptions, canSave, onSave, onCancel, onModalChange });
</script>

<style scoped>
.ct-flow-notification-modal__content {
    display: grid;
    gap: var(--scale-size-16, 16px);
}
</style>

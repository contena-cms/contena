<template>
    <ct-block name="sw_flow_user_status_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal :title="$t('ct-flow.userStatus.title')" width="s">
                <ct-block name="sw_flow_user_status_modal_content">
                    <div class="ct-flow-user-status-modal__content">
                        <mt-banner variant="attention">
                            {{ $t('ct-flow.userStatus.warning') }}
                        </mt-banner>
                        <mt-select
                            v-model="active"
                            required
                            :label="$t('ct-flow.userStatus.status')"
                            :options="statusOptions"
                        />
                    </div>
                </ct-block>
                <template #footer>
                    <ct-block name="sw_flow_user_status_modal_footer">
                        <div class="ct-flow-modal__footer-actions">
                            <mt-button variant="secondary" @click="onCancel">{{ $t('global.default.cancel') }}</mt-button>
                            <mt-button variant="primary" @click="onSave">{{ $t('global.default.save') }}</mt-button>
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

const props = defineProps({ config: { type: Object as PropType<Record<string, unknown>>, required: true } });
const emit = defineEmits<{ save: [config: Record<string, unknown>]; cancel: [] }>();
const { t } = useI18n();

const active = ref(props.config.active !== false);
const statusOptions = computed(() => [
    { label: t('ct-flow.userStatus.active'), value: true },
    { label: t('ct-flow.userStatus.inactive'), value: false },
]);
const onSave = (): void => emit('save', { active: active.value });
const onCancel = (): void => emit('cancel');
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) onCancel();
};

swDefinePublic({
    active,
    statusOptions,
    onSave,
    onCancel,
    onModalChange,
});

defineExpose({ active, statusOptions, onSave, onCancel, onModalChange });
</script>

<style scoped>
.ct-flow-user-status-modal__content {
    display: grid;
    gap: var(--scale-size-16, 16px);
}
</style>

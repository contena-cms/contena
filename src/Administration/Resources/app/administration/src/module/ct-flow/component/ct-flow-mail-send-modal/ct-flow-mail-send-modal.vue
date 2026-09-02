<template>
    <ct-block name="ct_flow_mail_send_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal :title="$t('ct-flow.mail.title')" width="s">
                <ct-block name="ct_flow_mail_send_modal_content">
                    <div class="ct-flow-mail-send-modal__content">
                        <mt-entity-select
                            v-model="draft.mailTemplateId"
                            entity="mail_template"
                            label-property="subject"
                            required
                            :label="$t('ct-flow.mail.template')"
                        />
                        <mt-select
                            v-model="draft.recipient.type"
                            :label="$t('ct-flow.mail.recipientType')"
                            :options="recipientOptions"
                        />
                        <mt-textarea
                            v-if="draft.recipient.type === 'custom'"
                            v-model="customRecipients"
                            :label="$t('ct-flow.mail.customRecipients')"
                            :help-text="$t('ct-flow.mail.customRecipientsHelp')"
                        />
                    </div>
                </ct-block>

                <template #footer>
                    <ct-block name="ct_flow_mail_send_modal_footer">
                        <div class="ct-flow-modal__footer-actions">
                            <mt-button variant="secondary" @click="onCancel">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                            <mt-button variant="primary" :disabled="!draft.mailTemplateId || undefined" @click="onSave">
                                {{ $t('ct-flow.mail.save') }}
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
export interface MailActionConfig extends Record<string, unknown> {
    mailTemplateId: string | null;
    recipient: { type: 'default' | 'admin' | 'custom'; data: Record<string, string | null> | never[] };
}

const props = defineProps({
    config: { type: Object as PropType<Record<string, unknown>>, required: true },
});
const emit = defineEmits<{ save: [config: MailActionConfig]; cancel: [] }>();
const { t } = useI18n();

const configuredRecipient = props.config.recipient;
const recipient =
    configuredRecipient !== null && typeof configuredRecipient === 'object'
        ? (configuredRecipient as MailActionConfig['recipient'])
        : { type: 'default' as const, data: [] as never[] };
const draft = ref<MailActionConfig>({
    mailTemplateId: typeof props.config.mailTemplateId === 'string' ? props.config.mailTemplateId : null,
    recipient: cloneDeep(recipient),
});
const initialData = draft.value.recipient.data;
const customRecipients = ref(Array.isArray(initialData) ? '' : Object.keys(initialData).join(', '));
const recipientOptions = computed(() => [
    { label: t('ct-flow.mail.recipientDefault'), value: 'default' },
    { label: t('ct-flow.mail.recipientAdmin'), value: 'admin' },
    { label: t('ct-flow.mail.recipientCustom'), value: 'custom' },
]);
const onSave = (): void => {
    if (draft.value.recipient.type === 'custom') {
        draft.value.recipient.data = Object.fromEntries(
            customRecipients.value
                .split(',')
                .map((email) => email.trim())
                .filter(Boolean)
                .map((email) => [
                    email,
                    null,
                ]),
        );
    } else {
        draft.value.recipient.data = [];
    }
    emit('save', cloneDeep(draft.value));
};
const onCancel = (): void => emit('cancel');
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) {
        onCancel();
    }
};

ctDefinePublic({
    draft,
    customRecipients,
    recipientOptions,
    onSave,
    onCancel,
    onModalChange,
});

defineExpose({ draft, customRecipients, recipientOptions, onSave, onCancel, onModalChange });
</script>

<style scoped>
.ct-flow-mail-send-modal__content {
    display: grid;
    gap: 16px;
}
</style>

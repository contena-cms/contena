<template>
    <ct-block name="sw_settings_logging_entry_info">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal :title="$t('ct-settings-logging.entryInfo.title')">
                <ct-block name="sw_settings_logging_entry_info_tabs">
                    <div>
                        <mt-tabs :items="tabItems" :default-item="activeTab" @new-item-active="onTabChange" />

                        <div class="ct-settings-logging-entry-info__content">
                            <ct-block name="sw_settings_logging_entry_info_content">
                                <ct-block name="sw_settings_logging_entry_info_raw_content">
                                    <mt-textarea v-if="activeTab === 'raw'" :model-value="displayString" />
                                </ct-block>

                                <template v-if="activeTab === 'html' || activeTab === 'plain'">
                                    <ct-block name="sw_settings_logging_mail_sent_content_recipients">
                                        <div class="ct-settings-logging-mail-sent-info__recipients">
                                            <ct-block name="sw_settings_logging_mail_sent_content_recipients_title">
                                                <span>
                                                    {{ $t('ct-settings-logging.mailInfo.recipientsTitle') }}:
                                                    {{ recipientString }}
                                                </span>
                                            </ct-block>
                                        </div>
                                    </ct-block>

                                    <ct-block name="sw_settings_logging_mail_sent_content_mailbody">
                                        <div>
                                            <ct-block name="sw_settings_logging_mail_sent_content_mailbody_title">
                                                <span>{{ $t('ct-settings-logging.mailInfo.contentsTitle') }}:</span>
                                            </ct-block>

                                            <ct-block name="sw_settings_logging_mail_sent_content_mailbody_html">
                                                <div
                                                    v-if="activeTab === 'html'"
                                                    class="ct-settings-logging-mail-sent-info__mail-content"
                                                    v-html="logEntry.context.additionalData.contents['text/html']"
                                                ></div>
                                            </ct-block>

                                            <ct-block name="sw_settings_logging_mail_sent_content_mailbody_plain">
                                                <div
                                                    v-if="activeTab === 'plain'"
                                                    class="ct-settings-logging-mail-sent-info__mail-content"
                                                >
                                                    {{ logEntry.context.additionalData.contents['text/plain'] }}
                                                </div>
                                            </ct-block>
                                        </div>
                                    </ct-block>
                                </template>
                            </ct-block>
                        </div>
                    </div>
                </ct-block>

                <template #footer>
                    <ct-block name="sw_settings_logging_entry_info_footer">
                        <ct-block name="sw_settings_logging_entry_info_close_button">
                            <mt-button size="small" variant="secondary" @click="onClose">
                                {{ $t('global.default.close') }}
                            </mt-button>
                        </ct-block>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, ref, type PropType } from 'vue';
import { useI18n } from 'vue-i18n';

import './ct-settings-logging-mail-sent-info.scss';

interface LogEntry {
    context?: Record<string, unknown>;
}

const props = defineProps({
    logEntry: {
        type: Object as PropType<LogEntry>,
        required: true,
    },
});
const emit = defineEmits<{
    close: [];
}>();

const i18n = useI18n();
const activeTab = ref('html');
const additionalData = computed(
    () =>
        (props.logEntry.context?.additionalData as
            | {
                  recipients?: Record<string, unknown>;
              }
            | undefined) ?? {},
);
const tabItems = computed(() => [
    { label: i18n.t('ct-settings-logging.mailInfo.tabHTML'), name: 'html' },
    { label: i18n.t('ct-settings-logging.mailInfo.tabPlain'), name: 'plain' },
    { label: i18n.t('ct-settings-logging.entryInfo.tabRaw'), name: 'raw' },
]);
const displayString = computed(() => (props.logEntry.context ? JSON.stringify(props.logEntry.context, null, 2) : ''));
const recipientString = computed(() => {
    const addresses = Object.keys(additionalData.value.recipients ?? {});
    const recipients = addresses.slice(0, 4).join(' ');

    return addresses.length >= 5 ? `${recipients} ...` : recipients;
});
const onTabChange = (tab: string): void => {
    activeTab.value = tab;
};
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) {
        onClose();
    }
};
const onClose = (): void => {
    emit('close');
};

swDefinePublic({
    activeTab,
    tabItems,
    displayString,
    recipientString,
    onTabChange,
    onModalChange,
    onClose,
});

defineExpose({
    activeTab,
    tabItems,
    displayString,
    recipientString,
    onTabChange,
    onModalChange,
    onClose,
});
</script>

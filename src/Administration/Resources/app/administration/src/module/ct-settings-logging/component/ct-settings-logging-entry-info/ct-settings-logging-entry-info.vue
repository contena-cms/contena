<template>
    <ct-block name="ct_settings_logging_entry_info">
        <ct-modal :title="$t('ct-settings-logging.entryInfo.title')" @modal-close="onClose">
            <ct-block name="ct_settings_logging_entry_info_tabs">
                <div>
                    <mt-tabs :items="tabItems" :default-item="activeTab" @new-item-active="onTabChange" />

                    <div class="ct-settings-logging-entry-info__content">
                        <ct-block name="ct_settings_logging_entry_info_content">
                            <ct-block name="ct_settings_logging_entry_info_raw_content">
                                <mt-textarea v-if="activeTab === 'raw'" :model-value="displayString" />
                            </ct-block>
                        </ct-block>
                    </div>
                </div>
            </ct-block>

            <template #modal-footer>
                <ct-block name="ct_settings_logging_entry_info_footer">
                    <ct-block name="ct_settings_logging_entry_info_close_button">
                        <mt-button size="small" variant="secondary" @click="onClose">
                            {{ $t('global.default.close') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
const props = defineProps({
    logEntry: {
        type: Object,
        required: true,
    },
});
const emit = defineEmits(['close']);

import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const activeTab = ref('raw');

const tabItems = computed(() => {
    return [
        {
            label: t('ct-settings-logging.entryInfo.tabRaw'),
            name: 'raw',
        },
    ];
});
const displayString = computed(() => {
    return props.logEntry.context ? JSON.stringify(props.logEntry.context, null, 2) : '';
});

const onTabChange = (tab) => {
    activeTab.value = tab;
};
const onClose = () => {
    emit('close');
};

ctDefinePublic({
    activeTab,
    tabItems,
    displayString,
    onTabChange,
    onClose,
});

defineExpose({
    activeTab,
    tabItems,
    displayString,
    onTabChange,
    onClose,
});
</script>

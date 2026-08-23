<template>
    <ct-block name="sw_flow_tag_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal :title="title" width="s">
                <ct-block name="sw_flow_tag_modal_content">
                    <mt-entity-select v-model="tagId" entity="tag" required :label="$t('ct-flow.tag.tag')" />
                </ct-block>
                <template #footer>
                    <ct-block name="sw_flow_tag_modal_footer">
                        <div class="ct-flow-modal__footer-actions">
                            <mt-button variant="secondary" @click="onCancel">{{ $t('global.default.cancel') }}</mt-button>
                            <mt-button variant="primary" :disabled="!tagId || undefined" @click="onSave">
                                {{ $t('global.default.save') }}
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

const props = defineProps({
    actionName: { type: String, required: true },
    config: { type: Object as PropType<Record<string, unknown>>, required: true },
});
const emit = defineEmits<{ save: [config: Record<string, unknown>]; cancel: [] }>();
const { t } = useI18n();

const configuredTagIds = props.config.tagIds;
const tagId = ref<string | null>(
    Array.isArray(configuredTagIds) && typeof configuredTagIds[0] === 'string' ? configuredTagIds[0] : null,
);
const title = computed(() =>
    props.actionName === 'action.user.tag.remove' ? t('ct-flow.tag.removeTitle') : t('ct-flow.tag.addTitle'),
);
const onSave = (): void => {
    if (tagId.value) emit('save', { tagIds: [tagId.value] });
};
const onCancel = (): void => emit('cancel');
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) onCancel();
};

swDefinePublic({
    tagId,
    title,
    onSave,
    onCancel,
    onModalChange,
});

defineExpose({ tagId, title, onSave, onCancel, onModalChange });
</script>

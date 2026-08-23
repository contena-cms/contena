<template>
    <ct-block name="sw_channel_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal class="ct-channel-modal" :title="modalTitle" width="l">
                <ct-channel-modal-grid
                    v-if="!detailType"
                    @grid-detail-open="onGridOpenDetails"
                    @grid-channel-add="onAddChannel"
                />
                <ct-channel-modal-detail v-else :detail-type="detailType" />

                <template #footer>
                    <ct-block name="sw_channel_modal_footer">
                        <mt-button
                            v-if="detailType"
                            class="ct-channel-modal__footer-left"
                            variant="secondary"
                            @click="detailType = null"
                        >
                            {{ t('global.default.back') }}
                        </mt-button>
                        <mt-button v-else variant="secondary" @click="onCloseModal">
                            {{ t('global.default.cancel') }}
                        </mt-button>
                        <mt-button v-if="detailType" variant="primary" @click="onAddChannel(detailType.id)">
                            {{ t('ct-channel.modal.buttonAddChannel') }}
                        </mt-button>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
/* global Entity */
/* global Entity */
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';

import './ct-channel-modal.scss';

defineProps({});
const emit = defineEmits<{ 'modal-close': [] }>();
const { t } = useI18n();
const router = useRouter();
const detailType = ref<Entity<'channel_type'> | null>(null);
const modalTitle = computed(() =>
    detailType.value
        ? t('ct-channel.modal.titleDetailPrefix', {
              name: detailType.value.translated?.name || detailType.value.name,
          })
        : t('ct-channel.modal.title'),
);
const onGridOpenDetails = (type: Entity<'channel_type'>): void => {
    detailType.value = type;
};
const onCloseModal = (): void => emit('modal-close');
const onModalChange = (open: boolean): void => {
    if (!open) onCloseModal();
};
const onAddChannel = (typeId: string): void => {
    onCloseModal();
    void router.push({ name: 'ct.channel.create', params: { typeId } });
};

swDefinePublic({
    detailType,
    modalTitle,
    onGridOpenDetails,
    onCloseModal,
    onModalChange,
    onAddChannel,
});

defineExpose({ detailType, modalTitle, onGridOpenDetails, onCloseModal, onModalChange, onAddChannel });
</script>

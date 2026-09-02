<template>
    <ct-block name="ct_settings_listing_delete_modal">
        <mt-modal-root :is-open="true" @change="onModalChange">
            <mt-modal class="ct-settings-listing-delete-modal" :title="title" width="s">
                <ct-block name="ct_settings_listing_delete_modal_body_description">
                    <p>{{ description }}</p>
                </ct-block>

                <template #footer>
                    <ct-block name="ct_settings_listing_delete_modal_footer">
                        <div class="ct-settings-listing-delete-modal__actions">
                            <mt-button variant="secondary" @click="emitCancel">
                                {{ $t('global.default.cancel') }}
                            </mt-button>
                            <mt-button variant="critical" @click="emitDelete">
                                {{ $t('global.default.delete') }}
                            </mt-button>
                        </div>
                    </ct-block>
                </template>
            </mt-modal>
        </mt-modal-root>
    </ct-block>
</template>

<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
}>();
const emit = defineEmits<{
    cancel: [];
    delete: [];
}>();

const emitCancel = (): void => emit('cancel');
const emitDelete = (): void => emit('delete');
const onModalChange = (isOpen: boolean): void => {
    if (!isOpen) emitCancel();
};

ctDefinePublic({
    emitCancel,
    emitDelete,
    onModalChange,
});

defineExpose({ emitCancel, emitDelete, onModalChange });
</script>

<style scoped>
.ct-settings-listing-delete-modal__actions {
    display: flex;
    justify-content: flex-end;
    gap: var(--scale-size-12, 12px);
}
</style>

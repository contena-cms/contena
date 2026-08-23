<template>
    <ct-block name="sw_discard_changes_modal">
        <ct-modal variant="small" :title="$t('ct-discard-changes-modal.title')" @modal-close="keepEditing">
            <ct-block name="sw_discard_changes_modal_text">
                <p class="ct-discard-changes-modal-delete-text">
                    {{ $t('ct-discard-changes-modal.message') }}
                </p>
            </ct-block>

            <template #modal-footer>
                <ct-block name="sw_discard_changes_modal_footer">
                    <ct-block name="sw_discard_changes_modal_discard_changes_modal_cancel">
                        <mt-button variant="secondary" @click="keepEditing">
                            {{ $t('ct-discard-changes-modal.actions.keepEditing') }}
                        </mt-button>

                        <mt-button variant="critical" @click="discardChanges">
                            {{ $t('ct-discard-changes-modal.actions.discard') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
defineProps({});
const emit = defineEmits([
    'keep-editing',
    'discard-changes',
]);

const keepEditing = () => {
    emit('keep-editing');
};
const discardChanges = () => {
    emit('discard-changes');
};

swDefinePublic({
    keepEditing,
    discardChanges,
});

defineExpose({
    keepEditing,
    discardChanges,
});
</script>

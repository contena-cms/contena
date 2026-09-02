<template>
    <ct-block name="ct_category_entry_point_overwrite_modal">
        <ct-confirm-modal
            class="ct-category-entry-point-overwrite-modal"
            @cancel="onCancel"
            @close="onCancel"
            @confirm="onConfirm"
        >
            <p class="ct-confirm-modal__text">
                {{ $t('ct-category.entry-point-overwrite-modal.textBefore', {}, channels.length) }}
            </p>

            <ul>
                <li v-for="channel in channels" :key="channel.translated.name">
                    {{ channel.translated.name }}
                </li>
            </ul>

            <p class="ct-confirm-modal__text">
                {{ $t('ct-category.entry-point-overwrite-modal.textAfter', {}, channels.length) }}
            </p>
        </ct-confirm-modal>
    </ct-block>
</template>

<script setup>
import './ct-category-entry-point-overwrite-modal.scss';

defineProps({
    channels: {
        type: Array,
        required: false,
        default: () => {
            return [];
        },
    },
});
const emit = defineEmits([
    'cancel',
    'confirm',
]);

const onCancel = () => {
    emit('cancel');
};
const onConfirm = () => {
    emit('confirm');
};

ctDefinePublic({
    onCancel,
    onConfirm,
});

defineExpose({
    onCancel,
    onConfirm,
});
</script>

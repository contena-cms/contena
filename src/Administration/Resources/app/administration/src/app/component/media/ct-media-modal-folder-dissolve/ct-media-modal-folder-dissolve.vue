<template>
    <ct-block name="sw_media_modal_folder_dissolve">
        <ct-modal
            variant="small"
            class="ct-media-modal-folder-dissolve"
            :title="$t('global.default.warning')"
            @modal-close="closeDissolveModal"
        >
            <ct-block name="sw_media_modal_folder_dissolve_body">
                {{
                    $t(
                        'global.ct-media-modal-folder-dissolve.dissolveMessage',
                        { folderName: itemsToDissolve[0].name, count: itemsToDissolve.length },
                        itemsToDissolve.length,
                    )
                }}
            </ct-block>

            <template #modal-footer>
                <ct-block name="sw_media_modal_folder_dissolve_footer">
                    <ct-block name="sw_media_modal_folder_dissolve__cancel_button">
                        <mt-button size="small" variant="secondary" @click="closeDissolveModal">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_media_modal_folder_dissolve_confirm_button">
                        <mt-button
                            class="ct-media-modal-folder-dissolve__confirm"
                            size="small"
                            variant="critical"
                            @click="dissolveSelection"
                        >
                            {{ $t('global.ct-media-modal-folder-dissolve.buttonDissolve') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
const props = defineProps({
    itemsToDissolve: {
        required: true,
        type: Array,
        validator(value) {
            return value.length !== 0;
        },
    },
});
const emit = defineEmits([
    'media-folder-dissolve-modal-close',
    'media-folder-dissolve-modal-dissolve',
]);

import { inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const mediaFolderService = inject('mediaFolderService');

const closeDissolveModal = (originalDomEvent) => {
    emit('media-folder-dissolve-modal-close', {
        originalDomEvent,
    });
};
const dissolveItem = async (item) => {
    item.isLoading = true;

    try {
        await mediaFolderService.dissolveFolder(item.id);

        createNotificationSuccess({
            title: t('global.default.success'),
            message: t(
                'global.ct-media-modal-folder-dissolve.notification.successSingle.message',
                {
                    folderName: item.name,
                },
                1,
            ),
        });
        return item.id;
    } catch {
        createNotificationError({
            title: t('global.default.error'),
            message: t(
                'global.ct-media-modal-folder-dissolve.notification.errorSingle.message',
                {
                    folderName: item.name,
                },
                1,
            ),
        });

        return null;
    } finally {
        item.isLoading = false;
    }
};
const dissolveSelection = async () => {
    const dissolvedIds = [];

    try {
        await Promise.all(
            props.itemsToDissolve.map((item) => {
                dissolvedIds.push(item.id);
                return dissolveItem(item);
            }),
        );

        if (props.itemsToDissolve.length > 1) {
            createNotificationSuccess({
                title: t('global.default.success'),
                message: t('global.ct-media-modal-folder-dissolve.notification.successOverall.message'),
            });
        }

        emit('media-folder-dissolve-modal-dissolve', dissolvedIds);
    } catch {
        if (props.itemsToDissolve.length > 1) {
            createNotificationError({
                title: t('global.default.error'),
                message: t('global.ct-media-modal-folder-dissolve.notification.errorOverall.message'),
            });
        }
    }
};

swDefinePublic({
    mediaFolderService,
    closeDissolveModal,
    dissolveItem,
    dissolveSelection,
});

defineExpose({
    mediaFolderService,
    closeDissolveModal,
    dissolveItem,
    dissolveSelection,
});
</script>

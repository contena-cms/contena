<template>
    <ct-block name="sw_media_modal_delete">
        <ct-modal
            variant="small"
            class="ct-media-modal-delete"
            :title="snippets.modalTitle"
            @modal-close="closeDeleteModal($event)"
        >
            <ct-block name="sw_media_modal_body">
                <div v-if="mediaQuickInfo">
                    <p>{{ $t('global.ct-media-modal-delete.mediaQuickInfoMessage') }}</p>
                    <ct-media-quickinfo-usage :item="mediaQuickInfo" router-link-target="_blank" />
                </div>
                <div v-if="mediaInUsages && mediaInUsages.length > 0">
                    <p>{{ $t('global.ct-media-modal-delete.mediaInUsagesMessage') }}</p>
                    <ct-media-media-item
                        v-for="mediaInUsage in mediaInUsages"
                        :key="`ct-media-modal-delete-${mediaInUsage.id}`"
                        class="ct-media-modal-delete__media-list-item"
                        :item="mediaInUsage"
                        :is-list="true"
                        :editable="false"
                        :selected="false"
                        :show-selection-indicator="false"
                        :show-context-menu-button="false"
                    />
                </div>
                <p v-html="$sanitize(snippets.deleteMessage)"></p>
            </ct-block>

            <template #modal-footer>
                <ct-block name="sw_media_modal_footer">
                    <ct-block name="sw_media_modal_delete_cancel_button">
                        <mt-button size="small" variant="secondary" @click="closeDeleteModal($event)">
                            {{ $t('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="sw_media_modal_delete_confirm_button">
                        <mt-button
                            class="ct-media-modal-delete__confirm"
                            size="small"
                            variant="critical"
                            @click="deleteSelection($event)"
                        >
                            {{ $t('global.default.delete') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-media-modal-delete.scss';
const { Context, Filter } = Contena;

const props = defineProps({
    itemsToDelete: {
        required: true,
        type: Array,
        validator(value) {
            return value.length !== 0;
        },
    },
});
const emit = defineEmits([
    'media-delete-modal-close',
    'media-delete-modal-items-delete',
]);

import { ref, computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationError } = useNotification();

const repositoryFactory = inject('repositoryFactory');

const mediaItems = ref([]);
const folders = ref([]);
const notificationId = ref(null);

const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const mediaNameFilter = computed(() => {
    return Filter.getByName('mediaName');
});
const snippets = computed(() => {
    if (mediaItems.value.length > 0 && folders.value.length > 0) {
        return {
            successOverall: 'global.ct-media-modal-delete.notification.successOverall.message.mediaAndFolder',
            errorOverall: t('global.ct-media-modal-delete.notification.errorOverall.message.mediaAndFolder'),
            modalTitle: t('global.default.warning'),
            deleteMessage: t('global.ct-media-modal-delete.deleteMessage.mediaAndFolder', props.itemsToDelete.length, {
                mediaCount: mediaItems.value.length,
                folderCount: folders.value.length,
            }),
        };
    }

    if (mediaItems.value.length > 0) {
        return {
            successOverall: 'global.ct-media-modal-delete.notification.successOverall.message.media',
            errorOverall: t('global.ct-media-modal-delete.notification.errorOverall.message.media'),
            modalTitle: t('global.default.warning'),
            deleteMessage: t(
                'global.ct-media-modal-delete.deleteMessage.media',
                {
                    name: mediaNameFilter.value(mediaItems.value[0]),
                    count: mediaItems.value.length,
                },
                mediaItems.value.length,
            ),
        };
    }

    return {
        successOverall: 'global.ct-media-modal-delete.notification.successOverall.message.folder',
        errorOverall: t('global.ct-media-modal-delete.notification.errorOverall.message.folder'),
        modalTitle: t('global.default.warning'),
        deleteMessage: t(
            'global.ct-media-modal-delete.deleteMessage.folder',
            {
                name: folders.value[0].name,
                count: folders.value.length,
            },
            folders.value.length,
        ),
    };
});
const mediaQuickInfo = computed(() => {
    const usedMediaItem = mediaItems.value.length === 1 && checkInUsage(mediaItems.value[0]);
    return usedMediaItem ? mediaItems.value[0] : null;
});
const mediaInUsages = computed(() => {
    if (mediaItems.value.length <= 1) return [];

    return mediaItems.value.filter((mediaItem) => checkInUsage(mediaItem));
});

const createdComponent = () => {
    mediaItems.value = props.itemsToDelete.filter((item) => {
        return item.getEntityName() === 'media';
    });

    folders.value = props.itemsToDelete.filter((item) => {
        return item.getEntityName() === 'media_folder';
    });
};
const closeDeleteModal = (originalDomEvent) => {
    emit('media-delete-modal-close', { originalDomEvent });
};
const getEntityRepository = (entityName) => {
    if (entityName === 'media') {
        return mediaRepository.value;
    }

    if (entityName === 'media_folder') {
        return mediaFolderRepository.value;
    }

    return null;
};
const deleteItem = (item) => {
    const entityName = item.getEntityName();
    const repository = getEntityRepository(entityName);

    item.isLoading = true;

    return repository
        .delete(item.id, Context.api)
        .then(() => {
            return true;
        })
        .catch(() => {
            const isMedia = item.getEntityName() === 'media';
            const errorSnippet = 'global.ct-media-modal-delete.notification.errorSingle.message';

            const message = isMedia
                ? t(`${errorSnippet}.media`, 1, {
                      name: mediaNameFilter.value(item),
                  })
                : t(`${errorSnippet}.folder`, 1, {
                      name: item.name,
                  });

            createNotificationError({
                message,
            });

            return false;
        })
        .finally(() => {
            item.isLoading = false;
        });
};
const deleteSelection = async () => {
    const deleteSelections = props.itemsToDelete.map((item) => {
        return deleteItem(item).catch(() => false);
    });

    const deletions = await Promise.all(deleteSelections);

    const amounts = deletions.reduce(
        (acc, isSuccess) => {
            acc.success = isSuccess ? (acc.success += 1) : acc.success;
            acc.failure = isSuccess ? acc.failure : (acc.failure += 1);

            return acc;
        },
        { success: 0, failure: 0 },
    );

    if (amounts.success > 0) {
        void updateSuccessNotification(amounts.success, amounts.failure, deletions.length);
    }

    emit('media-delete-modal-items-delete', {
        mediaIds: mediaItems.value.map((media) => {
            return media.id;
        }),
        folderIds: folders.value.map((folder) => {
            return folder.id;
        }),
    });
};
async function updateSuccessNotification(successAmount, failureAmount, totalAmount) {
    const notification = {
        message: t(snippets.value.successOverall, successAmount, {
            count: successAmount,
            total: totalAmount,
        }),
        growl: successAmount + failureAmount === totalAmount,
    };
    if (notificationId.value !== null) {
        await Promise.resolve(
            Contena.Store.get('notification').updateNotification({
                uuid: notificationId.value,
                ...notification,
            }),
        );
        if (successAmount + failureAmount === totalAmount) {
            notificationId.value = null;
        }
        return;
    }
    const newNotificationId = await Promise.resolve(
        Contena.Store.get('notification').createNotification({
            variant: 'success',
            ...notification,
        }),
    );
    if (successAmount + failureAmount < totalAmount) {
        notificationId.value = newNotificationId;
    }
}
function checkInUsage(mediaItem) {
    if (mediaItem.avatarUsers?.[0]) {
        return true;
    }
    return false;
}

createdComponent();

swDefinePublic({
    repositoryFactory,
    mediaItems,
    folders,
    notificationId,
    mediaRepository,
    mediaFolderRepository,
    mediaNameFilter,
    snippets,
    mediaQuickInfo,
    mediaInUsages,
    createdComponent,
    closeDeleteModal,
    getEntityRepository,
    deleteItem,
    deleteSelection,
    updateSuccessNotification,
    checkInUsage,
});

defineExpose({
    repositoryFactory,
    mediaItems,
    folders,
    notificationId,
    mediaRepository,
    mediaFolderRepository,
    mediaNameFilter,
    snippets,
    mediaQuickInfo,
    mediaInUsages,
    createdComponent,
    closeDeleteModal,
    getEntityRepository,
    deleteItem,
    deleteSelection,
    updateSuccessNotification,
    checkInUsage,
});
</script>

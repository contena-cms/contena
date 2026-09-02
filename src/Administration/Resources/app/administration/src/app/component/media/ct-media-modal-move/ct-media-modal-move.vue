<template>
    <ct-block name="ct_media_modal_move">
        <ct-modal
            variant="default"
            class="ct-media-modal-move"
            :title="
                translate('global.ct-media-modal-move.titleModal', {
                    mediaName: mediaNameFilter(itemsToMove[0]),
                    count: itemsToMove.length,
                })
            "
            @modal-close="closeMoveModal"
        >
            <ct-block name="ct_media_modal_body">
                <ct-block name="ct_media_modal_move_breadcrumbs">
                    <div class="ct-media-modal-move-folder-breadcrumbs">
                        <img
                            :src="assetFilter('/administration/administration/static/img/media/folder-thumbnail.svg')"
                            class="ct-media-modal-move__folder-icon"
                            alt="Folder thumbnail"
                        />

                        <button
                            v-if="parentFolder && parentFolder.id !== targetFolder.id"
                            class="ct-media-modal-move__breadcrumb-btn --parent"
                            @click="onSelection(parentFolder)"
                        >
                            <mt-icon
                                class="ct-media-folder-content__switch-button"
                                name="regular-chevron-right-xs"
                                size="10px"
                            />
                            {{ parentFolder.name }}
                        </button>

                        <button
                            v-if="displayFolder && displayFolder.id !== targetFolder.id"
                            class="ct-media-modal-move__breadcrumb-btn"
                            @click="onSelection(displayFolder)"
                        >
                            <mt-icon
                                class="ct-media-folder-content__switch-button"
                                name="regular-chevron-right-xs"
                                size="10px"
                            />
                            {{ displayFolder.name }}
                        </button>

                        <button
                            v-if="targetFolder"
                            class="ct-media-modal-move__breadcrumb-btn --target"
                            @click="onSelection(targetFolder)"
                        >
                            <mt-icon
                                class="ct-media-folder-content__switch-button"
                                name="regular-chevron-right-xs"
                                size="10px"
                            />
                            {{ targetFolder.name }}
                        </button>
                    </div>
                </ct-block>
                <ct-media-folder-content
                    :start-folder-id="displayFolderId"
                    :selected-id="targetFolderId"
                    @selected="onSelection"
                />
            </ct-block>

            <template #modal-footer>
                <ct-block name="ct_media_modal_footer">
                    <ct-block name="ct_media_modal_move_cancel_button">
                        <mt-button size="small" variant="secondary" @click="closeMoveModal">
                            {{ translate('global.default.cancel') }}
                        </mt-button>
                    </ct-block>

                    <ct-block name="ct_media_modal_move_confirm_button">
                        <mt-button
                            class="ct-media-modal-move__confirm"
                            size="small"
                            variant="primary"
                            :disabled="isMoveDisabled"
                            @click="moveSelection"
                        >
                            {{ translate('global.ct-media-modal-move.buttonMove') }}
                        </mt-button>
                    </ct-block>
                </ct-block>
            </template>
        </ct-modal>
    </ct-block>
</template>

<script setup>
import './ct-media-modal-move.scss';
const {
    Context,
    Data: { Criteria },
} = Contena;

const props = defineProps({
    itemsToMove: {
        required: true,
        type: Array,
        validator(value) {
            return value.length > 0;
        },
    },
});
const emit = defineEmits([
    'media-move-modal-close',
    'media-move-modal-items-move',
]);

import { ref, computed, inject, provide, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useNotification } from 'src/app/composables/use-notification';

const { t } = useI18n();
const { createNotificationSuccess, createNotificationError } = useNotification();

const translate = t;
const repositoryFactory = inject('repositoryFactory');

const targetFolder = ref(null);
const parentFolder = ref(null);
const displayFolder = ref(null);
const displayFolderId = ref(null);

const mediaRepository = computed(() => {
    return repositoryFactory.create('media');
});
const mediaFolderRepository = computed(() => {
    return repositoryFactory.create('media_folder');
});
const mediaNameFilter = computed(() => {
    return (media) => {
        return media.getEntityName() === 'media' ? `${media.fileName}.${media.fileExtension}` : media.name;
    };
});
const targetFolderId = computed(() => {
    return targetFolder.value ? targetFolder.value.id : null;
});
const rootFolderName = computed(() => {
    return t('ct-media.index.rootFolderName');
});
const isMoveDisabled = computed(() => {
    return startFolderId.value === targetFolderId.value;
});
const startFolderId = computed(() => {
    const firstItem = props.itemsToMove[0];
    if (firstItem.getEntityName() === 'media') {
        return firstItem.mediaFolderId;
    }

    return firstItem.parentId;
});
const assetFilter = computed(() => {
    return Contena.Filter.getByName('asset');
});

const mountedComponent = async () => {
    displayFolder.value = { id: null, name: rootFolderName.value };
    targetFolder.value = { id: null, name: rootFolderName.value };

    if (startFolderId.value) {
        const folder = await mediaFolderRepository.value.get(startFolderId.value, Context.api);
        displayFolder.value = folder;
        targetFolder.value = folder;
    }
};
const closeMoveModal = () => {
    emit('media-move-modal-close');
};
const isNotPartOfItemsToMove = (item) => {
    return !props.itemsToMove.some((i) => {
        return i.id === item.id;
    });
};
const updateParentFolder = async (child) => {
    if (child.id === null) {
        parentFolder.value = null;
    } else if (child.parentId === null) {
        parentFolder.value = { id: null, name: rootFolderName.value };
    } else {
        parentFolder.value = await fetchParentFolder(child.parentId);
    }
};
async function fetchParentFolder(id) {
    let items = null;
    const criteria = new Criteria(1, 1).addFilter(Criteria.equals('id', id)).addAssociation('children');
    try {
        items = await mediaFolderRepository.value.search(criteria, Context.api);
    } catch {
        createNotificationError({
            message: t('global.ct-media-modal-move.notification.errorFetchNavigation.message'),
        });
    }
    if (items?.length) {
        return items[0];
    }
    return null;
}
const onSelection = (folder) => {
    targetFolder.value = folder;

    if (folder.id === null || folder.childCount > 0) {
        displayFolder.value = folder;
    }
};
const moveFolder = async (item) => {
    item.isLoading = true;
    item.parentId = targetFolder.value.id || null;

    try {
        await mediaFolderRepository.value.save(item, Context.api);

        createNotificationSuccess({
            title: t('global.default.success'),
            message: t(
                'global.ct-media-modal-move.notification.successSingle.message',
                {
                    mediaName: mediaNameFilter.value(item),
                },
                1,
            ),
        });

        return item.id;
    } catch {
        createNotificationError({
            title: t('global.default.error'),
            message: t(
                'global.ct-media-modal-move.notification.errorSingle.message',
                {
                    mediaName: mediaNameFilter.value(item),
                },
                1,
            ),
        });

        return null;
    } finally {
        item.isLoading = false;
    }
};
const moveSelection = async () => {
    const movedIds = [];

    try {
        const folders = props.itemsToMove.filter((item) => {
            return item.getEntityName() === 'media_folder';
        });

        const media = props.itemsToMove.filter((item) => {
            return item.getEntityName() === 'media';
        });

        await Promise.all(
            folders.map(async (folder) => {
                await moveFolder(folder);
            }),
        );

        await Promise.all(
            media.map(async (mediaItem) => {
                const item = mediaItem;
                item.mediaFolderId = targetFolder.value.id || null;
                movedIds.push(await mediaRepository.value.save(item, Context.api));
            }),
        );

        createNotificationSuccess({
            title: t('global.default.success'),
            message: t('global.ct-media-modal-move.notification.successOverall.message'),
        });

        emit('media-move-modal-items-move', movedIds);
    } catch {
        createNotificationError({
            title: t('global.default.error'),
            message: t('global.ct-media-modal-move.notification.errorOverall.message'),
        });
    }
};

watch(
    () => displayFolder.value,
    (newFolder) => {
        displayFolderId.value = newFolder.id;
        void updateParentFolder(newFolder);
    },
);

onMounted(() => {
    void mountedComponent();
});

ctDefinePublic({
    repositoryFactory,
    targetFolder,
    parentFolder,
    displayFolder,
    displayFolderId,
    mediaRepository,
    mediaFolderRepository,
    mediaNameFilter,
    targetFolderId,
    rootFolderName,
    isMoveDisabled,
    startFolderId,
    assetFilter,
    mountedComponent,
    closeMoveModal,
    isNotPartOfItemsToMove,
    updateParentFolder,
    fetchParentFolder,
    onSelection,
    moveSelection,
});

provide('filterItems', isNotPartOfItemsToMove);

defineExpose({
    repositoryFactory,
    targetFolder,
    parentFolder,
    displayFolder,
    displayFolderId,
    mediaRepository,
    mediaFolderRepository,
    mediaNameFilter,
    targetFolderId,
    rootFolderName,
    isMoveDisabled,
    startFolderId,
    assetFilter,
    mountedComponent,
    closeMoveModal,
    isNotPartOfItemsToMove,
    updateParentFolder,
    fetchParentFolder,
    onSelection,
    moveSelection,
});
</script>

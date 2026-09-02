<template>
    <ct-block name="ct_media_list_selection_v2">
        <div class="ct-media-list-selection-v2">
            <ct-block name="ct_media_list_selection_v2_upload">
                <ct-media-upload-v2
                    :upload-tag="uploadId"
                    variant="regular"
                    :default-folder="defaultFolderName"
                    :disabled="disabled"
                    @media-upload-sidebar-open="onMediaUploadButtonOpenSidebar"
                />
            </ct-block>

            <ct-block name="ct_media_list_selection_v2_grid">
                <div ref="grid" class="ct-media-list-selection-v2__grid" :style="gridAutoRows">
                    <mt-loader v-if="entity.isLoading" />

                    <ct-block name="ct_media_list_selection_v2_grid_items">
                        <ct-media-list-selection-item-v2
                            v-for="(mediaItem, index) in mediaItems"
                            :key="mediaItem.url"
                            v-draggable="{
                                disabled,
                                dragGroup: 'media-items',
                                data: mediaItem,
                                onDragEnter: onDeboundDragDrop,
                            }"
                            v-droppable="{ disabled, dragGroup: 'media-items', data: mediaItem }"
                            :item="mediaItem"
                            :disabled="disabled"
                            @item-remove="removeItem(mediaItem, index)"
                        />
                    </ct-block>
                </div>
            </ct-block>
        </div>
    </ct-block>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, inject, nextTick, onMounted, ref } from 'vue';

import './ct-media-list-selection-v2.scss';

type MediaListItem = {
    id?: string;
    mediaId: string | number;
    targetId?: string;
    url?: string;
    position: number;
    isPlaceholder?: boolean;
    media?: {
        isPlaceholder: boolean;
        name: string;
    };
};

type ListEntity = {
    id: string;
    isLoading: boolean;
    getEntityName: () => string;
};

type MediaService = {
    runUploads: (uploadId: string) => Promise<void>;
};

const props = withDefaults(
    defineProps<{
        entity: ListEntity;
        entityMediaItems: MediaListItem[];
        uploadTag?: string | null;
        defaultFolderName?: string | null;
        disabled?: boolean;
    }>(),
    {
        uploadTag: null,
        defaultFolderName: null,
        disabled: false,
    },
);

const emit = defineEmits<{
    (event: 'open-sidebar'): void;
    (event: 'upload-finish', media: unknown): void;
    (event: 'item-sort', dragData: MediaListItem, dropData: MediaListItem): void;
    (event: 'item-remove', mediaItem: MediaListItem, index?: number): void;
}>();

const repositoryFactory = inject<RepositoryFactory>('repositoryFactory')!;
const mediaService = inject<MediaService>('mediaService')!;
const mediaRepository = computed(() => repositoryFactory.create('media'));
const entityState = computed(() => props.entity);

const grid = ref<HTMLElement | null>(null);
const columnCount = ref(8);
const columnWidth = ref('90px');

const currentCount = computed(() => props.entityMediaItems.length);
const mediaItems = computed<MediaListItem[]>(() => {
    const maximumVisibleItems = columnCount.value * 2;
    const items = [...props.entityMediaItems];

    if (currentCount.value < maximumVisibleItems) {
        items.splice(currentCount.value, 0, ...createPlaceholders(maximumVisibleItems - currentCount.value));
    }

    items.forEach((item, index) => {
        item.position = index;
    });

    return items;
});
const gridAutoRows = computed(() => `grid-auto-rows: ${columnWidth.value}`);
const uploadId = computed(() => props.uploadTag || props.entity.id);
const defaultFolder = computed(() => props.defaultFolderName || props.entity.getEntityName());

const mountedComponent = () => {
    const component = getCurrentInstance()?.proxy;
    component?.$device.onResize({
        listener: updateColumnCount,
        component,
    });
    updateColumnCount();
};

const updateColumnCount = () => {
    void nextTick(() => {
        if (!grid.value) {
            return;
        }

        const cssColumns = window.getComputedStyle(grid.value).getPropertyValue('grid-template-columns').split(' ');
        columnCount.value = cssColumns.length;
        columnWidth.value = cssColumns[0];
    });
};

function createPlaceholders(count: number): MediaListItem[] {
    return new Array(count).fill({
        isPlaceholder: true,
        media: {
            isPlaceholder: true,
            name: '',
        },
        mediaId: currentCount.value,
    });
}

const onUploadsAdded = async ({ data }: { data: unknown[] }) => {
    if (data.length === 0) {
        return;
    }

    entityState.value.isLoading = true;
    await mediaService.runUploads(uploadId.value);
    entityState.value.isLoading = false;
};

const onMediaUploadButtonOpenSidebar = () => {
    emit('open-sidebar');
};

const successfulUpload = async ({ targetId }: { targetId: string }) => {
    const mediaItem = await mediaRepository.value.get(targetId, Contena.Context.api);
    getCurrentInstance()?.proxy?.$forceUpdate();
    emit('upload-finish', mediaItem);
};

const onUploadFailed = (uploadTask: { targetId: string }) => {
    const toRemove = mediaItems.value.find((media) => media.mediaId === uploadTask.targetId);

    if (toRemove) {
        removeItem(toRemove);
    }

    entityState.value.isLoading = false;
};

const onMediaItemDragSort = (dragData?: MediaListItem, dropData?: MediaListItem, validDrop?: boolean) => {
    if (
        validDrop !== true ||
        !dragData ||
        !dropData ||
        dropData.position > currentCount.value ||
        dragData.position > currentCount.value
    ) {
        return;
    }

    emit('item-sort', dragData, dropData);
};

const onDeboundDragDrop = Contena.Utils.debounce(onMediaItemDragSort, 500);

const removeItem = (mediaItem: MediaListItem, index?: number) => {
    emit('item-remove', mediaItem, index);
};

onMounted(mountedComponent);

ctDefinePublic({
    columnCount,
    columnWidth,
    mediaRepository,
    currentCount,
    mediaItems,
    gridAutoRows,
    uploadId,
    defaultFolder,
    mountedComponent,
    updateColumnCount,
    createPlaceholders,
    onUploadsAdded,
    onMediaUploadButtonOpenSidebar,
    successfulUpload,
    onUploadFailed,
    onMediaItemDragSort,
    onDeboundDragDrop,
    removeItem,
});
</script>
